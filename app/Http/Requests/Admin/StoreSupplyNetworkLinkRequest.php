<?php

namespace App\Http\Requests\Admin;

use App\Enums\NetworkRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplyNetworkLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSystemAdmin() ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'sppg_organization_id' => [
                'required',
                'integer',
                Rule::exists('organizations', 'id'),
            ],
            'kdkmp_organization_id' => [
                'required',
                'integer',
                'different:sppg_organization_id',
                Rule::exists('organizations', 'id'),
            ],
            'network_role' => [
                'required',
                Rule::enum(NetworkRole::class),
            ],
            'is_active' => [
                'required',
                'boolean',
            ],
        ];
    }
}