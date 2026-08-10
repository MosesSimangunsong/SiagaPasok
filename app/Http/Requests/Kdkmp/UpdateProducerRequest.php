<?php

namespace App\Http\Requests\Kdkmp;

use App\Models\Producer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProducerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $producer = $this->route('producer');

        return $producer instanceof Producer
            && (
                $this->user()?->can(
                    'update',
                    $producer
                ) ?? false
            );
    }

    public function rules(): array
    {
        /** @var Producer $producer */
        $producer = $this->route('producer');

        return [
            'producer_code' => [
                'required',
                'string',
                'max:255',
                Rule::unique(
                    'producers',
                    'producer_code'
                )
                    ->where(
                        'organization_id',
                        $this->user()->organization_id
                    )
                    ->ignore($producer->id),
            ],

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'village' => [
                'required',
                'string',
                'max:255',
            ],

            'district' => [
                'required',
                'string',
                'max:255',
            ],

            'contact_phone' => [
                'nullable',
                'string',
                'max:255',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }
}