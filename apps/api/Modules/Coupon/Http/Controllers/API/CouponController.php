<?php

namespace Modules\Coupon\Http\Controllers\API;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Http\Responses\ApiResponse;
use App\Support\Rbac\ExplicitPermission;
use Modules\Coupon\Models\Coupon;

class CouponController extends Controller
{
    /**
     * Display a listing of the coupons.
     */
    public function index(Request $request)
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'subscriptions.coupons.manage'), 403);
        $coupons = Coupon::query()->with('subscriptionPlans:id,name,identifier')->paginate(50);
        return ApiResponse::custom(['success' => true, 'data' => $coupons], 200);
    }

    /**
     * Store a newly created coupon in storage.
     */
    public function store(Request $request)
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'subscriptions.coupons.manage'), 403);
        $validated = $request->validate([
            'code' => 'required|string|max:64|alpha_dash|unique:coupons,code',
            'discount' => 'required|numeric|min:0',
            'discount_type' => 'required|in:fixed,percentage',
            'expire_date' => 'nullable|date|after_or_equal:start_date',
            'start_date' => 'nullable|date',
            'description' => 'nullable|string|max:2000',
            'status' => 'nullable|boolean',
            'subscription_plan_ids' => 'required|array|min:1',
            'subscription_plan_ids.*' => 'integer|distinct|exists:plan,id',
        ]);
        if ($validated['discount_type'] === 'percentage') {
            validator($validated, ['discount' => ['numeric', 'min:0', 'max:100']])->validate();
        }
        $coupon = new Coupon($validated);
        $coupon->forceFill(['created_by' => $request->user()->getKey()])->save();
    
        if ($request->filled('subscription_plan_ids')) {
            $coupon->subscriptionPlans()->attach($request->subscription_plan_ids);
        }
    
        return ApiResponse::success($coupon->load('subscriptionPlans'), __('messages.coupon_created_successfully'), 201);
    }

    /**
     * Display the specified coupon.
     */
    public function couponlist(Request $request)
    {
        // Validate incoming request
        $request->validate([
            'plan_id' => 'nullable|integer|exists:plan,id',
            'coupon_code' => 'nullable|string',
            'per_page' => 'nullable|integer'
        ]);   
    
        $today = now()->toDateString();
    
        $coupons = Coupon::query()
            ->where('status', 1)
            ->whereDate('start_date', '<=', $today) 
            ->where(function($query) use ($today) {
                $query->whereDate('expire_date', '>=', $today) 
                      ->orWhereNull('expire_date');           
            })
            ->orderBy('start_date', 'asc'); 
        if ($request->filled('plan_id')) {
            $coupons->whereHas('subscriptionPlans', function ($query) use ($request) {
                $query->where('subscription_plan_id', $request->plan_id);
            });
        }
        
        if ($request->filled('coupon_code')) {
            $coupons->where('code', 'like', '%' . $request->coupon_code . '%');
        }

        $perPage = $request->get('per_page', 10);

        $result = $coupons->paginate($perPage);
        
        $result->getCollection()->transform(function ($coupon) {
            $coupon->discount = (float) $coupon->discount;
            return $coupon;
        });
        
        return ApiResponse::success(
            $result->items(),
            null,
            200,
            [
                'pagination' => [
                    'current_page' => $result->currentPage(),
                    'per_page' => $result->perPage(),
                    'total' => $result->total(),
                    'last_page' => $result->lastPage(),
                    'next_page_url' => $result->nextPageUrl(),
                    'prev_page_url' => $result->previousPageUrl(),
                ]
            ]
        );
    }
    
    /**
     * Update the specified coupon in storage.
     */
    public function update(Request $request, int $id)
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'subscriptions.coupons.manage'), 403);
        $coupon = Coupon::find($id);

        if (!$coupon) {
            return ApiResponse::custom(['success' => false, 'message' => 'Coupon not found'], 404);
        }

        $validated = $request->validate([
            'code' => 'sometimes|required|string|max:64|alpha_dash|unique:coupons,code,' . $id,
            'discount' => 'sometimes|required|numeric|min:0',
            'discount_type' => 'sometimes|required|in:fixed,percentage',
            'start_date' => 'sometimes|nullable|date',
            'expire_date' => 'sometimes|nullable|date|after_or_equal:start_date',
            'description' => 'sometimes|nullable|string|max:2000',
            'status' => 'sometimes|boolean',
            'subscription_plan_ids' => 'sometimes|array|min:1',
            'subscription_plan_ids.*' => 'integer|distinct|exists:plan,id',
        ]);
        if (($validated['discount_type'] ?? $coupon->discount_type) === 'percentage' && isset($validated['discount'])) {
            validator($validated, ['discount' => ['numeric', 'min:0', 'max:100']])->validate();
        }
        $coupon->fill(collect($validated)->except('subscription_plan_ids')->all());
        $coupon->forceFill(['updated_by' => $request->user()->getKey()])->save();
        if (array_key_exists('subscription_plan_ids', $validated)) $coupon->subscriptionPlans()->sync($validated['subscription_plan_ids']);
        return ApiResponse::custom(['success' => true, 'data' => $coupon], 200);
    }

    /**
     * Remove the specified coupon from storage.
     */
    public function destroy(Request $request, int $id)
    {
        abort_unless(ExplicitPermission::allows($request->user(), 'subscriptions.coupons.manage'), 403);
        $coupon = Coupon::find($id);

        if (!$coupon) {
            return ApiResponse::custom(['success' => false, 'message' => 'Coupon not found'], 404);
        }

        $coupon->delete();
        return ApiResponse::custom(['success' => true, 'message' => 'Coupon deleted successfully'], 200);
    }

    /**
     * Display a filtered list of coupons based on plan_id or coupon_code.
     */
    
}
