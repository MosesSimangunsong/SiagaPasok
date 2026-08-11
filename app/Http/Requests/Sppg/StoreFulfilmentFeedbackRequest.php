<?php

namespace App\Http\Requests\Sppg;

use Illuminate\Foundation\Http\FormRequest;

final class StoreFulfilmentFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return
            $this->user()?->isSppgUser()
            && $this->user()
                ?->hasValidIdentityContext();
    }

    public function rules(): array
    {
        return [
            'delivered_volume' => [
                'required',
                'string',
                'regex:/^\d+(?:\.\d{1,6})?$/',
            ],

            'fulfilment_date' => [
                'required',
                'date',
            ],

            'reason_note' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'delivered_volume.regex' =>
                (
                    'Delivered volume harus berupa '
                    .'angka non-negatif dengan maksimal '
                    .'6 digit pecahan.'
                ),
        ];
    }
}