<?php

namespace App\Http\Requests\Sppg;

use Illuminate\Foundation\Http\FormRequest;

class CancelForecastRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSppgUser()
            && $this->user()?->hasValidIdentityContext();
    }

    public function rules(): array
    {
        return [
            'cancellation_reason' => [
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