<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supply_network_links', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sppg_organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();

            $table->foreignId('kdkmp_organization_id')
                ->constrained('organizations')
                ->restrictOnDelete();

            $table->string('network_role');
            $table->boolean('is_active')->default(true);

            $table->foreignId('configured_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();

            $table->unique([
                'sppg_organization_id',
                'kdkmp_organization_id',
            ]);

            $table->index([
                'sppg_organization_id',
                'is_active',
                'network_role',
            ]);

            $table->index([
                'kdkmp_organization_id',
                'is_active',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supply_network_links');
    }
};