<?php

namespace App\Http\Requests\Kdkmp;

use App\Models\CommitmentVersion;
use Illuminate\Foundation\Http\FormRequest;

class SubmitCommitmentVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $version = $this->route(
            'version'
        );

        return $version instanceof CommitmentVersion
            && (
                $this->user()?->can(
                    'submit',
                    $version
                ) ?? false
            );
    }

    public function rules(): array
    {
        return [];
    }
}