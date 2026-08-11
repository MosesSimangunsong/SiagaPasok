<?php

namespace Database\Seeders;

use App\Enums\OrganizationType;
use App\Enums\ReadinessType;
use App\Enums\RequirementScope;
use App\Models\Commodity;
use App\Models\ReadinessRequirement;
use App\Support\Demo\DemoIdentifiers;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DemoReadinessRequirementSeeder extends Seeder
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
                'DemoReadinessRequirementSeeder hanya boleh dijalankan ketika SiagaPasok demo mode aktif.'
            );
        }

        $commodity =
            Commodity::query()
                ->where(
                    'code',
                    'KANGKUNG'
                )
                ->where(
                    'is_active',
                    true
                )
                ->first();

        if (! $commodity) {
            throw new RuntimeException(
                'Commodity KANGKUNG aktif belum tersedia.'
            );
        }

        DB::transaction(
            function () use (
                $commodity
            ): void {
                ReadinessRequirement::query()
                    ->updateOrCreate(
                        [
                            'requirement_code' =>
                                DemoIdentifiers
                                    ::LOGISTICS_REQUIREMENT_CODE,
                        ],
                        [
                            'readiness_type' =>
                                ReadinessType::LOGISTICS,

                            'label' =>
                                'Konfirmasi kesiapan logistik forecast simulasi',

                            'requirement_scope' =>
                                RequirementScope::FORECAST,

                            'applies_to_organization_type' =>
                                OrganizationType::KDKMP,

                            'commodity_id' =>
                                $commodity->id,

                            'is_required_default' =>
                                true,

                            'is_active' =>
                                true,

                            'sort_order' =>
                                10,

                            'config_json' => [
                                'simulation' => true,
                            ],
                        ],
                    );

                ReadinessRequirement::query()
                    ->updateOrCreate(
                        [
                            'requirement_code' =>
                                DemoIdentifiers
                                    ::DOCUMENT_REQUIREMENT_CODE,
                        ],
                        [
                            'readiness_type' =>
                                ReadinessType::DOCUMENT,

                            'label' =>
                                'Konfirmasi dokumen operasional forecast simulasi',

                            'requirement_scope' =>
                                RequirementScope::FORECAST,

                            'applies_to_organization_type' =>
                                OrganizationType::KDKMP,

                            'commodity_id' =>
                                $commodity->id,

                            'is_required_default' =>
                                true,

                            'is_active' =>
                                true,

                            'sort_order' =>
                                20,

                            'config_json' => [
                                'simulation' => true,
                            ],
                        ],
                    );
            }
        );
    }
}