<?php

namespace App\Http\Requests\Kdkmp;

use App\Models\SupplyCommitment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommitmentRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $commitment = $this->route(
            'commitment'
        );

        return $commitment instanceof SupplyCommitment
            && (
                $this->user()?->can(
                    'createRevision',
                    $commitment
                ) ?? false
            );
    }

    public function rules(): array
    {
        return [
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

            'change_reason' => [
                'required',
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