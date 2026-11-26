<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MarketingCampaign;
use App\Models\MarketingConsent;
use App\Support\Rbac\AdminAuditLogger;
use App\Support\Rbac\ExplicitPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Banner\Models\Banner;
use Modules\Coupon\Models\Coupon;

class AdminMarketingController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        $this->authorizeMarketing($request, 'marketing.campaigns.manage');
        return response()->json(['data' => [
            'campaigns' => MarketingCampaign::query()->count(),
            'scheduled' => MarketingCampaign::query()->where('status', 'scheduled')->count(),
            'active' => MarketingCampaign::query()->where('status', 'published')->count(),
            'expired' => MarketingCampaign::query()->where('status', 'expired')->count(),
            'banners' => Banner::query()->count(),
            'email_consents' => MarketingConsent::query()->where('channel', 'email')->where('consented', true)->count(),
            'push_consents' => MarketingConsent::query()->where('channel', 'push')->where('consented', true)->count(),
        ]]);
    }

    public function campaigns(Request $request): JsonResponse
    {
        $this->authorizeMarketing($request, 'marketing.campaigns.manage');
        $request->validate(['status' => ['sometimes', Rule::in(['draft', 'pending_approval', 'approved', 'scheduled', 'published', 'paused', 'expired', 'rejected'])]]);
        $query = MarketingCampaign::query()->withCount([
            'deliveries as recipients_count',
            'deliveries as sent_count' => fn ($q) => $q->where('status', 'sent'),
            'deliveries as failed_count' => fn ($q) => $q->where('status', 'failed'),
        ])->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))->latest();
        return response()->json($query->paginate(min(max($request->integer('per_page', 25), 1), 100)));
    }

    public function createCampaign(Request $request, AdminAuditLogger $audit): JsonResponse
    {
        $this->authorizeMarketing($request, 'marketing.campaigns.manage');
        $data = $this->validateCampaign($request);
        $campaign = DB::transaction(function () use ($request, $data, $audit): MarketingCampaign {
            $data['slug'] = $data['slug'] ?? (Str::slug($data['name']).'-'.Str::lower(Str::random(6)));
            $campaign = MarketingCampaign::query()->create($data + ['created_by' => $request->user()->id, 'status' => 'draft']);
            $audit->record($request->user(), 'marketing.campaign.created', MarketingCampaign::class, $campaign->id, null, ['kind' => $campaign->kind, 'channel' => $campaign->channel]);
            return $campaign;
        });
        return response()->json(['data' => $campaign], 201);
    }

    public function updateCampaign(Request $request, MarketingCampaign $campaign, AdminAuditLogger $audit): JsonResponse
    {
        $this->authorizeMarketing($request, 'marketing.campaigns.manage');
        abort_unless(in_array($campaign->status, ['draft', 'rejected'], true), 409, 'Only draft or rejected campaigns can be edited.');
        $data = $this->validateCampaign($request);
        $before = $campaign->only(['name', 'kind', 'channel', 'status']);
        $campaign->fill($data)->save();
        $audit->record($request->user(), 'marketing.campaign.updated', MarketingCampaign::class, $campaign->id, $before, $campaign->only(['name', 'kind', 'channel', 'status']));
        return response()->json(['data' => $campaign->fresh()]);
    }

    public function submitForApproval(Request $request, MarketingCampaign $campaign, AdminAuditLogger $audit): JsonResponse
    {
        $this->authorizeMarketing($request, 'marketing.campaigns.manage');
        abort_unless(in_array($campaign->status, ['draft', 'rejected'], true), 409);
        $campaign->forceFill(['status' => 'pending_approval'])->save();
        $audit->record($request->user(), 'marketing.campaign.submitted', MarketingCampaign::class, $campaign->id, null, ['status' => 'pending_approval']);
        return response()->json(['data' => $campaign]);
    }

    public function approveCampaign(Request $request, MarketingCampaign $campaign, AdminAuditLogger $audit): JsonResponse
    {
        $this->authorizeMarketing($request, 'marketing.campaigns.approve');
        abort_unless($campaign->status === 'pending_approval', 409);
        abort_if((int) $campaign->created_by === (int) $request->user()->id, 403, 'Campaigns require an independent approver.');
        $this->assertCampaignEligible($campaign);
        $campaign->forceFill(['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now()])->save();
        $audit->record($request->user(), 'marketing.campaign.approved', MarketingCampaign::class, $campaign->id, ['status' => 'pending_approval'], ['status' => 'approved']);
        return response()->json(['data' => $campaign]);
    }

    public function rejectCampaign(Request $request, MarketingCampaign $campaign, AdminAuditLogger $audit): JsonResponse
    {
        $this->authorizeMarketing($request, 'marketing.campaigns.approve');
        abort_unless($campaign->status === 'pending_approval', 409);
        abort_if((int) $campaign->created_by === (int) $request->user()->id, 403, 'Campaigns require an independent reviewer.');
        $data = $request->validate(['review_note' => ['required', 'string', 'min:5', 'max:2000']]);
        $campaign->forceFill(['status' => 'rejected'])->save();
        $audit->record($request->user(), 'marketing.campaign.rejected', MarketingCampaign::class, $campaign->id, ['status' => 'pending_approval'], ['status' => 'rejected', 'review_note' => $data['review_note']]);
        return response()->json(['data' => $campaign]);
    }

    public function scheduleCampaign(Request $request, MarketingCampaign $campaign, AdminAuditLogger $audit): JsonResponse
    {
        $this->authorizeMarketing($request, 'marketing.campaigns.manage');
        abort_unless($campaign->status === 'approved', 409, 'Campaign approval is required before scheduling.');
        $this->assertCampaignEligible($campaign);
        $data = $request->validate([
            'scheduled_at' => ['required', 'date', 'after:now'],
            'expires_at' => ['nullable', 'date', 'after:scheduled_at'],
        ]);
        $campaign->forceFill(['status' => 'scheduled', 'scheduled_at' => $data['scheduled_at'], 'expires_at' => $data['expires_at'] ?? null])->save();
        $audit->record($request->user(), 'marketing.campaign.scheduled', MarketingCampaign::class, $campaign->id, null, ['scheduled_at' => $campaign->scheduled_at, 'expires_at' => $campaign->expires_at]);
        return response()->json(['data' => $campaign]);
    }

    public function pauseCampaign(Request $request, MarketingCampaign $campaign, AdminAuditLogger $audit): JsonResponse
    {
        $this->authorizeMarketing($request, 'marketing.campaigns.manage');
        abort_unless(in_array($campaign->status, ['scheduled', 'published'], true), 409);
        $previous = $campaign->status;
        $campaign->forceFill(['status' => 'paused', 'deactivated_at' => now()])->save();
        $audit->record($request->user(), 'marketing.campaign.deactivated', MarketingCampaign::class, $campaign->id, ['status' => $previous], ['status' => 'paused']);
        return response()->json(['data' => $campaign]);
    }

    public function analytics(Request $request, MarketingCampaign $campaign): JsonResponse
    {
        $this->authorizeMarketing($request, 'marketing.campaigns.manage');
        $counts = $campaign->deliveries()->select('channel', 'variant', 'status', DB::raw('COUNT(*) as count'), DB::raw('SUM(opened_at IS NOT NULL) as opens'), DB::raw('SUM(clicked_at IS NOT NULL) as clicks'))
            ->groupBy('channel', 'variant', 'status')->get();
        return response()->json(['data' => ['campaign' => $campaign->only(['id', 'name', 'status', 'published_at', 'expires_at']), 'performance' => $counts]]);
    }

    public function publicLanding(string $slug): JsonResponse
    {
        $campaign = MarketingCampaign::query()->where('slug', $slug)->whereIn('kind', ['landing_page', 'promotion'])
            ->where('status', 'published')->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))->firstOrFail();
        $coupon = null;
        if ($campaign->coupon_id) {
            $candidate = Coupon::query()->whereKey($campaign->coupon_id)->where('status', true)
                ->where(fn ($query) => $query->whereNull('start_date')->orWhereDate('start_date', '<=', today()))
                ->where(fn ($query) => $query->whereNull('expire_date')->orWhereDate('expire_date', '>=', today()))->first();
            if ($candidate) $coupon = ['code' => $candidate->code, 'discount_type' => $candidate->discount_type, 'discount' => $candidate->discount];
            abort_unless($coupon, 404, 'This promotion is no longer eligible.');
        }
        return response()->json(['data' => [
            'name' => $campaign->name, 'headline' => $campaign->landing_page['headline'] ?? $campaign->name,
            'description' => $campaign->landing_page['description'] ?? $campaign->body,
            'hero_image' => $campaign->landing_page['hero_image'] ?? null,
            'call_to_action' => $campaign->call_to_action, 'destination_url' => $campaign->destination_url,
            'featured_content' => $campaign->featured_content ?? [], 'coupon' => $coupon,
            'expires_at' => $campaign->expires_at,
        ]]);
    }

    public function publicFeaturedCollections(): JsonResponse
    {
        $campaigns = MarketingCampaign::query()->where('kind', 'featured_collection')->where('status', 'published')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest('published_at')->limit(20)->get(['id', 'name', 'slug', 'featured_content', 'published_at', 'expires_at']);
        return response()->json(['data' => $campaigns]);
    }

    public function segments(Request $request): JsonResponse
    {
        $this->authorizeMarketing($request, 'marketing.campaigns.manage');
        $segments = [];
        foreach (['all_consented' => ['email', 'push'], 'email_consented' => ['email'], 'push_consented' => ['push']] as $name => $channels) {
            foreach ($channels as $channel) {
                $segments[$name][$channel] = MarketingConsent::query()->where('channel', $channel)->where('consented', true)->count();
            }
        }
        $segments['subscribers'] = $this->recipientQuery('all_consented', 'email')->where('users.is_subscribe', 1)->count();
        $segments['registered'] = $this->recipientQuery('all_consented', 'email')->where('users.is_subscribe', 0)->count();
        return response()->json(['data' => $segments]);
    }

    public function banners(Request $request): JsonResponse
    {
        $this->authorizeMarketing($request, 'marketing.banners.manage');
        return response()->json(Banner::query()->withTrashed()->latest()->paginate(min(max($request->integer('per_page', 50), 1), 100)));
    }

    public function saveBanner(Request $request, ?Banner $banner = null): JsonResponse
    {
        $this->authorizeMarketing($request, 'marketing.banners.manage');
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'], 'banner_for' => ['required', Rule::in(['home', 'movie', 'tv_show', 'livetv', 'video', 'promotional'])],
            'type' => ['nullable', Rule::in(['movie', 'tvshow', 'livetv', 'video'])], 'type_id' => ['nullable', 'string', 'max:191'],
            'type_name' => ['nullable', 'string', 'max:191'], 'file_url' => ['nullable', 'string', 'max:2048'], 'poster_url' => ['nullable', 'string', 'max:2048'],
            'description' => ['nullable', 'string', 'max:5000'], 'click_url' => ['nullable', 'url', 'regex:/^https?:\/\//i', 'max:2048'],
            'status' => ['required', 'boolean'], 'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ]);
        $banner ??= new Banner();
        $isNew = !$banner->exists;
        $before = $banner->only(['title', 'banner_for', 'type', 'type_id', 'status', 'starts_at', 'ends_at']);
        $banner->fill($data + ['created_by' => $request->user()->id]);
        $banner->save();
        clearRelatedCache(['banner_list'], 'banner');
        app(AdminAuditLogger::class)->record($request->user(), $isNew ? 'marketing.banner.created' : 'marketing.banner.updated', Banner::class, $banner->id, $isNew ? null : $before, $banner->only(['title', 'banner_for', 'type', 'type_id', 'status', 'starts_at', 'ends_at']));
        return response()->json(['data' => $banner], $banner->wasRecentlyCreated ? 201 : 200);
    }

    private function validateCampaign(Request $request): array
    {
        $routeCampaign = $request->route('campaign');
        $ignoreId = $routeCampaign instanceof MarketingCampaign ? $routeCampaign->id : null;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'slug' => ['nullable', 'alpha_dash', 'max:150', Rule::unique('marketing_campaigns', 'slug')->ignore($ignoreId)],
            'kind' => ['required', Rule::in(['campaign', 'promotion', 'landing_page', 'featured_collection', 'notification'])],
            'channel' => ['nullable', Rule::in(['email', 'push', 'in_app'])],
            'audience_segment' => ['required', Rule::in(['all_consented', 'subscribers', 'registered', 'email_consented', 'push_consented'])],
            'subject' => ['nullable', 'string', 'max:191'], 'body' => ['required', 'string', 'max:20000'],
            'call_to_action' => ['nullable', 'string', 'max:80'], 'destination_url' => ['nullable', 'url', 'regex:/^https?:\/\//i', 'max:2048'],
            'coupon_id' => ['nullable', 'integer', 'exists:coupons,id'],
            'featured_content' => ['nullable', 'array', 'max:50'], 'featured_content.*' => ['integer', 'min:1'],
            'landing_page' => ['nullable', 'array'], 'landing_page.headline' => ['nullable', 'string', 'max:191'],
            'landing_page.description' => ['nullable', 'string', 'max:5000'], 'landing_page.hero_image' => ['nullable', 'url', 'regex:/^https?:\/\//i', 'max:2048'],
            'variant_b' => ['nullable', 'array'], 'variant_b.subject' => ['nullable', 'string', 'max:191'], 'variant_b.body' => ['nullable', 'string', 'max:20000'], 'variant_b.destination_url' => ['nullable', 'url', 'regex:/^https?:\/\//i', 'max:2048'],
            'variant_b_percent' => ['sometimes', 'integer', 'min:0', 'max:50'],
        ]);
        if (($data['kind'] ?? null) === 'notification') {
            $this->authorizeMarketing($request, 'marketing.notifications.manage');
            abort_unless(in_array($data['channel'] ?? null, ['email', 'push'], true), 422, 'Notification campaigns require an email or push channel.');
            if (($data['channel'] ?? null) === 'push') abort_unless(in_array($data['audience_segment'], ['all_consented', 'subscribers', 'registered', 'push_consented'], true), 422);
            if (($data['channel'] ?? null) === 'email') abort_unless(in_array($data['audience_segment'], ['all_consented', 'subscribers', 'registered', 'email_consented'], true), 422);
        }
        if (($data['kind'] ?? null) === 'promotion') {
            $this->authorizeMarketing($request, 'marketing.promotions.manage');
            abort_unless(!empty($data['coupon_id']), 422, 'Promotional campaigns must be linked to a coupon.');
        }
        if (($data['kind'] ?? null) === 'featured_collection') abort_unless(!empty($data['featured_content']), 422, 'Featured collections require at least one content item.');
        if (($data['variant_b_percent'] ?? 0) > 0) abort_unless(!empty($data['variant_b']['body']), 422, 'A B variant requires its own message body.');
        return $data;
    }

    private function recipientQuery(string $segment, string $channel)
    {
        return \App\Support\Marketing\CampaignAudience::query($segment, $channel);
    }

    private function authorizeMarketing(Request $request, string $permission): void
    {
        abort_unless(ExplicitPermission::allows($request->user(), $permission), 403);
    }

    private function assertCampaignEligible(MarketingCampaign $campaign): void
    {
        if ($campaign->kind === 'promotion') {
            $coupon = Coupon::query()->whereKey($campaign->coupon_id)->where('status', true)
                ->where(fn ($query) => $query->whereNull('start_date')->orWhereDate('start_date', '<=', today()))
                ->where(fn ($query) => $query->whereNull('expire_date')->orWhereDate('expire_date', '>=', today()))->exists();
            abort_unless($coupon, 422, 'The linked coupon is not currently eligible for publication.');
        }
        if ($campaign->kind === 'featured_collection') abort_unless(count($campaign->featured_content ?? []) > 0, 422);
        if ($campaign->kind === 'notification') {
            abort_unless(in_array($campaign->channel, ['email', 'push'], true), 422);
            abort_unless($campaign->body !== '', 422);
        }
    }
}
