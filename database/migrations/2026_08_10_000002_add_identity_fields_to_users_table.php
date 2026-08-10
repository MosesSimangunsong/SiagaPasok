<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_id')
                ->nullable()
                ->after('id')
                ->constrained('organizations')
                ->restrictOnDelete();

            $table->string('role')
                ->after('password');

            $table->boolean('is_active')
                ->default(true)
                ->after('role');

            $table->timestamp('last_login_at')
                ->nullable()
                ->after('is_active');

            $table->index('role');
            $table->index('is_active');
            $table->index(['organization_id', 'role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'role']);
            $table->dropIndex(['is_active']);
            $table->dropIndex(['role']);

            $table->dropForeign(['organization_id']);

            $table->dropColumn([
                'organization_id',
                'role',
                'is_active',
                'last_login_at',
            ]);
        });
    }
};