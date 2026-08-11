<?php

namespace App\Http\Requests\Kdkmp;

use App\Models\DocumentRecord;
use Illuminate\Foundation\Http\FormRequest;

class ValidateDocumentRecordRequest extends FormRequest
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
                    'markValid',
                    $documentRecord
                ) ?? false
            );
    }

    public function rules(): array
    {
        return [];
    }
}