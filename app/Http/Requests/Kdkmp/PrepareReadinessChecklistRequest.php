<?php

namespace App\Http\Requests\Kdkmp;

use App\Models\ReadinessChecklist;
use Illuminate\Foundation\Http\FormRequest;

class PrepareReadinessChecklistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'create',
            ReadinessChecklist::class
        ) ?? false;
    }

    public function rules(): array
    {
        return [];
    }
} 