<?php

namespace App\Http\Requests\Kdkmp;

use App\Models\DocumentRecord;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDocumentRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $documentRecord =
            $this->route(
                'documentRecord'
            );

        return $documentRecord
                instanceof DocumentRecord
            && (
                $this->user()?->can(
                    'update',
                    $documentRecord
                ) ?? false
            );
    }

    public function rules(): array
    {
        return [
            'document_name' => [
                'sometimes',
                'string',
                'max:255',
            ],

            'reference_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'valid_from' => [
                'sometimes',
                'nullable',
                'date',
            ],

            'expires_at' => [
                'sometimes',
                'nullable',
                'date',
            ],

            'notes' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }
}