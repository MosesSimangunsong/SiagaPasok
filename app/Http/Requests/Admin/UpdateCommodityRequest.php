<?php

namespace App\Http\Requests\Admin;

use App\Models\Commodity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCommodityRequest extends FormRequest
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
        /** @var Commodity|null $commodity */
        $commodity = $this->route('commodity');

        return [
            'code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('commodities', 'code')
                    ->ignore($commodity),
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