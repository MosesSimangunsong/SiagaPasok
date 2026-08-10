<?php

namespace App\Http\Requests\Kdkmp;

use App\Models\FallbackRequest;
use Illuminate\Foundation\Http\FormRequest;

class StoreFallbackRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'create',
            FallbackRequest::class
        ) ?? false;
    }

    public function rules(): array
    {
        return [
            'forecast_id' => [
                'required',
                'integer',
                'exists:demand_forecasts,id',
            ],

            'requested_volume' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'response_deadline_at' => [
                'required',
                'date',
            ],

            'broadcast_note' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }
}