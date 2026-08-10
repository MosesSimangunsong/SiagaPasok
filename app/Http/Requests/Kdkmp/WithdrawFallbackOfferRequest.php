<?php

namespace App\Http\Requests\Kdkmp;

use App\Models\FallbackOffer;
use Illuminate\Foundation\Http\FormRequest;

class WithdrawFallbackOfferRequest extends FormRequest
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
                    'withdraw',
                    $offer
                ) ?? false
            );
    }

    public function rules(): array
    {
        return [
            'withdrawal_reason' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }
}