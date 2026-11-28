<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MarketingCampaign;
use App\Models\MarketingConsent;
use App\Support\Rbac\AdminAuditLogger;
use App\Support\Rbac\ExplicitPermission;
use App\Services\Marketing\HomepageSectionContentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Banner\Models\Banner;
use Modules\Coupon\Models\Coupon;

class AdminMarketingController extends Controller
{
    private const HOMEPAGE_SECTIONS = ['latest-movies', 'live-now', 'top-10', 'popular-tvshows', 'popular-movies', 'pay-per-view'];
    private const HOMEPAGE_SECTION_TITLES = [
        'latest-movies' => ['Trending Now', 'এখন ট্রেন্ডিং'],
        'live-now' => ['Live Now', 'সরাসরি সম্প্রচার'],
        'top-10' => ['Top 10 Today', 'আজকের শীর্ষ ১০'],
        'popular-tvshows' => ['Popular TV Shows & Series', 'জনপ্রিয় টিভি শো ও সিরিজ'],
        'popular-movies' => ['Popular Movies', 'জনপ্রিয় সিনেমা'],
        'pay-per-view' => ['Pay-Per-View Exclusives', 'পে-পার-ভিউ এক্সক্লুসিভ'],
    ];

    public function homepageConfig(): JsonResponse
    {
        $config = $this->readHomepageConfig();
        return response()->json(['data' => $config + ['latest_movies' => $this->resolveHomepageMovies($config)]]);
    }

    public function homepageCatalog(Request $request): JsonResponse
    {
        $this->authorizeMarketing($request, 'marketing.banners.manage');
        $filters = $request->validate(['search' => ['sometimes', 'nullable', 'string', 'max:120'], 'page' => ['sometimes', 'integer', 'min:1']]);
        $query = DB::table('entertainments')->where('type', 'movie')->where('status', 1)->whereNull('deleted_at')->whereDate('release_date', '<=', today());
        if (!empty($filters['search'])) {
            $term = '%'.addcslashes($filters['search'], '%_\\').'%';
            $query->where(fn ($builder) => $builder->where('name', 'like', $term)->orWhere('name_bn', 'like', $term)->orWhere('slug', 'like', $term));
        }
        $page = $query->orderByDesc('release_date')->orderByDesc('id')->paginate(20);
        $page->getCollection()->transform(fn ($movie) => [
            'id' => (string) $movie->id,
            'name' => $movie->name,
            'name_bn' => $movie->name_bn,
            'poster_image' => $movie->poster_url ?: $movie->thumbnail_url ?: $movie->poster_tv_url,
            'release_date' => $movie->release_date,
        ]);
        return response()->json(['data' => $page]);
    }

    public function updateHomepageConfig(Request $request): JsonResponse
    {
        $this->authorizeMarketing($request, 'marketing.banners.manage');
        $data = $request->validate([
            'sections' => ['required', 'array', 'size:6'],
            'sections.*.key' => ['required', 'string', Rule::in(self::HOMEPAGE_SECTIONS), 'distinct'],
            'sections.*.enabled' => ['required', 'boolean'],
            'sections.*.title' => ['nullable', 'string', 'max:80'],
            'sections.*.title_bn' => ['nullable', 'string', 'max:80'],
            'sections.*.content_ids' => ['nullable', 'array', 'max:30'],
            'sections.*.content_ids.*' => ['string', 'distinct', Rule::exists('entertainments', 'id')->where(fn ($query) => $query->where('type', 'movie')->where('status', 1)->whereNull('deleted_at'))],
            'premium_card.enabled' => ['required', 'boolean'],
            'premium_card.title' => ['required', 'string', 'max:80'],
            'premium_card.description' => ['required', 'string', 'max:240'],
            'premium_card.image_url' => ['nullable', 'url', 'max:2048'],
            'premium_card.cta' => ['required', 'string', 'max:60'],
            'premium_card.url' => ['required', 'string', 'regex:/^(\/(?!\/)|https?:\/\/)/i', 'max:2048'],
        ]);
        abort_unless(count(array_unique(array_column($data['sections'], 'key'))) === count(self::HOMEPAGE_SECTIONS), 422, 'Homepage sections must contain every supported section exactly once.');

        $before = $this->readHomepageConfig();
        DB::table('web_homepage_configs')->updateOrInsert(
            ['id' => 1],
            ['config' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'updated_by' => $request->user()->id, 'updated_at' => now()],
        );
        app(AdminAuditLogger::class)->record($request->user(), 'marketing.homepage.updated', 'homepage', 1, $before, $data);

        return response()->json(['data' => $data]);
    }

    public function publicHomepageConfig(): JsonResponse
    {
        $config = $this->readHomepageConfig();
        $latestMovies = $this->resolveHomepageMovies($config);
        $config['sections'] = app(HomepageSectionContentService::class)->hydrate($config['sections'], $latestMovies);

        return response()->json(['data' => $config + ['latest_movies' => $latestMovies]]);
    }

    private function readHomepageConfig(): array
    {
        $defaults = [
            'sections' => array_map(fn (string $key): array => [
                'key' => $key,
                'enabled' => true,
                'title' => self::HOMEPAGE_SECTION_TITLES[$key][0],
                'title_bn' => self::HOMEPAGE_SECTION_TITLES[$key][1],
                'content_ids' => null,
            ], self::HOMEPAGE_SECTIONS),
            'premium_card' => ['enabled' => true, 'title' => 'Go Premium', 'description' => 'Unlock exclusive content, ad-free experience and more.', 'image_url' => '', 'cta' => 'Upgrade Now', 'url' => '/pricing'],
        ];
        $value = DB::table('web_homepage_configs')->where('id', 1)->value('config');
        $config = is_string($value) ? json_decode($value, true) : null;
        if (!is_array($config) || !is_array($config['sections'] ?? null) || !is_array($config['premium_card'] ?? null)) return $defaults;
        $byKey = collect($config['sections'])->keyBy('key');
        $orderedKeys = collect($config['sections'])->pluck('key')->filter(fn ($key) => in_array($key, self::HOMEPAGE_SECTIONS, true))->unique()->values();
        $orderedKeys = $orderedKeys->concat(collect(self::HOMEPAGE_SECTIONS)->diff($orderedKeys))->values();
        $defaults['sections'] = $orderedKeys->map(function (string $key) use ($byKey): array {
            $section = $byKey->get($key, []);
            return ['key' => $key, 'enabled' => (bool) ($section['enabled'] ?? true), 'title' => (string) ($section['title'] ?? ''), 'title_bn' => (string) ($section['title_bn'] ?? ''), 'content_ids' => $key === 'latest-movies' && is_array($section['content_ids'] ?? null) ? array_values($section['content_ids']) : null];
        })->all();
        $defaults['premium_card'] = array_replace($defaults['premium_card'], array_intersect_key($config['premium_card'], $defaults['premium_card']));
        return $defaults;
    }

    private function resolveHomepageMovies(array $config): array
    {
        $section = collect($config['sections'])->firstWhere('key', 'latest-movies');
        $ids = $section['content_ids'] ?? null;
        if (!is_array($ids)) {
            $legacy = DB::table('mobile_settings')->where('slug', 'latest-movies')->whereNull('deleted_at')->first(['value', 'type']);
            $ids = $legacy && empty($legacy->type) ? json_decode((string) $legacy->value, true) : [];
        }
        if (!is_array($ids) || $ids === []) return [];
        $movies = DB::table('entertainments')->select(['id', 'name', 'name_bn', 'slug', 'poster_url', 'thumbnail_url', 'poster_tv_url', 'release_date', 'imdb_rating', 'movie_access', 'type'])
            ->whereIn('id', $ids)->where('type', 'movie')->where('status', 1)->whereNull('deleted_at')->whereDate('release_date', '<=', today())->get()->keyBy(fn ($movie) => (string) $movie->id);
        return collect($ids)->map(function ($id) use ($movies): ?array {
            $movie = $movies->get((string) $id);
            if (!$movie) return null;
            return [
                'id' => (string) $movie->id, 'name' => $movie->name, 'name_bn' => $movie->name_bn,
                'title' => $movie->name, 'type' => 'movie', 'slug' => $movie->slug,
                'poster_image' => $movie->poster_url ?: $movie->thumbnail_url ?: $movie->poster_tv_url,
                'poster_url' => $movie->poster_url, 'thumbnail_url' => $movie->thumbnail_url,
                'release_date' => $movie->release_date, 'imdb_rating' => $movie->imdb_rating,
                'access' => $movie->movie_access,
            ];
        })->filter()->values()->all();
    }

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
        return response()->json(Banner::query()->latest()->paginate(min(max($request->integer('per_page', 50), 1), 100)));
    }

    public function uploadBannerArtwork(Request $request): JsonResponse
    {
        $this->authorizeMarketing($request, 'marketing.banners.manage');
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240', 'dimensions:max_width=5000,max_height=5000'],
        ]);

        $path = $request->file('image')->storePublicly('marketing/banners', 'public');
        abort_if(! $path, 500, 'The banner artwork could not be stored.');

        return response()->json(['data' => ['url' => Storage::disk('public')->url($path)]], 201);
    }

    public function saveBanner(Request $request, ?Banner $banner = null): JsonResponse
    {
        $this->authorizeMarketing($request, 'marketing.banners.manage');
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'], 'banner_for' => ['required', Rule::in(['home', 'movie', 'tv_show', 'livetv', 'video', 'promotional'])],
            'type' => ['nullable', Rule::in(['movie', 'tvshow', 'livetv', 'video'])], 'type_id' => ['nullable', 'string', 'max:191'],
            'type_name' => ['nullable', 'string', 'max:191'], 'type_name_bn' => ['nullable', 'string', 'max:191'], 'file_url' => ['nullable', 'string', 'max:2048'], 'poster_url' => ['nullable', 'string', 'max:2048'],
            'description' => ['nullable', 'string', 'max:5000'], 'description_bn' => ['nullable', 'string', 'max:5000'],
            'click_url' => ['nullable', 'url', 'regex:/^https?:\/\//i', 'max:2048'],
            'status' => ['required', 'boolean'], 'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ]);
        $banner ??= new Banner();
        $isNew = !$banner->exists;
        $before = $banner->only(['title', 'banner_for', 'type', 'type_id', 'type_name', 'type_name_bn', 'status', 'starts_at', 'ends_at']);
        $banner->fill($data + ['created_by' => $request->user()->id]);
        $banner->save();
        clearRelatedCache(['banner_list'], 'banner');
        app(AdminAuditLogger::class)->record($request->user(), $isNew ? 'marketing.banner.created' : 'marketing.banner.updated', Banner::class, $banner->id, $isNew ? null : $before, $banner->only(['title', 'banner_for', 'type', 'type_id', 'type_name', 'type_name_bn', 'status', 'starts_at', 'ends_at']));
        return response()->json(['data' => $banner], $banner->wasRecentlyCreated ? 201 : 200);
    }

    public function deleteBanner(Request $request, Banner $banner): JsonResponse
    {
        $this->authorizeMarketing($request, 'marketing.banners.manage');
        $before = $banner->only(['title', 'banner_for', 'type', 'type_id', 'type_name', 'type_name_bn', 'status', 'starts_at', 'ends_at']);
        $banner->delete();
        clearRelatedCache(['banner_list'], 'banner');
        app(AdminAuditLogger::class)->record($request->user(), 'marketing.banner.deleted', Banner::class, $banner->id, $before, ['deleted' => true]);

        return response()->json(['data' => ['deleted' => true, 'id' => $banner->id]]);
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
