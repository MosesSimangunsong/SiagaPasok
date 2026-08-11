<?php

namespace Database\Seeders;

use App\Enums\NetworkRole;
use App\Enums\OrganizationType;
use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\SupplyNetworkLink;
use App\Models\User;
use App\Support\Demo\DemoIdentifiers;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DemoIdentitySeeder extends Seeder
{
    public function run(): void
    {
        if (
            ! (bool) config(
                'siagapasok.demo.enabled',
                false
            )
        ) {
            throw new RuntimeException(
                'DemoIdentitySeeder hanya boleh dijalankan ketika SiagaPasok demo mode aktif.'
            );
        }

        DB::transaction(function (): void {
            $sppg = $this->upsertOrganization(
                DemoIdentifiers::SPPG_CODE,
                DemoIdentifiers::SPPG_NAME,
                OrganizationType::SPPG
            );

            $primaryKdkmp = $this->upsertOrganization(
                DemoIdentifiers::PRIMARY_KDKMP_CODE,
                DemoIdentifiers::PRIMARY_KDKMP_NAME,
                OrganizationType::KDKMP
            );

            $networkKdkmp = $this->upsertOrganization(
                DemoIdentifiers::NETWORK_KDKMP_CODE,
                DemoIdentifiers::NETWORK_KDKMP_NAME,
                OrganizationType::KDKMP
            );

            $admin = $this->upsertUser(
                null,
                'System Admin Demo',
                DemoIdentifiers::ADMIN_EMAIL,
                UserRole::SYSTEM_ADMIN
            );

            $this->upsertUser(
                $sppg,
                'Pengguna SPPG Badung Demo',
                DemoIdentifiers::SPPG_EMAIL,
                UserRole::SPPG_USER
            );

            $this->upsertUser(
                $primaryKdkmp,
                'Operator Tani Sejahtera',
                DemoIdentifiers::PRIMARY_OPERATOR_EMAIL,
                UserRole::KDKMP_OPERATOR
            );

            $this->upsertUser(
                $primaryKdkmp,
                'Manager Tani Sejahtera',
                DemoIdentifiers::PRIMARY_MANAGER_EMAIL,
                UserRole::KDKMP_MANAGER
            );

            $this->upsertUser(
                $networkKdkmp,
                'Operator Mitra Lestari',
                DemoIdentifiers::NETWORK_OPERATOR_EMAIL,
                UserRole::KDKMP_OPERATOR
            );

            $this->upsertUser(
                $networkKdkmp,
                'Manager Mitra Lestari',
                DemoIdentifiers::NETWORK_MANAGER_EMAIL,
                UserRole::KDKMP_MANAGER
            );

            SupplyNetworkLink::query()->updateOrCreate(
                [
                    'sppg_organization_id' => $sppg->id,
                    'kdkmp_organization_id' => $primaryKdkmp->id,
                ],
                [
                    'network_role' => NetworkRole::PRIMARY,
                    'is_active' => true,
                    'configured_by' => $admin->id,
                ],
            );

            SupplyNetworkLink::query()->updateOrCreate(
                [
                    'sppg_organization_id' => $sppg->id,
                    'kdkmp_organization_id' => $networkKdkmp->id,
                ],
                [
                    'network_role' => NetworkRole::NETWORK,
                    'is_active' => true,
                    'configured_by' => $admin->id,
                ],
            );
        });
    }

    private function upsertOrganization(
        string $code,
        string $name,
        OrganizationType $type
    ): Organization {
        return Organization::query()->updateOrCreate(
            [
                'code' => $code,
            ],
            [
                'name' => $name,
                'organization_type' => $type,
                'is_active' => true,
                'general_location' =>
                    'Kabupaten Badung, Bali (SIMULASI)',
            ],
        );
    }

    private function upsertUser(
        ?Organization $organization,
        string $name,
        string $email,
        UserRole $role
    ): User {
        return User::query()->updateOrCreate(
            [
                'email' => $email,
            ],
            [
                'organization_id' => $organization?->id,
                'name' => $name,
                'password' => DemoIdentifiers::DEMO_PASSWORD,
                'role' => $role,
                'is_active' => true,
                'last_login_at' => null,
            ],
        );
    }
}