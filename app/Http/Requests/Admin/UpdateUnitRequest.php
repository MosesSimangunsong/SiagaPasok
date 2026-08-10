<?php

namespace App\Http\Requests\Admin;

use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnitRequest extends FormRequest
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
        /** @var Unit|null $unit */
        $unit = $this->route('unit');

        return [
            'code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('units', 'code')->ignore($unit),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'symbol' => [
                'required',
                'string',
                'max:50',
            ],
            'decimal_precision' => [
                'required',
                'integer',
                'min:0',
                'max:6',
            ],
            'is_active' => [
                'required',
                'boolean',
            ],
        ];
    }
}