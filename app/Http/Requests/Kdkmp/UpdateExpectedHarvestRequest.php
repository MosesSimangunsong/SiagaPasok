<?php

namespace App\Http\Requests\Kdkmp;

use App\Models\ExpectedHarvest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpectedHarvestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $expectedHarvest = $this->route(
            'expectedHarvest'
        );

        return $expectedHarvest instanceof ExpectedHarvest
            && (
                $this->user()?->can(
                    'update',
                    $expectedHarvest
                ) ?? false
            );
    }

    public function rules(): array
    {
        return [
            'producer_id' => [
                'required',
                'integer',
                Rule::exists(
                    'producers',
                    'id'
                )
                    ->where(
                        'organization_id',
                        $this->user()->organization_id
                    )
                    ->where(
                        'is_active',
                        true
                    ),
            ],

            'commodity_id' => [
                'required',
                'integer',
                Rule::exists(
                    'commodities',
                    'id'
                )->where(
                    'is_active',
                    true
                ),
            ],

            'unit_id' => [
                'required',
                'integer',
                Rule::exists(
                    'units',
                    'id'
                )->where(
                    'is_active',
                    true
                ),
            ],

            'expected_min_volume' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'expected_max_volume' => [
                'required',
                'numeric',
                'gte:expected_min_volume',
            ],

            'harvest_start_at' => [
                'required',
                'date',
            ],

            'harvest_end_at' => [
                'required',
                'date',
                'after_or_equal:harvest_start_at',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }
}