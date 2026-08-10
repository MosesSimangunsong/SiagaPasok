<?php

namespace App\Http\Requests\Kdkmp;

use App\Models\FallbackOffer;
use Illuminate\Foundation\Http\FormRequest;

class AcceptFallbackOfferRequest extends FormRequest
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
                    'requesterDecision',
                    $offer
                ) ?? false
            );
    }

    public function rules(): array
    {
        return [
            'accepted_volume' => [
                'required',
                'numeric',
                'gt:0',
            ],
        ];
    }
}