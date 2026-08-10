<?php

namespace Database\Seeders;

use App\Models\Commodity;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class BaseReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $kilogram = Unit::query()->updateOrCreate(
            [
                'code' => 'kg',
            ],
            [
                'name' => 'Kilogram',
                'symbol' => 'kg',
                'decimal_precision' => 2,
                'is_active' => true,
            ],
        );

        $commodities = [
            [
                'code' => 'KANGKUNG',
                'name' => 'Kangkung',
            ],
            [
                'code' => 'BAYAM',
                'name' => 'Bayam',
            ],
            [
                'code' => 'KACANG_PANJANG',
                'name' => 'Kacang Panjang',
            ],
        ];

        foreach ($commodities as $commodity) {
            Commodity::query()->updateOrCreate(
                [
                    'code' => $commodity['code'],
                ],
                [
                    'name' => $commodity['name'],
                    'default_unit_id' => $kilogram->id,
                    'harvest_behavior' => null,
                    'notes' => null,
                    'is_active' => true,
                ],
            );
        }
    }
}