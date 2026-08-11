<?php

namespace App\Services\Notification;

use App\Enums\NotificationPriority;
use App\Enums\NotificationType;
use App\Enums\ReadinessType;
use App\Enums\SupplyConfidence;
use App\Models\ConfidenceRecoveryRequest;
use App\Models\SupplyCommitment;
use App\Models\CommitmentVersion;
use App\Models\FallbackOffer;
use App\Models\FallbackRequest;
use App\Models\ReadinessChecklist;
use App\Models\DemandForecast;
use App\Models\CommitmentConfidenceEvent;
use App\Models\ForecastDerivedStateObservation;

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



    public function shortfallIncreased(
    DemandForecast $forecast,
    ForecastDerivedStateObservation $observation,
): void {
    $recipients =
        $this->recipientResolver
            ->primaryKdkmpOperatorsAndManagers(
                $forecast
            );

    foreach ($recipients as $recipient) {
        $this->notificationService
            ->send(
                recipient:
                    $recipient,

                type:
                    NotificationType::SHORTFALL,

                priority:
                    NotificationPriority::WARNING,

                title:
                    'Shortfall pasokan meningkat',

                message:
                    'Shortfall Forecast muncul atau '
                    .'bertambah. Evaluasi kebutuhan '
                    .'fallback dan kondisi Safe Supply.',

                relatedEntity:
                    $forecast,

                actionUrl:
                    '/kdkmp/forecasts/'
                    .$forecast->id,

                deduplicationKey:
                    'derived-observation:'
                    .$observation->id
                    .':shortfall',
            );
    }
}

public function readyForProcurementReached(
    DemandForecast $forecast,
    ForecastDerivedStateObservation $observation,
): void {
    $sppgRecipients =
        $this->recipientResolver
            ->sppgUsers(
                $forecast
                    ->sppg_organization_id
            );

    foreach ($sppgRecipients as $recipient) {
        $this->notificationService
            ->send(
                recipient:
                    $recipient,

                type:
                    NotificationType::RFP,

                priority:
                    NotificationPriority
                        ::INFORMATION,

                title:
                    'Ready for Procurement tercapai',

                message:
                    'Forecast telah memenuhi volume, '
                    .'Logistics Readiness, dan '
                    .'Document Readiness seluruh '
                    .'effective contributor.',

                relatedEntity:
                    $forecast,

                actionUrl:
                    '/sppg/forecasts/'
                    .$forecast->id,

                deduplicationKey:
                    'derived-observation:'
                    .$observation->id
                    .':rfp-reached',
            );
    }

    $managerRecipients =
        $this->recipientResolver
            ->kdkmpManagersForOrganizations(
                $observation
                    ->contributor_organization_ids
            );

    foreach ($managerRecipients as $recipient) {
        $this->notificationService
            ->send(
                recipient:
                    $recipient,

                type:
                    NotificationType::RFP,

                priority:
                    NotificationPriority
                        ::INFORMATION,

                title:
                    'Ready for Procurement tercapai',

                message:
                    'Forecast yang Anda kontribusikan '
                    .'telah memenuhi seluruh gate '
                    .'Ready for Procurement.',

                relatedEntity:
                    $forecast,

                actionUrl:
                    '/kdkmp/forecasts/'
                    .$forecast->id,

                deduplicationKey:
                    'derived-observation:'
                    .$observation->id
                    .':rfp-reached',
            );
    }
}

public function readyForProcurementLost(
    DemandForecast $forecast,
    ForecastDerivedStateObservation $observation,
    array $affectedContributorOrganizationIds,
): void {
    $sppgRecipients =
        $this->recipientResolver
            ->sppgUsers(
                $forecast
                    ->sppg_organization_id
            );

    foreach ($sppgRecipients as $recipient) {
        $this->notificationService
            ->send(
                recipient:
                    $recipient,

                type:
                    NotificationType::RFP,

                priority:
                    NotificationPriority::WARNING,

                title:
                    'Ready for Procurement tidak lagi terpenuhi',

                message:
                    'Salah satu dependency Forecast '
                    .'berubah. Lihat status terbaru '
                    .'untuk mengetahui blocker.',

                relatedEntity:
                    $forecast,

                actionUrl:
                    '/sppg/forecasts/'
                    .$forecast->id,

                deduplicationKey:
                    'derived-observation:'
                    .$observation->id
                    .':rfp-lost',
            );
    }

    $managerRecipients =
        $this->recipientResolver
            ->kdkmpManagersForOrganizations(
                $affectedContributorOrganizationIds
            );

    foreach ($managerRecipients as $recipient) {
        $this->notificationService
            ->send(
                recipient:
                    $recipient,

                type:
                    NotificationType::RFP,

                priority:
                    NotificationPriority::WARNING,

                title:
                    'Ready for Procurement hilang',

                message:
                    'Forecast tidak lagi memenuhi '
                    .'seluruh dependency Ready for '
                    .'Procurement. Tinjau supply dan '
                    .'readiness terkait.',

                relatedEntity:
                    $forecast,

                actionUrl:
                    '/kdkmp/forecasts/'
                    .$forecast->id,

                deduplicationKey:
                    'derived-observation:'
                    .$observation->id
                    .':rfp-lost',
            );
    }
}
public function supplyConfidenceDowngraded(
    SupplyCommitment $commitment,
    CommitmentConfidenceEvent $event,
): void {
    $recipients =
        $this->recipientResolver
            ->kdkmpOperatorsAndManagers(
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
                        ::SUPPLY_RISK,

                priority:
                    NotificationPriority
                        ::WARNING,

                title:
                    'Confidence pasokan menurun',

                message:
                    'Confidence Commitment berubah dari '
                    .$event
                        ->from_confidence
                        ?->value
                    .' menjadi '
                    .$event
                        ->to_confidence
                        ->value
                    .'. '
                    .$event->reason_note,

                relatedEntity:
                    $commitment,

                actionUrl:
                    '/kdkmp/commitments/'
                    .$commitment->id,

                deduplicationKey:
                    'confidence-event:'
                    .$event->id
                    .':supply-risk',
            );
    }
}

public function staleCommitmentDetected(
    SupplyCommitment $commitment,
    CommitmentConfidenceEvent $event,
): void {
    $recipients =
        $this->recipientResolver
            ->kdkmpOperators(
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
                        ::STALE_COMMITMENT,

                priority:
                    NotificationPriority
                        ::WARNING,

                title:
                    'Commitment perlu diverifikasi',

                message:
                    'Confidence Commitment diturunkan '
                    .'otomatis menjadi YELLOW karena '
                    .'data verifikasi telah melewati '
                    .'freshness interval.',

                relatedEntity:
                    $commitment,

                actionUrl:
                    '/kdkmp/commitments/'
                    .$commitment->id,

                deduplicationKey:
                    'confidence-event:'
                    .$event->id
                    .':stale',
            );
    }
}

public function confidenceRecoveryApprovalRequired(
    SupplyCommitment $commitment,
    ConfidenceRecoveryRequest $recovery,
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
                    'Recovery confidence perlu persetujuan',

                message:
                    'Operator mengajukan pemulihan '
                    .'Commitment YELLOW ke GREEN dan '
                    .'menunggu review Manager.',

                relatedEntity:
                    $recovery,

                actionUrl:
                    '/kdkmp/manager/recoveries/'
                    .$recovery->id,

                deduplicationKey:
                    'confidence-recovery:'
                    .$recovery->id
                    .':approval-required',
            );
    }
}

    public function fallbackOfferApprovalRequired(
    FallbackOffer $offer,
): void {
    $recipients =
        $this->recipientResolver
            ->kdkmpManagers(
                $offer
                    ->supplier_organization_id
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
                    'Fallback Offer perlu persetujuan',

                message:
                    'Fallback Offer telah disubmit '
                    .'dan menunggu review Manager supplier.',

                relatedEntity:
                    $offer,

                actionUrl:
                    '/kdkmp/manager/outgoing-offers',

                deduplicationKey:
                    'fallback-offer:'
                    .$offer->id
                    .':approval-required',
            );
    }
}


public function fallbackRequestApprovalRequired(
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
                        ::APPROVAL_REQUIRED,

                priority:
                    NotificationPriority
                        ::ACTION,

                title:
                    'Fallback Request perlu persetujuan',

                message:
                    'Fallback Request telah disubmit '
                    .'dan menunggu keputusan '
                    .'Manager requester.',

                relatedEntity:
                    $request,

                actionUrl:
                    '/kdkmp/manager/fallback-requests/'
                    .$request->id,

                deduplicationKey:
                    'fallback-request:'
                    .$request->id
                    .':approval-required',
            );
    }
}

public function fallbackRequestOpened(
    FallbackRequest $request,
    DemandForecast $forecast,
): void {
    $recipients =
        $this->recipientResolver
            ->fallbackNetworkRecipients(
                $forecast,
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
                        ::FALLBACK_REQUEST,

                priority:
                    NotificationPriority
                        ::ACTION,

                title:
                    'Fallback Request baru tersedia',

                message:
                    'Kebutuhan fallback baru telah '
                    .'dibuka untuk jaringan KDKMP. '
                    .'Lihat detail broadcast untuk '
                    .'menilai kapasitas yang dapat '
                    .'ditawarkan.',

                relatedEntity:
                    $request,

                actionUrl:
                    '/kdkmp/fallback-network/'
                    .$request->id,

                deduplicationKey:
                    'fallback-request:'
                    .$request->id
                    .':opened',
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