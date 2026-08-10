<?php

namespace App\Http\Requests\Sppg;

use Illuminate\Foundation\Http\FormRequest;

class ForecastVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSppgUser()
            && $this->user()?->hasValidIdentityContext();
    }

    public function rules(): array
    {
        return [
            'version' => [
                'required',
                'integer',
                'min:1',
            ],
        ];
    }
}