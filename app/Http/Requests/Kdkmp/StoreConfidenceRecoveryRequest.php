<?php

namespace App\Http\Requests\Kdkmp;

use App\Models\SupplyCommitment;
use Illuminate\Foundation\Http\FormRequest;

class StoreConfidenceRecoveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $commitment = $this->route(
            'commitment'
        );

        return $commitment instanceof SupplyCommitment
            && (
                $this->user()?->can(
                    'requestRecovery',
                    $commitment
                ) ?? false
            );
    }

    public function rules(): array
    {
        return [
            'recovery_reason' => [
                'required',
                'string',
                'max:5000',
            ],
        ];
    }
}