<?php

namespace App\Http\Requests\Kdkmp;

use App\Models\Producer;
use Illuminate\Foundation\Http\FormRequest;

class SetProducerActiveStateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $producer = $this->route('producer');

        return $producer instanceof Producer
            && (
                $this->user()?->can(
                    'setActiveState',
                    $producer
                ) ?? false
            );
    }

    public function rules(): array
    {
        return [
            'is_active' => [
                'required',
                'boolean',
            ],
        ];
    }
}