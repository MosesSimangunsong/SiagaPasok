<?php

namespace App\Http\Requests\Kdkmp;

use App\Models\CommitmentVersion;
use Illuminate\Foundation\Http\FormRequest;

class RejectCommitmentVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $version = $this->route(
            'version'
        );

        return $version instanceof CommitmentVersion
            && (
                $this->user()?->can(
                    'reject',
                    $version
                ) ?? false
            );
    }

    public function rules(): array
    {
        return [
            'review_reason' => [
                'required',
                'string',
                'max:5000',
            ],
        ];
    }
}