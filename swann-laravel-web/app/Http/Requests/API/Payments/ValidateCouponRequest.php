<?php

namespace App\Http\Requests\API\Payments;

use Illuminate\Foundation\Http\FormRequest;

class ValidateCouponRequest extends FormRequest
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
            'coupon_code' => 'required|string',
            'plan_id' => 'nullable|integer',
            'content_id' => 'nullable|integer',
            'content_type' => 'nullable|string',
        ];
    }
}
