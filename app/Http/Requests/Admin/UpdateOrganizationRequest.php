<?php

namespace App\Http\Requests\Admin;

use App\Enums\OrganizationType;
use App\Enums\UserRole;
use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateOrganizationRequest extends FormRequest
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
        /** @var Organization|null $organization */
        $organization = $this->route('organization');

        return [
            'code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('organizations', 'code')
                    ->ignore($organization),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'organization_type' => [
                'required',
                Rule::enum(OrganizationType::class),
            ],
            'general_location' => [
                'nullable',
                'string',
                'max:255',
            ],
            'is_active' => [
                'required',
                'boolean',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Organization|null $organization */
            $organization = $this->route('organization');

            if (! $organization) {
                return;
            }

            $newType = OrganizationType::tryFrom(
                (string) $this->input('organization_type')
            );

            if (! $newType || $newType === $organization->organization_type) {
                return;
            }

            if (
                $newType === OrganizationType::SPPG
                && $organization->users()
                    ->whereIn('role', [
                        UserRole::KDKMP_OPERATOR->value,
                        UserRole::KDKMP_MANAGER->value,
                    ])
                    ->exists()
            ) {
                $validator->errors()->add(
                    'organization_type',
                    'Tipe organisasi tidak dapat diubah menjadi SPPG karena masih memiliki user dengan role KDKMP.'
                );

                return;
            }

            if (
                $newType === OrganizationType::KDKMP
                && $organization->users()
                    ->where('role', UserRole::SPPG_USER->value)
                    ->exists()
            ) {
                $validator->errors()->add(
                    'organization_type',
                    'Tipe organisasi tidak dapat diubah menjadi KDKMP karena masih memiliki user dengan role SPPG.'
                );
            }
        });
    }
}