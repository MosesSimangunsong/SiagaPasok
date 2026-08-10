<?php

namespace App\Http\Requests\Kdkmp;

use App\Models\FallbackOffer;
use Illuminate\Foundation\Http\FormRequest;

class RejectOutgoingFallbackOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        $offer =
            $this->route(
                'fallbackOffer'
            );

        return $offer
            instanceof FallbackOffer
            && (
                $this->user()?->can(
                    'supplierReview',
                    $offer
                ) ?? false
            );
    }

    public function rules(): array
    {
        return [
            'supplier_review_reason' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }
}