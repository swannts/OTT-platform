<?php

namespace Modules\Entertainment\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;

class PlaybackDetailsRequest extends FormRequest
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
            'id' => ['required', 'string', 'max:64'],
            'type' => ['required', 'in:movie,tvshow,episode,video'],
            'profile_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
