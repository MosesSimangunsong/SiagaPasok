<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'notifications',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->foreignId(
                        'recipient_user_id'
                    )
                    ->constrained('users')
                    ->cascadeOnDelete();

                $table->string(
                    'notification_type'
                );

                $table->string(
                    'priority'
                );

                $table->string(
                    'title'
                );

                $table->text(
                    'message'
                );

                $table
                    ->string(
                        'related_entity_type'
                    )
                    ->nullable();

                $table
                    ->unsignedBigInteger(
                        'related_entity_id'
                    )
                    ->nullable();

                /*
                 * CTA disimpan sebagai internal
                 * application path.
                 */
                $table
                    ->text(
                        'action_url'
                    )
                    ->nullable();

                /*
                 * Business-event deduplication.
                 *
                 * Contoh integrasi nanti:
                 * commitment:41:submitted
                 * offer:12:available
                 * forecast:8:rfp:reached:<sequence>
                 */
                $table
                    ->string(
                        'deduplication_key'
                    )
                    ->nullable();

                $table
                    ->timestamp(
                        'read_at'
                    )
                    ->nullable();

                $table->timestamp(
                    'created_at'
                );

                $table->index(
                    [
                        'recipient_user_id',
                        'read_at',
                        'created_at',
                    ],
                    'notification_recipient_read_index'
                );

                $table->index(
                    [
                        'related_entity_type',
                        'related_entity_id',
                    ],
                    'notification_related_entity_index'
                );

                $table->unique(
                    [
                        'recipient_user_id',
                        'deduplication_key',
                    ],
                    'notification_recipient_dedupe_unique'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'notifications'
        );
    }
};