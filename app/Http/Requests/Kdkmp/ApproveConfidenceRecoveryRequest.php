<?php

namespace App\Http\Requests\Kdkmp;

use App\Models\ConfidenceRecoveryRequest;
use Illuminate\Foundation\Http\FormRequest;

class ApproveConfidenceRecoveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $recovery = $this->route(
            'recovery'
        );

        return $recovery instanceof ConfidenceRecoveryRequest
            && (
                $this->user()?->can(
                    'approve',
                    $recovery
                ) ?? false
            );
    }

    public function rules(): array
    {
        return [
            'review_reason' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }
}