<?php

namespace App\Http\Requests\Kdkmp;

use App\Models\DocumentRecord;
use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'create',
            DocumentRecord::class
        ) ?? false;
    }

    public function rules(): array
    {
        return [
            'requirement_id' => [
                'required',
                'integer',
                'exists:readiness_requirements,id',
            ],

            'document_name' => [
                'required',
                'string',
                'max:255',
            ],

            'reference_number' => [
                'nullable',
                'string',
                'max:255',
            ],

            'valid_from' => [
                'nullable',
                'date',
            ],

            'expires_at' => [
                'nullable',
                'date',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }
}