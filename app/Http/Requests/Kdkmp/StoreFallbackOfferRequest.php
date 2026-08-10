<?php

namespace App\Http\Requests\Kdkmp;

use App\Models\FallbackOffer;
use App\Models\FallbackRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFallbackOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        $fallbackRequest =
            $this->route(
                'fallbackRequest'
            );

        return $fallbackRequest
            instanceof FallbackRequest
            && (
                $this->user()?->can(
                    'createForRequest',
                    [
                        FallbackOffer::class,
                        $fallbackRequest,
                    ]
                ) ?? false
            );
    }

    public function rules(): array
    {
        $organizationId =
            $this->user()
                ->organization_id;

        return [
            'offered_volume' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'availability_note' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'expires_at' => [
                'required',
                'date',
            ],

            'source_commitment_ids' => [
                'required',
                'array',
                'min:1',
            ],

            'source_commitment_ids.*' => [
                'required',
                'integer',
                'distinct',

                Rule::exists(
                    'supply_commitments',
                    'id'
                )->where(
                    fn ($query) =>
                        $query->where(
                            'organization_id',
                            $organizationId
                        )
                ),
            ],
        ];
    }
}