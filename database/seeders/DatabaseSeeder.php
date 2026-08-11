<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            BaseReferenceSeeder::class,
        ]);

        if (
            (bool) config(
                'siagapasok.demo.enabled',
                false
            )
        ) {
            $this->call([
                DemoIdentitySeeder::class,
                DemoSupplySeeder::class,
                DemoBaselineScenarioSeeder::class,
            ]);
        }
    }
}