<?php

namespace App\Services\Notification;

use App\Enums\NotificationPriority;
use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class NotificationService
{
    public function send(
        User $recipient,
        NotificationType $type,
        NotificationPriority $priority,
        string $title,
        string $message,
        Model $relatedEntity,
        ?string $actionUrl = null,
        ?string $deduplicationKey = null,
    ): void {
        if (! $recipient->exists) {
            throw new InvalidArgumentException(
                'Notification recipient must be persisted.'
            );
        }

        if (! $relatedEntity->exists) {
            throw new InvalidArgumentException(
                'Notification related entity must be persisted.'
            );
        }

        $title =
            trim($title);

        $message =
            trim($message);

        $actionUrl =
            $this->nullableTrim(
                $actionUrl
            );

        $deduplicationKey =
            $this->nullableTrim(
                $deduplicationKey
            );

        if ($title === '') {
            throw new InvalidArgumentException(
                'Notification title cannot be empty.'
            );
        }

        if ($message === '') {
            throw new InvalidArgumentException(
                'Notification message cannot be empty.'
            );
        }

        /*
         * Capture scalar event data now.
         *
         * Callback tidak bergantung pada mutable
         * Eloquent object setelah transaction
         * selesai.
         */
        $recipientId =
            (int)
            $recipient->getKey();

        $relatedEntityType =
            $relatedEntity
                ->getMorphClass();

        $relatedEntityId =
            (int)
            $relatedEntity
                ->getKey();

        $connectionName =
            $relatedEntity
                ->getConnectionName();

        $persist =
            function () use (
                $connectionName,
                $recipientId,
                $type,
                $priority,
                $title,
                $message,
                $relatedEntityType,
                $relatedEntityId,
                $actionUrl,
                $deduplicationKey,
            ): void {
                $this->persist(
                    connectionName:
                        $connectionName,

                    recipientId:
                        $recipientId,

                    type:
                        $type,

                    priority:
                        $priority,

                    title:
                        $title,

                    message:
                        $message,

                    relatedEntityType:
                        $relatedEntityType,

                    relatedEntityId:
                        $relatedEntityId,

                    actionUrl:
                        $actionUrl,

                    deduplicationKey:
                        $deduplicationKey,
                );
            };

        $connection =
            DB::connection(
                $connectionName
            );

        /*
         * Critical rule M10:
         *
         * business mutation commit dahulu,
         * baru notification menjadi visible.
         */
        if (
            $connection
                ->transactionLevel() > 0
        ) {
            $connection
                ->afterCommit(
                    $persist
                );

            return;
        }

        /*
         * Non-transactional caller:
         * tidak ada commit yang perlu ditunggu.
         */
        $persist();
    }

    public function markRead(
        User $actor,
        Notification $notification,
    ): Notification {
        if (
            $notification
                ->recipient_user_id
            !== $actor->id
        ) {
            throw new AuthorizationException(
                'Notification berada di luar recipient scope.'
            );
        }

        if ($notification->isRead()) {
            return $notification;
        }

        $notification->update([
            'read_at' =>
                now(),
        ]);

        return $notification
            ->refresh();
    }

    private function persist(
        ?string $connectionName,
        int $recipientId,
        NotificationType $type,
        NotificationPriority $priority,
        string $title,
        string $message,
        string $relatedEntityType,
        int $relatedEntityId,
        ?string $actionUrl,
        ?string $deduplicationKey,
    ): void {
        $model =
            new Notification();

        $model->setConnection(
            $connectionName
        );

        $attributes = [
            'recipient_user_id' =>
                $recipientId,

            'notification_type' =>
                $type,

            'priority' =>
                $priority,

            'title' =>
                $title,

            'message' =>
                $message,

            'related_entity_type' =>
                $relatedEntityType,

            'related_entity_id' =>
                $relatedEntityId,

            'action_url' =>
                $actionUrl,

            'deduplication_key' =>
                $deduplicationKey,

            'read_at' =>
                null,

            'created_at' =>
                now(),
        ];

        /*
         * Tanpa deduplication key setiap call
         * adalah notification event baru.
         */
        if ($deduplicationKey === null) {
            $model
                ->newQuery()
                ->create(
                    $attributes
                );

            return;
        }

        /*
         * Retry event yang sama untuk recipient
         * yang sama tidak membuat row kedua.
         */
        $model
            ->newQuery()
            ->firstOrCreate(
                [
                    'recipient_user_id' =>
                        $recipientId,

                    'deduplication_key' =>
                        $deduplicationKey,
                ],
                $attributes
            );
    }

    private function nullableTrim(
        ?string $value,
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value =
            trim($value);

        return $value === ''
            ? null
            : $value;
    }
}