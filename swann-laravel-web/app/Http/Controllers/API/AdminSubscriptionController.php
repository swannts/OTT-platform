<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Support\Rbac\AdminAuditLogger;
use App\Support\Rbac\ExplicitPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Subscriptions\Models\Plan;
use Modules\Subscriptions\Models\Subscription;
use Modules\Coupon\Models\Coupon;

class AdminSubscriptionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'subscriptions.view'), 403);
        $query = Plan::query()->with('planLimitation.limitation_data')->withCount('subscriptions');
        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(fn ($builder) => $builder->where('name', 'like', "%{$search}%")
                ->orWhere('identifier', 'like', "%{$search}%"));
        }
        if ($request->input('status') === 'active') $query->where('status', 1);
        if ($request->input('status') === 'archived') $query->where('status', 0);

        $page = $query->orderByDesc('updated_at')->paginate(min(max($request->integer('per_page', 25), 1), 100));
        $page->getCollection()->transform(function (Plan $plan): Plan {
            $plan->setAttribute('entitlements', $this->planLimits($plan));
            return $plan;
        });
        return response()->json($page);
    }

    public function store(Request $request, AdminAuditLogger $audit): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'subscriptions.plans.create'), 403);
        if ($request->exists('trial_days')) abort_unless(ExplicitPermission::allows($request->user(), 'subscriptions.trials.manage'), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'identifier' => ['sometimes', 'nullable', 'string', 'max:120', 'alpha_dash', 'unique:plan,identifier'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'level' => ['required', 'integer', 'min:0', 'max:10000'],
            'duration' => ['required', 'in:day,week,month,year'],
            'duration_value' => ['required', 'integer', 'min:1', 'max:120'],
            'trial_days' => ['sometimes', 'integer', 'min:0', 'max:90'],
            'limits' => ['sometimes', 'array'],
            'limits.device_limit' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
            'limits.profile_limit' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:50'],
            'limits.simultaneous_streams' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:10'],
            'limits.max_video_quality' => ['sometimes', 'nullable', 'integer', 'in:480,720,1080,1440,2160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['sometimes', 'boolean'],
        ]);
        $data['identifier'] = $data['identifier'] ?? Str::slug($data['name']).'-'.Str::lower(Str::random(6));
        $data['status'] = (bool) ($data['status'] ?? false);
        $limits = $data['limits'] ?? null;
        unset($data['limits']);
        $plan = DB::transaction(function () use ($request, $data, $limits, $audit): Plan {
            $plan = Plan::query()->create($data);
            $plan->forceFill(['created_by' => $request->user()->getKey()])->save();
            if ($limits !== null) $this->syncPlanLimits($plan, $limits);
            $plan->load('planLimitation.limitation_data');
            $plan->setAttribute('entitlements', $this->planLimits($plan));
            $audit->record($request->user(), 'subscription.plan.created', 'plan', (int) $plan->id, null, $this->snapshot($plan));
            return $plan;
        });

        return response()->json(['data' => $plan], 201);
    }

    public function update(Request $request, Plan $plan, AdminAuditLogger $audit): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'subscriptions.plans.update'), 403);
        if ($request->exists('trial_days')) abort_unless(ExplicitPermission::allows($request->user(), 'subscriptions.trials.manage'), 403);
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'identifier' => ['sometimes', 'required', 'string', 'max:120', 'alpha_dash', 'unique:plan,identifier,'.$plan->id],
            'price' => ['sometimes', 'required', 'numeric', 'min:0', 'max:99999999'],
            'level' => ['sometimes', 'required', 'integer', 'min:0', 'max:10000'],
            'duration' => ['sometimes', 'required', 'in:day,week,month,year'],
            'duration_value' => ['sometimes', 'required', 'integer', 'min:1', 'max:120'],
            'trial_days' => ['sometimes', 'integer', 'min:0', 'max:90'],
            'limits' => ['sometimes', 'array'],
            'limits.device_limit' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
            'limits.profile_limit' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:50'],
            'limits.simultaneous_streams' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:10'],
            'limits.max_video_quality' => ['sometimes', 'nullable', 'integer', 'in:480,720,1080,1440,2160'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);
        $limits = $data['limits'] ?? null;
        unset($data['limits']);
        $plan = DB::transaction(function () use ($request, $data, $limits, $plan, $audit): Plan {
            $locked = Plan::query()->lockForUpdate()->findOrFail($plan->id);
            $before = $this->snapshot($locked);
            $locked->fill($data);
            $locked->forceFill(['updated_by' => $request->user()->getKey()])->save();
            if ($limits !== null) $this->syncPlanLimits($locked, $limits);
            $audit->record($request->user(), 'subscription.plan.updated', 'plan', (int) $locked->id, $before, $this->snapshot($locked));
            return $locked;
        });

        $fresh = $plan->fresh()->load('planLimitation.limitation_data');
        $fresh->setAttribute('entitlements', $this->planLimits($fresh));
        return response()->json(['data' => $fresh]);
    }

    public function archive(Request $request, Plan $plan, AdminAuditLogger $audit): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'subscriptions.plans.archive'), 403);
        abort_if(!$plan->status, 409, 'This plan is already archived.');
        $before = $this->snapshot($plan);
        $plan->forceFill(['status' => false, 'updated_by' => $request->user()->getKey()])->save();
        $audit->record($request->user(), 'subscription.plan.archived', 'plan', (int) $plan->id, $before, $this->snapshot($plan));

        return response()->json(['data' => $plan->fresh(), 'message' => 'Plan archived. Existing subscription snapshots are preserved.']);
    }

    public function subscribers(Request $request): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'subscriptions.subscribers.view'), 403);
        $query = Subscription::query()->with('plan:id,name,identifier')
            ->select(['id', 'user_id', 'plan_id', 'name', 'identifier', 'status', 'start_date', 'end_date', 'amount', 'level'])
            ->latest('id');
        if ($request->filled('status')) $query->where('status', $request->string('status')->toString());
        if ($request->filled('plan_id')) $query->where('plan_id', $request->integer('plan_id'));
        $page = $query->paginate(min(max($request->integer('per_page', 25), 1), 100));

        return response()->json($page);
    }

    public function performance(Request $request): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'subscriptions.view'), 403);

        $plans = Plan::query()->withCount([
            'subscriptions as active_subscribers_count' => fn ($query) => $query->where('status', 'active'),
        ])->orderBy('level')->get(['id', 'name', 'identifier', 'price', 'duration', 'duration_value', 'level', 'status']);

        $revenueByPlan = [];
        if (Schema::hasTable('subscriptions_transactions')) {
            $revenueByPlan = DB::table('subscriptions_transactions as transactions')
                ->join('subscriptions', 'subscriptions.id', '=', 'transactions.subscriptions_id')
                ->where('transactions.payment_status', 'paid')
                ->whereNull('transactions.deleted_at')
                ->whereNull('subscriptions.deleted_at')
                ->select('subscriptions.plan_id', 'transactions.currency_code', DB::raw('COUNT(transactions.id) as paid_transactions'), DB::raw('SUM(transactions.amount) as gross_revenue'))
                ->groupBy('subscriptions.plan_id', 'transactions.currency_code')->get()->groupBy('plan_id');
        }

        return response()->json(['data' => $plans->map(function (Plan $plan) use ($revenueByPlan): array {
            $currencyGroups = $revenueByPlan[$plan->id] ?? collect();
            $revenueByCurrency = $currencyGroups->mapWithKeys(fn ($row) => [($row->currency_code ?: 'UNRECORDED') => round((float) $row->gross_revenue, 2)]);
            return [
                'id' => $plan->id, 'name' => $plan->name, 'identifier' => $plan->identifier,
                'price' => $plan->price, 'duration' => $plan->duration, 'duration_value' => $plan->duration_value,
                'level' => $plan->level, 'status' => (bool) $plan->status,
                'active_subscribers' => (int) $plan->active_subscribers_count,
                'paid_transactions' => (int) $currencyGroups->sum('paid_transactions'),
                'revenue_by_currency' => $revenueByCurrency,
            ];
        })]);
    }

    public function simulate(Request $request): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'subscriptions.view'), 403);
        $data = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plan,id'],
            'required_plan_level' => ['required', 'integer', 'min:0'],
            'profile_count' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'device_count' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'stream_count' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'requested_video_quality' => ['sometimes', 'integer', 'in:480,720,1080,1440,2160'],
        ]);
        $plan = Plan::query()->with('planLimitation.limitation_data')->findOrFail($data['plan_id']);
        $limits = collect($plan->planLimitation)->keyBy(fn ($item) => $item->limitation_slug ?: $item->limitation_data?->slug);
        $limitValue = static function (string $slug) use ($limits): ?int {
            $entry = $limits->get($slug);
            if (!$entry || !(bool) $entry->limitation_value) return null;
            $value = json_decode((string) $entry->limit, true);
            return is_array($value) ? (int) ($value['value'] ?? 0) : (is_numeric($value) ? (int) $value : null);
        };
        $levelOk = $plan->status && (int) $plan->level >= (int) $data['required_plan_level'];
        $checks = [
            'plan_active' => (bool) $plan->status,
            'content_plan_level' => $levelOk,
            'profiles' => (int) ($data['profile_count'] ?? 1) <= ($limitValue('profile-limit') ?? 1),
            'devices' => (int) ($data['device_count'] ?? 1) <= ($limitValue('device-limit') ?? 1),
            'simultaneous_streams' => (int) ($data['stream_count'] ?? 1) <= ($limitValue('simultaneous-streams') ?? 1),
            'video_quality' => $limitValue('max-video-quality') === null || (int) ($data['requested_video_quality'] ?? 720) <= $limitValue('max-video-quality'),
        ];

        return response()->json(['data' => ['eligible' => !in_array(false, $checks, true), 'checks' => $checks]]);
    }

    public function coupons(Request $request): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'subscriptions.coupons.manage'), 403);
        $query = Coupon::query()->with('subscriptionPlans:id,name,identifier')->latest('id')
            ->addSelect(['redemptions_count' => DB::table('user_coupon_redeem')->selectRaw('COUNT(*)')->whereColumn('coupon_id', 'coupons.id')])
            ->addSelect(['pending_reservations_count' => DB::table('subscription_coupon_reservations')->selectRaw('COUNT(*)')->whereColumn('coupon_id', 'coupons.id')->where('status', 'pending')->where('expires_at', '>', now())]);
        if ($request->filled('search')) $query->where('code', 'like', '%'.trim((string) $request->input('search')).'%');
        if ($request->input('status') === 'active') $query->where('status', 1);
        if ($request->input('status') === 'archived') $query->where('status', 0);
        return response()->json($query->paginate(min(max($request->integer('per_page', 25), 1), 100)));
    }

    public function storeCoupon(Request $request, AdminAuditLogger $audit): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'subscriptions.coupons.manage'), 403);
        $data = $this->validateCoupon($request);
        $coupon = DB::transaction(function () use ($request, $data, $audit): Coupon {
            $plans = $data['subscription_plan_ids'];
            unset($data['subscription_plan_ids']);
            $coupon = Coupon::query()->create($data);
            $coupon->forceFill(['created_by' => $request->user()->getKey()])->save();
            $coupon->subscriptionPlans()->sync($plans);
            $audit->record($request->user(), 'subscription.coupon.created', 'coupon', (int) $coupon->id, null, $coupon->only(['code', 'discount', 'discount_type', 'max_redemptions', 'per_user_limit', 'status']));
            return $coupon;
        });
        return response()->json(['data' => $coupon->load('subscriptionPlans:id,name,identifier')], 201);
    }

    public function updateCoupon(Request $request, Coupon $coupon, AdminAuditLogger $audit): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'subscriptions.coupons.manage'), 403);
        $data = $this->validateCoupon($request, (int) $coupon->id, true);
        $coupon = DB::transaction(function () use ($request, $data, $coupon, $audit): Coupon {
            $locked = Coupon::query()->lockForUpdate()->findOrFail($coupon->id);
            $before = $locked->only(['code', 'discount', 'discount_type', 'max_redemptions', 'per_user_limit', 'start_date', 'expire_date', 'status']);
            $plans = $data['subscription_plan_ids'] ?? null;
            unset($data['subscription_plan_ids']);
            $locked->fill($data);
            $locked->forceFill(['updated_by' => $request->user()->getKey()])->save();
            if ($plans !== null) $locked->subscriptionPlans()->sync($plans);
            $audit->record($request->user(), 'subscription.coupon.updated', 'coupon', (int) $locked->id, $before, $locked->only(['code', 'discount', 'discount_type', 'max_redemptions', 'per_user_limit', 'start_date', 'expire_date', 'status']));
            return $locked;
        });
        return response()->json(['data' => $coupon->load('subscriptionPlans:id,name,identifier')]);
    }

    public function archiveCoupon(Request $request, Coupon $coupon, AdminAuditLogger $audit): JsonResponse
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'subscriptions.coupons.manage'), 403);
        abort_if(!$coupon->status, 409, 'This coupon is already inactive.');
        $before = $coupon->only(['code', 'discount', 'discount_type', 'status']);
        $coupon->forceFill(['status' => false, 'updated_by' => $request->user()->getKey()])->save();
        $audit->record($request->user(), 'subscription.coupon.archived', 'coupon', (int) $coupon->id, $before, $coupon->only(['code', 'discount', 'discount_type', 'status']));
        return response()->json(['data' => $coupon->fresh(), 'message' => 'Coupon deactivated; redemption history is retained.']);
    }

    private function validateCoupon(Request $request, ?int $couponId = null, bool $partial = false): array
    {
        $required = $partial ? ['sometimes', 'required'] : ['required'];
        $data = $request->validate([
            'code' => [...$required, 'string', 'max:64', 'alpha_dash', 'unique:coupons,code'.($couponId ? ','.$couponId : '')],
            'discount' => [...$required, 'numeric', 'min:0'],
            'discount_type' => [...$required, 'in:fixed,percentage'],
            'max_redemptions' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:10000000'],
            'per_user_limit' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:1000'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'expire_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'status' => ['sometimes', 'boolean'],
            'subscription_plan_ids' => [...($partial ? ['sometimes'] : ['required']), 'array', 'min:1'],
            'subscription_plan_ids.*' => ['integer', 'distinct', 'exists:plan,id'],
        ]);
        $discountType = $data['discount_type'] ?? ($couponId ? Coupon::query()->whereKey($couponId)->value('discount_type') : null);
        if ($discountType === 'percentage' && isset($data['discount'])) {
            validator($data, ['discount' => ['numeric', 'min:0', 'max:100']])->validate();
        }
        if (!$partial) {
            $data['status'] = (bool) ($data['status'] ?? false);
            $data['start_date'] = $data['start_date'] ?? now()->toDateString();
        }
        return $data;
    }

    private function snapshot(Plan $plan): array
    {
        return array_merge($plan->only(['name', 'identifier', 'price', 'level', 'duration', 'duration_value', 'trial_days', 'description', 'status']), [
            'entitlements' => $this->planLimits($plan),
        ]);
    }

    private function planLimits(Plan $plan): array
    {
        $limits = [];
        foreach ($plan->planLimitation as $mapping) {
            $slug = $mapping->limitation_slug ?: $mapping->limitation_data?->slug;
            if (!$slug) continue;
            $value = $mapping->limit;
            $decoded = is_string($value) ? json_decode($value, true) : null;
            if (is_array($decoded)) $value = $decoded['value'] ?? $decoded;
            $limits[$slug] = (int) $mapping->limitation_value === 1 && is_numeric($value) ? (int) $value : null;
        }
        return $limits;
    }

    private function syncPlanLimits(Plan $plan, array $limits): void
    {
        $fields = [
            'device_limit' => 'device-limit',
            'profile_limit' => 'profile-limit',
            'simultaneous_streams' => 'simultaneous-streams',
            'max_video_quality' => 'max-video-quality',
        ];
        foreach ($fields as $input => $slug) {
            if (!array_key_exists($input, $limits)) continue;
            $definition = DB::table('planlimitation')->where('slug', $slug)->whereNull('deleted_at')->first();
            abort_unless($definition, 409, "Plan limitation {$slug} is not installed.");
            $value = $limits[$input] === null ? null : (int) $limits[$input];
            DB::table('planlimitation_mapping')->updateOrInsert(
                ['plan_id' => $plan->id, 'planlimitation_id' => $definition->id],
                [
                    'limitation_slug' => $slug,
                    'limitation_value' => $value === null ? 0 : 1,
                    'limit' => $value === null ? null : (string) $value,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
