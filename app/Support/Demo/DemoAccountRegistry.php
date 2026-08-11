<?php

namespace App\Support\Demo;

use App\Enums\UserRole;
use App\Models\User;

final class DemoAccountRegistry
{
    /**
     * @return array<string, array{
     *     email: string,
     *     role: UserRole,
     *     organization_code: string,
     *     label: string,
     *     organization_label: string
     * }>
     */
    private static function definitions(): array
    {
        return [
            'sppg' => [
                'email' =>
                    DemoIdentifiers::SPPG_EMAIL,
                'role' =>
                    UserRole::SPPG_USER,
                'organization_code' =>
                    DemoIdentifiers::SPPG_CODE,
                'label' =>
                    'SPPG User',
                'organization_label' =>
                    DemoIdentifiers::SPPG_NAME,
            ],

            'tani-operator' => [
                'email' =>
                    DemoIdentifiers::PRIMARY_OPERATOR_EMAIL,
                'role' =>
                    UserRole::KDKMP_OPERATOR,
                'organization_code' =>
                    DemoIdentifiers::PRIMARY_KDKMP_CODE,
                'label' =>
                    'Operator',
                'organization_label' =>
                    DemoIdentifiers::PRIMARY_KDKMP_NAME,
            ],

            'tani-manager' => [
                'email' =>
                    DemoIdentifiers::PRIMARY_MANAGER_EMAIL,
                'role' =>
                    UserRole::KDKMP_MANAGER,
                'organization_code' =>
                    DemoIdentifiers::PRIMARY_KDKMP_CODE,
                'label' =>
                    'Manager',
                'organization_label' =>
                    DemoIdentifiers::PRIMARY_KDKMP_NAME,
            ],

            'mitra-operator' => [
                'email' =>
                    DemoIdentifiers::NETWORK_OPERATOR_EMAIL,
                'role' =>
                    UserRole::KDKMP_OPERATOR,
                'organization_code' =>
                    DemoIdentifiers::NETWORK_KDKMP_CODE,
                'label' =>
                    'Operator',
                'organization_label' =>
                    DemoIdentifiers::NETWORK_KDKMP_NAME,
            ],

            'mitra-manager' => [
                'email' =>
                    DemoIdentifiers::NETWORK_MANAGER_EMAIL,
                'role' =>
                    UserRole::KDKMP_MANAGER,
                'organization_code' =>
                    DemoIdentifiers::NETWORK_KDKMP_CODE,
                'label' =>
                    'Manager',
                'organization_label' =>
                    DemoIdentifiers::NETWORK_KDKMP_NAME,
            ],
        ];
    }

    /**
     * Data presentasi yang aman dikirim ke browser.
     *
     * @return array<int, array{
     *     key: string,
     *     label: string,
     *     organization_label: string,
     *     current: bool
     * }>
     */
    public static function options(
        ?User $currentUser
    ): array {
        $options = [];

        foreach (
            self::definitions()
            as $key => $definition
        ) {
            $options[] = [
                'key' => $key,
                'label' =>
                    $definition['label'],
                'organization_label' =>
                    $definition['organization_label'],
                'current' =>
                    $currentUser?->email
                    === $definition['email'],
            ];
        }

        return $options;
    }

    public static function resolve(
        string $key
    ): ?User {
        $definition =
            self::definitions()[$key] ?? null;

        if (! $definition) {
            return null;
        }

        $user = User::query()
            ->with('organization')
            ->where(
                'email',
                $definition['email']
            )
            ->where(
                'is_active',
                true
            )
            ->first();

        if (
            ! $user
            || ! $user->hasValidIdentityContext()
        ) {
            return null;
        }

        if (
            $user->role
            !== $definition['role']
        ) {
            return null;
        }

        if (
            $user->organization?->code
            !== $definition['organization_code']
        ) {
            return null;
        }

        return $user;
    }

    private function __construct()
    {
    }
}