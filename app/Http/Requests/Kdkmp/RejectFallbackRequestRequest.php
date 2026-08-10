<?php

namespace App\Http\Requests\Kdkmp;

use App\Models\FallbackRequest;
use Illuminate\Foundation\Http\FormRequest;

class RejectFallbackRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $fallbackRequest =
            $this->route(
                'fallbackRequest'
            );

        return $fallbackRequest
            instanceof FallbackRequest
            && (
                $this->user()?->can(
                    'rejectBroadcast',
                    $fallbackRequest
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