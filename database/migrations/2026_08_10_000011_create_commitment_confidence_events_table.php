<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'commitment_confidence_events',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId(
                    'commitment_id'
                )
                    ->constrained(
                        'supply_commitments'
                    )
                    ->restrictOnDelete();

                /*
                 * Nullable untuk initial transition:
                 * NULL -> GREEN.
                 */
                $table->string(
                    'from_confidence'
                )->nullable();

                $table->string(
                    'to_confidence'
                );

                $table->string('source');

                $table->string(
                    'reason_code'
                )->nullable();

                $table->text(
                    'reason_note'
                )->nullable();

                /*
                 * SYSTEM event boleh tidak memiliki
                 * actor_user_id.
                 */
                $table->foreignId(
                    'actor_user_id'
                )
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->dateTime(
                    'occurred_at'
                );

                $table->index(
                    [
                        'commitment_id',
                        'occurred_at',
                    ],
                    'commitment_confidence_events_timeline_index'
                );

                $table->index(
                    [
                        'source',
                        'occurred_at',
                    ],
                    'commitment_confidence_events_source_index'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'commitment_confidence_events'
        );
    }
};