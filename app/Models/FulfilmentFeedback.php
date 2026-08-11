<?php

namespace App\Models;

use App\Enums\FulfilmentResult;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'forecast_id',
    'contributor_organization_id',
    'unit_id',
    'planned_volume_snapshot',
    'delivered_volume',
    'fulfilment_date',
    'result',
    'reason_note',
    'recorded_by',
    'recorded_at',
])]
class FulfilmentFeedback extends Model
{
  protected $table = 'fulfilment_feedbacks';
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'planned_volume_snapshot' =>
                'decimal:6',

            'delivered_volume' =>
                'decimal:6',

            'fulfilment_date' =>
                'date',

            'result' =>
                FulfilmentResult::class,

            'recorded_at' =>
                'datetime',

            'created_at' =>
                'datetime',
        ];
    }

    public function forecast(): BelongsTo
    {
        return $this->belongsTo(
            DemandForecast::class,
            'forecast_id'
        );
    }

    public function contributorOrganization():
        BelongsTo
    {
        return $this->belongsTo(
            Organization::class,
            'contributor_organization_id'
        );
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(
            Unit::class,
            'unit_id'
        );
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'recorded_by'
        );
    }
}