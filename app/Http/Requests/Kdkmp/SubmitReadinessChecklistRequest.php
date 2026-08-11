<?php

namespace App\Http\Requests\Kdkmp;

use App\Models\ReadinessChecklist;
use Illuminate\Foundation\Http\FormRequest;

class SubmitReadinessChecklistRequest extends FormRequest
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
                    'submit',
                    $checklist
                ) ?? false
            );
    }

    public function rules(): array
    {
        return [];
    }
}