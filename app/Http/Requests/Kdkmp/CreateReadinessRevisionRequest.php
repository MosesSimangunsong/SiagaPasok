<?php

namespace App\Http\Requests\Kdkmp;

use App\Models\ReadinessChecklist;
use Illuminate\Foundation\Http\FormRequest;

class CreateReadinessRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $checklist =
            $this->route(
                'checklist'
            );

        return $checklist
                instanceof ReadinessChecklist
            && (
                $this->user()?->can(
                    'createRevision',
                    $checklist
                ) ?? false
            );
    }

    public function rules(): array
    {
        return [];
    }
}