<?php

namespace App\Http\Requests\API\Payments;

use Illuminate\Foundation\Http\FormRequest;

class CreateMobilePaymentIntentRequest extends FormRequest
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
            'purchase_type' => 'required|string|in:subscription,pay_per_view',
            'plan_id' => 'required_if:purchase_type,subscription|nullable|integer',
            'movie_id' => 'required_if:purchase_type,pay_per_view|nullable|integer',
            'type' => 'required_if:purchase_type,pay_per_view|nullable|string|in:movie,tvshow,video,episode,season',
            'coupon_code' => 'nullable|string',
            'coupon_id' => 'nullable|integer',
        ];
    }
}
