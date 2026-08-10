<?php

namespace App\Http\Requests\Kdkmp;

use App\Enums\SupplyConfidence;
use App\Models\SupplyCommitment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DowngradeCommitmentConfidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $commitment = $this->route(
            'commitment'
        );

        return $commitment instanceof SupplyCommitment
            && (
                $this->user()?->can(
                    'downgradeConfidence',
                    $commitment
                ) ?? false
            );
    }

    public function rules(): array
    {
        return [
            'to_confidence' => [
                'required',

                Rule::in([
                    SupplyConfidence::YELLOW->value,
                    SupplyConfidence::RED->value,
                ]),
            ],

            'reason_code' => [
                'nullable',
                'string',
                'max:100',
            ],

            'reason_note' => [
                'required',
                'string',
                'max:5000',
            ],
        ];
    }
}