<?php

namespace App\Http\Requests\Kdkmp;

use App\Models\DocumentRecord;
use Illuminate\Foundation\Http\FormRequest;

class RevokeDocumentRecordRequest extends FormRequest
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
                    'revoke',
                    $documentRecord
                ) ?? false
            );
    }

    public function rules(): array
    {
        return [
            'reason' => [
                'required',
                'string',
                'max:5000',
            ],
        ];
    }
}