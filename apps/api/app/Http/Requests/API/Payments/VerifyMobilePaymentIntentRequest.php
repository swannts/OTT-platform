<?php

namespace App\Http\Requests\API\Payments;

use Illuminate\Foundation\Http\FormRequest;

class VerifyMobilePaymentIntentRequest extends FormRequest
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
            'payment_intent_id' => 'required|string|max:191',
        ];
    }
}
