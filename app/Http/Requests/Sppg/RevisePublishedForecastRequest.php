<?php

namespace App\Http\Requests\Sppg;

use Illuminate\Foundation\Http\FormRequest;

class RevisePublishedForecastRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSppgUser()
            && $this->user()?->hasValidIdentityContext();
    }

    public function rules(): array
    {
        return [
            'target_volume' => [
                'nullable',
                'numeric',
                'gt:0',
            ],

            'required_start_at' => [
                'nullable',
                'date',
            ],

            'required_end_at' => [
                'nullable',
                'date',
            ],

            'reason' => [
                'required',
                'string',
                'max:2000',
            ],

            'version' => [
                'required',
                'integer',
                'min:1',
            ],
        ];
    }
}