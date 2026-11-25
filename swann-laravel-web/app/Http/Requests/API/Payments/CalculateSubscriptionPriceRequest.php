<?php

namespace App\Http\Requests\API\Payments;

use Illuminate\Foundation\Http\FormRequest;

class CalculateSubscriptionPriceRequest extends FormRequest
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
            'coupon_code' => 'nullable|string',
            'coupon_id' => 'nullable|integer',
        ];
    }
}
