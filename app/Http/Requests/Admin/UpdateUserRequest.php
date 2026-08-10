<?php

namespace App\Http\Requests\Admin;

use App\Enums\OrganizationType;
use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
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
        /** @var User|null $user */
        $user = $this->route('user');

        return [
            'organization_id' => [
                'nullable',
                'integer',
                Rule::exists('organizations', 'id'),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')
                    ->ignore($user),
            ],
            'role' => [
                'required',
                Rule::enum(UserRole::class),
            ],
            'is_active' => [
                'required',
                'boolean',
            ],
            'password' => [
                'nullable',
                'string',
                'min:8',
                'confirmed',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateIdentityContext($validator);
        });
    }

    private function validateIdentityContext(Validator $validator): void
    {
        $role = UserRole::tryFrom(
            (string) $this->input('role')
        );

        if (! $role) {
            return;
        }

        $organizationId = $this->input('organization_id');

        if ($role === UserRole::SYSTEM_ADMIN) {
            if ($organizationId !== null && $organizationId !== '') {
                $validator->errors()->add(
                    'organization_id',
                    'System Admin tidak terikat pada organisasi.'
                );
            }

            return;
        }

        if ($organizationId === null || $organizationId === '') {
            $validator->errors()->add(
                'organization_id',
                'Organization wajib dipilih untuk business user.'
            );

            return;
        }

        $organization = Organization::query()->find($organizationId);

        if (! $organization) {
            return;
        }

        if (
            $role === UserRole::SPPG_USER
            && $organization->organization_type !== OrganizationType::SPPG
        ) {
            $validator->errors()->add(
                'organization_id',
                'SPPG User hanya dapat ditempatkan pada organisasi SPPG.'
            );

            return;
        }

        if (
            $role->isKdkmpRole()
            && $organization->organization_type !== OrganizationType::KDKMP
        ) {
            $validator->errors()->add(
                'organization_id',
                'KDKMP Operator dan KDKMP Manager hanya dapat ditempatkan pada organisasi KDKMP.'
            );
        }
    }
}