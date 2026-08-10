<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('actor_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string(
                'actor_role_snapshot'
            )->nullable();

            $table->foreignId('actor_organization_id')
                ->nullable()
                ->constrained('organizations')
                ->nullOnDelete();

            $table->string('source');
            $table->string('action');

            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');

            $table->json(
                'previous_value_json'
            )->nullable();

            $table->json(
                'new_value_json'
            )->nullable();

            $table->text('reason_note')->nullable();

            $table->timestamp('occurred_at');

            $table->index(
                [
                    'entity_type',
                    'entity_id',
                    'occurred_at',
                ],
                'audit_entity_occurred_index'
            );

            $table->index(
                [
                    'actor_user_id',
                    'occurred_at',
                ],
                'audit_actor_occurred_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};