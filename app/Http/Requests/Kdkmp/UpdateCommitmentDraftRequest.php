<?php

namespace App\Http\Requests\Kdkmp;

use App\Models\CommitmentVersion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCommitmentDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        $version = $this->route(
            'version'
        );

        return $version instanceof CommitmentVersion
            && (
                $this->user()?->can(
                    'updateDraft',
                    $version
                ) ?? false
            );
    }

    public function rules(): array
    {
        /** @var CommitmentVersion|null $version */
        $version = $this->route(
            'version'
        );

        $organizationId =
            $this->user()->organization_id;

        $isInitialDraft =
            $version instanceof CommitmentVersion
            && $version->version_no === 1
            && $version
                ->commitment
                ?->active_version_id === null;

        $rules = [
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
                $isInitialDraft
                    ? 'nullable'
                    : 'required',

                'string',
                'max:5000',
            ],

            'operator_justification' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ];

        if ($isInitialDraft) {
            $rules['producer_id'] = [
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
            ];

            $rules['expected_harvest_id'] = [
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
            ];
        } else {
            $rules['producer_id'] = [
                'prohibited',
            ];

            $rules['expected_harvest_id'] = [
                'prohibited',
            ];
        }

        return $rules;
    }
}