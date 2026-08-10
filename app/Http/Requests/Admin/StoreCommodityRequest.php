<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommodityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSystemAdmin() ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('commodities', 'code'),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'default_unit_id' => [
                'required',
                'integer',
                Rule::exists('units', 'id'),
            ],
            'harvest_behavior' => [
                'nullable',
                'string',
                'max:100',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'is_active' => [
                'required',
                'boolean',
            ],
        ];
    }
}