<?php

namespace Tests\Feature\Admin;

use Database\Seeders\BaseReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BaseReferenceSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_base_reference_seeder_creates_required_m02_data(): void
    {
        $this->seed(BaseReferenceSeeder::class);

        $this->assertDatabaseHas('units', [
            'code' => 'kg',
            'name' => 'Kilogram',
            'symbol' => 'kg',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('commodities', [
            'code' => 'KANGKUNG',
            'name' => 'Kangkung',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('commodities', [
            'code' => 'BAYAM',
            'name' => 'Bayam',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('commodities', [
            'code' => 'KACANG_PANJANG',
            'name' => 'Kacang Panjang',
            'is_active' => true,
        ]);
    }

    public function test_base_reference_seeder_is_idempotent(): void
    {
        $this->seed(BaseReferenceSeeder::class);
        $this->seed(BaseReferenceSeeder::class);

        $this->assertDatabaseCount('units', 1);
        $this->assertDatabaseCount('commodities', 3);
    }
}