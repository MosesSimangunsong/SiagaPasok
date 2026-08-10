<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'fallback_offer_sources',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId(
                    'fallback_offer_id'
                )
                    ->constrained(
                        'fallback_offers'
                    )
                    ->restrictOnDelete();

                $table->foreignId(
                    'supply_commitment_id'
                )
                    ->constrained(
                        'supply_commitments'
                    )
                    ->restrictOnDelete();

                /*
                 * Lifetime ledger:
                 *
                 * reserved_volume
                 * = total yang pernah di-reserve
                 *   untuk offer ini/source ini.
                 *
                 * allocated_volume
                 * = bagian reserve yang akhirnya
                 *   diterima requester.
                 *
                 * released_volume
                 * = bagian reserve yang dibebaskan.
                 *
                 * Current open reserve:
                 *
                 * reserved - allocated - released
                 */
                $table->decimal(
                    'reserved_volume',
                    18,
                    6
                )->default(
                    '0.000000'
                );

                $table->decimal(
                    'allocated_volume',
                    18,
                    6
                )->default(
                    '0.000000'
                );

                $table->decimal(
                    'released_volume',
                    18,
                    6
                )->default(
                    '0.000000'
                );

                $table->dateTime(
                    'reserved_at'
                )->nullable();

                $table->dateTime(
                    'allocated_at'
                )->nullable();

                $table->dateTime(
                    'released_at'
                )->nullable();

                $table->timestamps();

                /*
                 * Satu source Commitment hanya
                 * muncul sekali dalam satu Offer.
                 *
                 * Multi-source tetap diperbolehkan.
                 * Commitment yang sama juga dapat
                 * menjadi source Offer lain selama
                 * aggregate capacity masih cukup.
                 */
                $table->unique(
                    [
                        'fallback_offer_id',
                        'supply_commitment_id',
                    ],
                    'fallback_offer_sources_offer_commitment_unique'
                );

                /*
                 * Query kritis M07:
                 * hitung reservation/allocation
                 * lintas Offer pada satu Commitment.
                 */
                $table->index(
                    [
                        'supply_commitment_id',
                        'fallback_offer_id',
                    ],
                    'fallback_offer_sources_commitment_offer_index'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'fallback_offer_sources'
        );
    }
};