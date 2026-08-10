<?php

use App\Enums\ReadinessApprovalStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'readiness_checklists',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId(
                    'forecast_id'
                )
                    ->constrained(
                        'demand_forecasts'
                    )
                    ->restrictOnDelete();

                $table->foreignId(
                    'organization_id'
                )
                    ->constrained(
                        'organizations'
                    )
                    ->restrictOnDelete();

                $table->string(
                    'readiness_type'
                );

                /*
                 * Snapshot version Forecast
                 * ketika checklist dibuat.
                 *
                 * Current readiness kelak fail
                 * closed jika Forecast.version
                 * sudah berubah.
                 */
                $table->unsignedInteger(
                    'forecast_version'
                );

                $table->unsignedInteger(
                    'version_no'
                );

                $table->foreignId(
                    'supersedes_checklist_id'
                )
                    ->nullable()
                    ->constrained(
                        'readiness_checklists'
                    )
                    ->restrictOnDelete();

                $table->string(
                    'status'
                )->default(
                    ReadinessApprovalStatus
                        ::DRAFT
                        ->value
                );

                $table->boolean(
                    'is_current_version'
                )->default(true);

                $table->foreignId(
                    'prepared_by'
                )
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->foreignId(
                    'submitted_by'
                )
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->dateTime(
                    'submitted_at'
                )->nullable();

                $table->foreignId(
                    'reviewed_by'
                )
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->dateTime(
                    'reviewed_at'
                )->nullable();

                $table->text(
                    'review_reason'
                )->nullable();

                $table->dateTime(
                    'approved_at'
                )->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'forecast_id',
                        'organization_id',
                        'readiness_type',
                        'version_no',
                    ],
                    'readiness_checklists_version_unique'
                );

                $table->index(
                    [
                        'forecast_id',
                        'organization_id',
                        'readiness_type',
                        'is_current_version',
                    ],
                    'readiness_checklists_current_lookup_index'
                );

                $table->index(
                    [
                        'organization_id',
                        'status',
                        'submitted_at',
                    ],
                    'readiness_checklists_approval_queue_index'
                );

                $table->index(
                    [
                        'forecast_id',
                        'status',
                    ],
                    'readiness_checklists_forecast_status_index'
                );
            }
        );

        /*
         * PostgreSQL operational runtime dan
         * SQLite PHPUnit sama-sama mendukung
         * partial unique index.
         *
         * Invariant:
         * hanya boleh ada satu current checklist
         * per Forecast + Organization + Type.
         */
        DB::statement(
            '
            CREATE UNIQUE INDEX
                readiness_checklists_current_unique
            ON readiness_checklists (
                forecast_id,
                organization_id,
                readiness_type
            )
            WHERE is_current_version = TRUE
            '
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'readiness_checklists'
        );
    }
};