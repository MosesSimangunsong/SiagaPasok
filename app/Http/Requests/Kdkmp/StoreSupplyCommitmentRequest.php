<?php

namespace App\Http\Requests\Kdkmp;

use App\Models\SupplyCommitment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplyCommitmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'create',
            SupplyCommitment::class
        ) ?? false;
    }

    public function rules(): array
    {
        $organizationId =
            $this->user()->organization_id;

        return [
            'forecast_id' => [
                'required',
                'integer',
                'exists:demand_forecasts,id',
            ],

            'producer_id' => [
                'required',
                'integer',

                Rule::exists(
                    'producers',
                    'id'
                )->where(
                    fn ($query) =>
                        $query
                            ->where(
                                'organization_id',
                                $organizationId
                            )
                            ->where(
                                'is_active',
                                true
                            )
                ),
            ],

            'expected_harvest_id' => [
                'nullable',
                'integer',

                Rule::exists(
                    'expected_harvests',
                    'id'
                )->where(
                    fn ($query) =>
                        $query->where(
                            'organization_id',
                            $organizationId
                        )
                ),
            ],

            'min_volume' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'max_volume' => [
                'required',
                'numeric',
                'gte:min_volume',
            ],

            'unit_id' => [
                'required',
                'integer',

                Rule::exists(
                    'units',
                    'id'
                )->where(
                    fn ($query) =>
                        $query->where(
                            'is_active',
                            true
                        )
                ),
            ],

            'availability_start_at' => [
                'required',
                'date',
            ],

            'availability_end_at' => [
                'required',
                'date',
                'after_or_equal:availability_start_at',
            ],

            'notes' => [
    'nullable',
    'string',
    'max:5000',
],

            'operator_justification' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }
}