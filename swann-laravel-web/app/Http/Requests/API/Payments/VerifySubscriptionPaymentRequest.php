<?php

namespace App\Http\Requests\API\Payments;

use Illuminate\Foundation\Http\FormRequest;

class VerifySubscriptionPaymentRequest extends FormRequest
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
            'gateway' => 'required|string|in:stripe,razorpay,paystack',
            'session_id' => 'required_if:gateway,stripe|nullable|string|max:191',
            'payment_id' => 'required_if:gateway,razorpay|nullable|string|max:191',
            'order_id' => 'required_if:gateway,razorpay|nullable|string|max:191',
            'signature' => 'required_if:gateway,razorpay|nullable|string|max:512',
            'reference' => 'required_if:gateway,paystack|nullable|string|max:191',
        ];
    }
}
