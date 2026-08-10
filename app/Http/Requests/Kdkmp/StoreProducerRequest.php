<?php

namespace App\Http\Requests\Kdkmp;

use App\Models\Producer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProducerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'create',
            Producer::class
        ) ?? false;
    }

    public function rules(): array
    {
        return [
            'producer_code' => [
                'required',
                'string',
                'max:255',
                Rule::unique(
                    'producers',
                    'producer_code'
                )->where(
                    'organization_id',
                    $this->user()->organization_id
                ),
            ],

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'village' => [
                'required',
                'string',
                'max:255',
            ],

            'district' => [
                'required',
                'string',
                'max:255',
            ],

            'contact_phone' => [
                'nullable',
                'string',
                'max:255',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }
}