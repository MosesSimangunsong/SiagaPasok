<?php

namespace App\Http\Requests\Kdkmp;

use App\Models\ReadinessChecklist;
use App\Models\ReadinessItem;
use Illuminate\Foundation\Http\FormRequest;

class UpdateReadinessItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $checklist =
            $this->route(
                'checklist'
            );

        $item =
            $this->route(
                'item'
            );

        return $checklist
                instanceof ReadinessChecklist
            && $item
                instanceof ReadinessItem
            && $item
                ->readiness_checklist_id
                === $checklist->id
            && (
                $this->user()?->can(
                    'updateItem',
                    $checklist
                ) ?? false
            )
            && (
                $this->user()?->can(
                    'update',
                    $item
                ) ?? false
            );
    }

    public function rules(): array
    {
        return [
            'is_satisfied' => [
                'sometimes',
                'boolean',
            ],

            'note' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],

            'document_record_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:document_records,id',
            ],

            'value_json' => [
                'sometimes',
                'nullable',
                'array',
            ],
        ];
    }
}