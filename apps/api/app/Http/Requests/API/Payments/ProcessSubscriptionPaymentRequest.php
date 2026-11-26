<?php

namespace App\Http\Requests\API\Payments;

use Illuminate\Foundation\Http\FormRequest;

class ProcessSubscriptionPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'plan_id' => 'required|exists:plan,id',
            'payment_method' => 'required|string|in:stripe,razorpay,paystack,free',
            'coupon_code' => 'nullable|string',
            'coupon_id' => 'nullable|integer',
        ];
    }
}
