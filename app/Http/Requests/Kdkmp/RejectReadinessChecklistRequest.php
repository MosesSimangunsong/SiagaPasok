<?php

namespace App\Http\Requests\Kdkmp;

use App\Models\ReadinessChecklist;
use Illuminate\Foundation\Http\FormRequest;

class RejectReadinessChecklistRequest extends FormRequest
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
                    'reject',
                    $checklist
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