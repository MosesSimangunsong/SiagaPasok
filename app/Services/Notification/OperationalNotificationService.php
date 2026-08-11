<?php

namespace App\Services\Notification;

use App\Enums\NotificationPriority;
use App\Enums\NotificationType;
use App\Enums\ReadinessType;
use App\Models\CommitmentVersion;
use App\Models\FallbackOffer;
use App\Models\FallbackRequest;
use App\Models\ReadinessChecklist;
use App\Models\SupplyCommitment;

final class OperationalNotificationService
{
    public function __construct(
        private readonly NotificationService
            $notificationService,

        private readonly NotificationRecipientResolver
            $recipientResolver,
    ) {
    }

    public function commitmentApprovalRequired(
        SupplyCommitment $commitment,
        CommitmentVersion $version,
    ): void {
        $recipients =
            $this->recipientResolver
                ->kdkmpManagers(
                    $commitment
                        ->organization_id
                );

        foreach ($recipients as $recipient) {
            $this->notificationService
                ->send(
                    recipient:
                        $recipient,

                    type:
                        NotificationType
                            ::APPROVAL_REQUIRED,

                    priority:
                        NotificationPriority
                            ::ACTION,

                    title:
                        'Commitment perlu persetujuan',

                    message:
                        'Commitment Version '
                        .$version->version_no
                        .' telah disubmit dan menunggu '
                        .'keputusan Manager.',

                    relatedEntity:
                        $version,

                    actionUrl:
                        '/kdkmp/manager/approvals/'
                        .$commitment->id
                        .'/versions/'
                        .$version->id,

                    deduplicationKey:
                        'commitment-version:'
                        .$version->id
                        .':approval-required',
                );
        }
    }

    public function fallbackOfferDecisionRequired(
        FallbackOffer $offer,
        FallbackRequest $request,
    ): void {
        $recipients =
            $this->recipientResolver
                ->kdkmpManagers(
                    $request
                        ->requester_organization_id
                );

        foreach ($recipients as $recipient) {
            $this->notificationService
                ->send(
                    recipient:
                        $recipient,

                    type:
                        NotificationType
                            ::FALLBACK_OFFER_DECISION,

                    priority:
                        NotificationPriority
                            ::ACTION,

                    title:
                        'Fallback Offer tersedia',

                    message:
                        'Fallback Offer telah disetujui '
                        .'oleh supplier dan menunggu '
                        .'keputusan Accept atau Reject.',

                    relatedEntity:
                        $offer,

                    actionUrl:
                        '/kdkmp/manager/incoming-offers/'
                        .$offer->id,

                    deduplicationKey:
                        'fallback-offer:'
                        .$offer->id
                        .':available',
                );
        }
    }

    public function readinessApprovalRequired(
        ReadinessChecklist $checklist,
    ): void {
        $recipients =
            $this->recipientResolver
                ->kdkmpManagers(
                    $checklist
                        ->organization_id
                );

        $readinessLabel =
            match (
                $checklist
                    ->readiness_type
            ) {
                ReadinessType::LOGISTICS =>
                    'Logistics Readiness',

                ReadinessType::DOCUMENT =>
                    'Document Readiness',
            };

        foreach ($recipients as $recipient) {
            $this->notificationService
                ->send(
                    recipient:
                        $recipient,

                    type:
                        NotificationType::READINESS,

                    priority:
                        NotificationPriority
                            ::ACTION,

                    title:
                        $readinessLabel
                        .' perlu persetujuan',

                    message:
                        $readinessLabel
                        .' telah disubmit dan menunggu '
                        .'keputusan Manager.',

                    relatedEntity:
                        $checklist,

                    actionUrl:
                        '/kdkmp/manager/readiness/'
                        .$checklist->id,

                    deduplicationKey:
                        'readiness-checklist:'
                        .$checklist->id
                        .':submitted',
                );
        }
    }
}