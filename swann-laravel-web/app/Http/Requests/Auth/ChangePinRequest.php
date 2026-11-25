<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ChangePinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_array($this->pin)) {
            $this->merge(['pin' => implode('', $this->pin)]);
        }
        if (is_array($this->confirm_pin)) {
            $this->merge(['confirm_pin' => implode('', $this->confirm_pin)]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'pin' => 'required|digits:4',
            'confirm_pin' => 'required_with:pin|same:pin|digits:4',
        ];
    }
}
