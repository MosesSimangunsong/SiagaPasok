<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Producer;
use App\Models\User;
use App\Support\Demo\DemoIdentifiers;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DemoSupplySeeder extends Seeder
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
                'DemoSupplySeeder hanya boleh dijalankan ketika SiagaPasok demo mode aktif.'
            );
        }

        DB::transaction(function (): void {
            $primaryOrganization =
                $this->resolveOrganization(
                    DemoIdentifiers::PRIMARY_KDKMP_CODE
                );

            $networkOrganization =
                $this->resolveOrganization(
                    DemoIdentifiers::NETWORK_KDKMP_CODE
                );

            $primaryOperator =
                $this->resolveUser(
                    DemoIdentifiers::PRIMARY_OPERATOR_EMAIL
                );

            $networkOperator =
                $this->resolveUser(
                    DemoIdentifiers::NETWORK_OPERATOR_EMAIL
                );

            $this->seedProducerGroup(
                organization: $primaryOrganization,
                creator: $primaryOperator,
                codes:
                    DemoIdentifiers::PRIMARY_PRODUCER_CODES,
                namePrefix:
                    'Produsen Demo Tani Sejahtera',
                villagePrefix:
                    'Desa Simulasi Tani'
            );

            $this->seedProducerGroup(
                organization: $networkOrganization,
                creator: $networkOperator,
                codes:
                    DemoIdentifiers::NETWORK_PRODUCER_CODES,
                namePrefix:
                    'Produsen Demo Mitra Lestari',
                villagePrefix:
                    'Desa Simulasi Mitra'
            );
        });
    }

    private function resolveOrganization(
        string $code
    ): Organization {
        $organization = Organization::query()
            ->where('code', $code)
            ->where('is_active', true)
            ->first();

        if (! $organization) {
            throw new RuntimeException(
                "Demo organization {$code} belum tersedia atau tidak aktif. Jalankan DemoIdentitySeeder terlebih dahulu."
            );
        }

        return $organization;
    }

    private function resolveUser(
        string $email
    ): User {
        $user = User::query()
            ->where('email', $email)
            ->where('is_active', true)
            ->first();

        if (
            ! $user
            || ! $user->hasValidIdentityContext()
        ) {
            throw new RuntimeException(
                "Demo user {$email} belum tersedia atau identity context-nya tidak valid."
            );
        }

        return $user;
    }

    /**
     * @param array<int, string> $codes
     */
    private function seedProducerGroup(
        Organization $organization,
        User $creator,
        array $codes,
        string $namePrefix,
        string $villagePrefix
    ): void {
        foreach ($codes as $index => $code) {
            $sequence = $index + 1;

            Producer::query()->updateOrCreate(
                [
                    'organization_id' =>
                        $organization->id,
                    'producer_code' =>
                        $code,
                ],
                [
                    'name' => sprintf(
                        '%s %02d',
                        $namePrefix,
                        $sequence
                    ),
                    'village' => sprintf(
                        '%s %02d',
                        $villagePrefix,
                        $sequence
                    ),
                    'district' =>
                        'Kabupaten Badung (SIMULASI)',
                    'contact_phone' =>
                        null,
                    'notes' =>
                        'Data produsen simulasi untuk demonstrasi SiagaPasok.',
                    'is_active' =>
                        true,
                    'created_by' =>
                        $creator->id,
                ],
            );
        }
    }
}