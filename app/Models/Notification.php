<?php

namespace App\Models;

use App\Enums\NotificationPriority;
use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'recipient_user_id',
    'notification_type',
    'priority',
    'title',
    'message',
    'related_entity_type',
    'related_entity_id',
    'action_url',
    'deduplication_key',
    'read_at',
    'created_at',
])]
class Notification extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'notification_type' =>
                NotificationType::class,

            'priority' =>
                NotificationPriority::class,

            'read_at' =>
                'datetime',

            'created_at' =>
                'datetime',
        ];
    }

    public function recipientUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'recipient_user_id'
        );
    }

    public function relatedEntity(): MorphTo
    {
        return $this->morphTo(
            'relatedEntity',
            'related_entity_type',
            'related_entity_id'
        );
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function isUnread(): bool
    {
        return ! $this->isRead();
    }
}