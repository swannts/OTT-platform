<?php

namespace App\Http\Requests\API\Payments;

use Illuminate\Foundation\Http\FormRequest;

class ProcessPayPerViewPaymentRequest extends FormRequest
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
            'movie_id' => 'required|integer',
            'type' => 'required|string|in:movie,tvshow,video,episode',
            'payment_method' => 'required|string|in:stripe,razorpay,free',
        ];
    }
}
