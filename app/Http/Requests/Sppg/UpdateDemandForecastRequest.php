<?php

namespace App\Http\Requests\Sppg;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDemandForecastRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSppgUser()
            && $this->user()?->hasValidIdentityContext();
    }

    public function rules(): array
    {
        return [
            'commodity_id' => [
                'required',
                'integer',
                Rule::exists('commodities', 'id')
                    ->where('is_active', true),
            ],

            'unit_id' => [
                'required',
                'integer',
                Rule::exists('units', 'id')
                    ->where('is_active', true),
            ],

            'target_volume' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'required_start_at' => [
                'required',
                'date',
            ],

            'required_end_at' => [
                'required',
                'date',
                'after_or_equal:required_start_at',
            ],

            'freshness_interval_hours' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'version' => [
                'required',
                'integer',
                'min:1',
            ],
        ];
    }
}