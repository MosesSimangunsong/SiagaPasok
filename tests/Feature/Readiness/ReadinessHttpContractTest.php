<?php

namespace Tests\Feature\Readiness;

use App\Enums\CommitmentApprovalStatus;
use App\Enums\CommitmentLifecycleStatus;
use App\Enums\ForecastStatus;
use App\Enums\NetworkRole;
use App\Enums\OrganizationType;
use App\Enums\ReadinessApprovalStatus;
use App\Enums\ReadinessType;
use App\Enums\RequirementScope;
use App\Enums\SupplyConfidence;
use App\Enums\UserRole;
use App\Models\Commodity;
use App\Models\CommitmentVersion;
use App\Models\DemandForecast;
use App\Models\DocumentRecord;
use App\Models\Organization;
use App\Models\Producer;
use App\Models\ReadinessChecklist;
use App\Models\ReadinessRequirement;
use App\Models\SupplyCommitment;
use App\Models\SupplyNetworkLink;
use App\Models\Unit;
use App\Models\User;
use App\Services\Readiness\DocumentRecordService;
use App\Services\Readiness\ReadinessChecklistPreparationService;
use App\Services\Readiness\ReadinessChecklistReviewService;
use App\Services\Readiness\ReadinessChecklistWorkflowService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReadinessHttpContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(
            CarbonImmutable::parse(
                '2026-08-10 10:00:00'
            )
        );
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_operator_can_prepare_update_and_submit_then_manager_can_approve_through_http_commands(): void
    {
        $context =
            $this->createOperationalContext(
                'HTTP-FLOW'
            );

        $this->createRequirement(
            context: $context,
            type: ReadinessType::LOGISTICS,
            code: 'LOG-HTTP-FLOW'
        );

        /*
         * Operator prepares V1 DRAFT.
         */
        $this->actingAs(
            $context['operator']
        )
            ->post(
                '/kdkmp/forecasts/'
                .$context['forecast']->id
                .'/readiness/logistics/prepare'
            )
            ->assertRedirect();

        $checklist =
            ReadinessChecklist::query()
                ->firstOrFail();

        $this->assertSame(
            ReadinessApprovalStatus::DRAFT,
            $checklist->status
        );

        $item =
            $checklist
                ->items
                ->firstOrFail();

        /*
         * DRAFT payload mutation.
         */
        $this->actingAs(
            $context['operator']
        )
            ->put(
                "/kdkmp/readiness/{$checklist->id}/items/{$item->id}",
                [
                    'is_satisfied' =>
                        true,

                    'note' =>
                        'HTTP readiness fixture satisfied.',
                ]
            )
            ->assertRedirect(
                route(
                    'kdkmp.readiness.show',
                    $checklist
                )
            );

        $item->refresh();

        $this->assertTrue(
            $item->is_satisfied
        );

        /*
         * Explicit submit command.
         */
        $this->actingAs(
            $context['operator']
        )
            ->post(
                "/kdkmp/readiness/{$checklist->id}/submit"
            )
            ->assertRedirect(
                route(
                    'kdkmp.readiness.show',
                    $checklist
                )
            );

        $checklist->refresh();

        $this->assertSame(
            ReadinessApprovalStatus
                ::PENDING_APPROVAL,
            $checklist->status
        );

        /*
         * Explicit Manager approval command.
         */
        $this->actingAs(
            $context['manager']
        )
            ->post(
                "/kdkmp/manager/readiness/{$checklist->id}/approve"
            )
            ->assertRedirect(
                route(
                    'kdkmp.manager.readiness.index'
                )
            );

        $checklist->refresh();

        $this->assertSame(
            ReadinessApprovalStatus::APPROVED,
            $checklist->status
        );

        $this->assertSame(
            $context['manager']->id,
            $checklist->reviewed_by
        );
    }

    public function test_manager_cannot_mutate_operator_payload_and_operator_cannot_use_manager_approval_command(): void
    {
        $context =
            $this->createOperationalContext(
                'ROLE-SPLIT'
            );

        $this->createRequirement(
            context: $context,
            type: ReadinessType::LOGISTICS,
            code: 'LOG-ROLE-SPLIT'
        );

        $checklist =
            app(
                ReadinessChecklistPreparationService::class
            )->createInitialDraft(
                $context['operator'],
                $context['forecast'],
                ReadinessType::LOGISTICS
            );

        $item =
            $checklist
                ->items
                ->firstOrFail();

        /*
         * Manager tidak mempunyai Operator
         * item-mutation route authority.
         */
        $this->actingAs(
            $context['manager']
        )
            ->put(
                "/kdkmp/readiness/{$checklist->id}/items/{$item->id}",
                [
                    'is_satisfied' =>
                        true,
                ]
            )
            ->assertForbidden();

        $this->assertFalse(
            $item
                ->fresh()
                ->is_satisfied
        );

        /*
         * Operator menyiapkan valid submission
         * melalui production service.
         */
        app(
            ReadinessChecklistWorkflowService::class
        )->updateItem(
            $context['operator'],
            $checklist,
            $item,
            [
                'is_satisfied' =>
                    true,
            ]
        );

        $checklist =
            app(
                ReadinessChecklistWorkflowService::class
            )->submit(
                $context['operator'],
                $checklist
            );

        /*
         * Operator tidak boleh memakai Manager
         * command walaupun mengetahui URL.
         */
        $this->actingAs(
            $context['operator']
        )
            ->post(
                "/kdkmp/manager/readiness/{$checklist->id}/approve"
            )
            ->assertForbidden();

        $this->assertSame(
            ReadinessApprovalStatus
                ::PENDING_APPROVAL,
            $checklist
                ->fresh()
                ->status
        );
    }

    public function test_cross_organization_direct_url_cannot_read_readiness_or_mutate_document_record(): void
    {
        $context =
            $this->createOperationalContext(
                'CROSS-ORG'
            );

        $this->createRequirement(
            context: $context,
            type: ReadinessType::LOGISTICS,
            code: 'LOG-CROSS-ORG'
        );

        $checklist =
            app(
                ReadinessChecklistPreparationService::class
            )->createInitialDraft(
                $context['operator'],
                $context['forecast'],
                ReadinessType::LOGISTICS
            );

        $documentRequirement =
            $this->createRequirement(
                context: $context,
                type: ReadinessType::DOCUMENT,
                code: 'DOC-CROSS-ORG',
                scope: RequirementScope::ORGANIZATION
            );

        $document =
            app(
                DocumentRecordService::class
            )->create(
                $context['operator'],
                $documentRequirement,
                [
                    'document_name' =>
                        'Private Document',

                    'reference_number' =>
                        'PRIVATE-001',

                    'valid_from' =>
                        '2026-08-01 00:00:00',

                    'expires_at' =>
                        '2026-08-31 23:59:59',

                    'notes' =>
                        'Private metadata.',
                ]
            );

        $otherKdkmp =
            $this->createOrganization(
                OrganizationType::KDKMP,
                'KDKMP-READINESS-CROSS-ORG-OTHER'
            );

        $otherOperator =
            $this->createKdkmpUser(
                $otherKdkmp,
                UserRole::KDKMP_OPERATOR
            );

        $otherManager =
            $this->createKdkmpUser(
                $otherKdkmp,
                UserRole::KDKMP_MANAGER
            );

        /*
         * Shared KDKMP detail still requires
         * tenant ownership policy.
         */
        $this->actingAs(
            $otherOperator
        )
            ->get(
                "/kdkmp/readiness/{$checklist->id}"
            )
            ->assertForbidden();

        /*
         * Manager review URL cannot bypass
         * organization boundary either.
         */
        $this->actingAs(
            $otherManager
        )
            ->get(
                "/kdkmp/manager/readiness/{$checklist->id}"
            )
            ->assertForbidden();

        /*
         * Direct Document Record URL mutation
         * also remains own-org only.
         */
        $this->actingAs(
            $otherOperator
        )
            ->put(
                "/kdkmp/documents/{$document->id}",
                [
                    'notes' =>
                        'Cross-tenant overwrite.',
                ]
            )
            ->assertForbidden();

        $document->refresh();

        $this->assertSame(
            'Private metadata.',
            $document->notes
        );

        $this->assertSame(
            1,
            $document->revision_no
        );
    }

    public function test_manager_rejection_requires_reason_at_http_boundary(): void
    {
        $context =
            $this->createOperationalContext(
                'REJECT-HTTP'
            );

        $checklist =
            $this->createSubmittedLogisticsChecklist(
                $context,
                'LOG-REJECT-HTTP'
            );

        $this->actingAs(
            $context['manager']
        )
            ->post(
                "/kdkmp/manager/readiness/{$checklist->id}/reject",
                []
            )
            ->assertSessionHasErrors(
                'review_reason'
            );

        $this->assertSame(
            ReadinessApprovalStatus
                ::PENDING_APPROVAL,
            $checklist
                ->fresh()
                ->status
        );

        $reason =
            'Jadwal logistik belum dapat dikonfirmasi.';

        $this->actingAs(
            $context['manager']
        )
            ->post(
                "/kdkmp/manager/readiness/{$checklist->id}/reject",
                [
                    'review_reason' =>
                        $reason,
                ]
            )
            ->assertRedirect(
                route(
                    'kdkmp.manager.readiness.index'
                )
            );

        $checklist->refresh();

        $this->assertSame(
            ReadinessApprovalStatus::REJECTED,
            $checklist->status
        );

        $this->assertSame(
            $reason,
            $checklist->review_reason
        );
    }

    public function test_system_admin_has_no_operational_readiness_authority(): void
    {
        $context =
            $this->createOperationalContext(
                'ADMIN-DENIAL'
            );

        $admin =
            $context['admin'];

        $this->actingAs(
            $admin
        )
            ->get(
                '/kdkmp/readiness'
            )
            ->assertForbidden();

        $this->actingAs(
            $admin
        )
            ->get(
                '/kdkmp/documents'
            )
            ->assertForbidden();

        /*
         * SPPG readiness surface is role-scoped
         * and cannot be used by System Admin.
         */
        $this->actingAs(
            $admin
        )
            ->get(
                '/sppg/forecasts/'
                .$context['forecast']->id
                .'/readiness'
            )
            ->assertForbidden();
    }

    public function test_sppg_readiness_view_exposes_derived_procurement_status_and_only_aggregate_contributor_data(): void
{
    $context =
        $this->createOperationalContext(
            'SPPG-PRIVACY'
        );

    $this->createApprovedLogisticsChecklist(
        $context,
        'LOG-SPPG-PRIVACY'
    );

    $documentContext =
        $this->createApprovedDocumentChecklist(
            $context,
            'DOC-SPPG-PRIVACY'
        );

    /*
     * Fixture:
     *
     * Demand       = 300
     * Safe Supply  = 300
     * Contributor  = current PRIMARY
     * Logistics    = READY
     * Document     = READY
     *
     * Karena itu derived M09 result harus TRUE.
     */
    $response =
        $this->actingAs(
            $context['sppgUser']
        )
            ->get(
                '/sppg/forecasts/'
                .$context['forecast']->id
                .'/readiness'
            );

    $response
        ->assertOk()
        ->assertInertia(
            fn (
                Assert $page
            ) =>
                $page
                    ->component(
                        'Sppg/Forecasts/Readiness'
                    )
                    ->where(
                        'forecast.id',
                        $context['forecast']->id
                    )
                    ->where(
                        'forecast.target_volume',
                        '300.000000'
                    )
                    ->where(
                        'supply.total_safe_supply',
                        '300.000000'
                    )
                    ->where(
                        'supply.shortfall',
                        '0.000000'
                    )
                    ->where(
                        'supply.volume_ready',
                        true
                    )
                    ->where(
                        'procurement.forecast_published',
                        true
                    )
                    ->where(
                        'procurement.operationally_valid',
                        true
                    )
                    ->where(
                        'procurement.volume_ready',
                        true
                    )
                    ->where(
                        'procurement.all_contributors_logistics_ready',
                        true
                    )
                    ->where(
                        'procurement.all_contributors_document_ready',
                        true
                    )
                    ->where(
                        'procurement.ready_for_procurement',
                        true
                    )
                    ->where(
                        'procurement.reason_codes',
                        []
                    )
                    ->has(
                        'procurement.evaluated_at'
                    )
                    ->has(
                        'contributors',
                        1
                    )
                    ->where(
                        'contributors.0.organization.id',
                        $context['kdkmp']->id
                    )
                    ->where(
                        'contributors.0.logistics_ready',
                        true
                    )
                    ->where(
                        'contributors.0.document_ready',
                        true
                    )
        );

    $props =
        $response
            ->viewData(
                'page'
            )['props'] ?? [];

    $this->assertIsArray(
        $props
    );

    /*
     * Procurement payload adalah aggregate derived
     * status. Tidak membawa internal KDKMP evidence.
     */
    $procurement =
        $props['procurement'] ?? null;

    $this->assertIsArray(
        $procurement
    );

    $this->assertSame(
        [
            'evaluated_at',
            'forecast_published',
            'operationally_valid',
            'volume_ready',
            'all_contributors_logistics_ready',
            'all_contributors_document_ready',
            'ready_for_procurement',
            'reason_codes',
        ],
        array_keys(
            $procurement
        )
    );

    $this->assertTrue(
        $procurement[
            'ready_for_procurement'
        ]
    );

    $contributors =
        $props[
            'contributors'
        ] ?? null;

    $this->assertIsArray(
        $contributors
    );

    $this->assertCount(
        1,
        $contributors
    );

    /*
     * Exact allowed contributor payload.
     *
     * Tidak ada producer, commitment,
     * checklist item, document metadata,
     * reserve ledger, atau source IDs.
     */
    $this->assertSame(
        [
            'organization',
            'logistics_ready',
            'document_ready',
            'logistics_reason_codes',
            'document_reason_codes',
        ],
        array_keys(
            $contributors[0]
        )
    );

    $this->assertSame(
        [
            'id',
            'code',
            'name',
        ],
        array_keys(
            $contributors[0][
                'organization'
            ]
        )
    );

    $serialized =
        json_encode(
            $props
        );

    $this->assertIsString(
        $serialized
    );

    /*
     * SPPG tetap tidak boleh memperoleh internal
     * producer / document / readiness evidence
     * walaupun sekarang halaman juga mengekspos
     * M09 derived status.
     */
    $this->assertStringNotContainsString(
        $context['producer']->name,
        $serialized
    );

    $this->assertStringNotContainsString(
        $documentContext[
            'document'
        ]->reference_number,
        $serialized
    );

    $this->assertStringNotContainsString(
        $documentContext[
            'document'
        ]->document_name,
        $serialized
    );

    $this->assertStringNotContainsString(
        'document_record_revision_no',
        $serialized
    );

    $this->assertStringNotContainsString(
        'readiness_checklist_id',
        $serialized
    );

    $this->assertStringNotContainsString(
        'producer_id',
        $serialized
    );

    $this->assertStringNotContainsString(
        'supply_commitment_id',
        $serialized
    );
}

public function test_sppg_forecast_detail_exposes_derived_procurement_summary_without_private_readiness_evidence(): void
{
    $context =
        $this->createOperationalContext(
            'FORECAST-RFP-SUMMARY'
        );

    $this->createApprovedLogisticsChecklist(
        $context,
        'LOG-FORECAST-RFP-SUMMARY'
    );

    $documentContext =
        $this->createApprovedDocumentChecklist(
            $context,
            'DOC-FORECAST-RFP-SUMMARY'
        );

    /*
     * Fixture canonical:
     *
     * Demand       = 300
     * Safe Supply  = 300
     * Contributors = 1
     * Logistics    = TRUE
     * Document     = TRUE
     *
     * Maka derived RFP harus TRUE.
     */
    $response =
        $this->actingAs(
            $context['sppgUser']
        )
            ->get(
                '/sppg/forecasts/'
                .$context['forecast']->id
            );

    $response
        ->assertOk()
        ->assertInertia(
            fn (
                Assert $page
            ) =>
                $page
                    ->component(
                        'Sppg/Forecasts/Show'
                    )
                    ->where(
                        'forecast.id',
                        $context['forecast']->id
                    )
                    ->where(
                        'procurementSummary.forecast_published',
                        true
                    )
                    ->where(
                        'procurementSummary.operationally_valid',
                        true
                    )
                    ->where(
                        'procurementSummary.volume_ready',
                        true
                    )
                    ->where(
                        'procurementSummary.all_contributors_logistics_ready',
                        true
                    )
                    ->where(
                        'procurementSummary.all_contributors_document_ready',
                        true
                    )
                    ->where(
                        'procurementSummary.contributor_count',
                        1
                    )
                    ->where(
                        'procurementSummary.ready_for_procurement',
                        true
                    )
                    ->where(
                        'procurementSummary.reason_codes',
                        []
                    )
                    ->has(
                        'procurementSummary.evaluated_at'
                    )
                    ->missing(
                        'procurementSummary.contributor_readiness'
                    )
                    ->missing(
                        'procurementSummary.contributor_organization_ids'
                    )
        );

    $props =
        $response
            ->viewData(
                'page'
            )['props'] ?? [];

    $this->assertIsArray(
        $props
    );

    $summary =
        $props[
            'procurementSummary'
        ] ?? null;

    $this->assertIsArray(
        $summary
    );

    /*
     * Exact summary contract.
     *
     * Detail Forecast tidak perlu mengetahui
     * siapa contributor atau internal evidence
     * di balik status tersebut.
     */
    $this->assertSame(
        [
            'evaluated_at',
            'forecast_published',
            'operationally_valid',
            'volume_ready',
            'all_contributors_logistics_ready',
            'all_contributors_document_ready',
            'contributor_count',
            'ready_for_procurement',
            'reason_codes',
        ],
        array_keys(
            $summary
        )
    );

    $serialized =
        json_encode(
            $props
        );

    $this->assertIsString(
        $serialized
    );

    $this->assertStringNotContainsString(
        $context['producer']->name,
        $serialized
    );

    $this->assertStringNotContainsString(
        $documentContext[
            'document'
        ]->reference_number,
        $serialized
    );

    $this->assertStringNotContainsString(
        $documentContext[
            'document'
        ]->document_name,
        $serialized
    );

    $this->assertStringNotContainsString(
        'document_record_revision_no',
        $serialized
    );

    $this->assertStringNotContainsString(
        'readiness_checklist_id',
        $serialized
    );

    $this->assertStringNotContainsString(
        'producer_id',
        $serialized
    );

    $this->assertStringNotContainsString(
        'supply_commitment_id',
        $serialized
    );
}

    public function test_sppg_cannot_open_private_kdkmp_readiness_workspace_by_direct_url(): void
    {
        $context =
            $this->createOperationalContext(
                'SPPG-DIRECT'
            );

        $this->createRequirement(
            context: $context,
            type: ReadinessType::LOGISTICS,
            code: 'LOG-SPPG-DIRECT'
        );

        $checklist =
            app(
                ReadinessChecklistPreparationService::class
            )->createInitialDraft(
                $context['operator'],
                $context['forecast'],
                ReadinessType::LOGISTICS
            );

        $this->actingAs(
            $context['sppgUser']
        )
            ->get(
                "/kdkmp/readiness/{$checklist->id}"
            )
            ->assertForbidden();

        $this->actingAs(
            $context['sppgUser']
        )
            ->get(
                '/kdkmp/documents'
            )
            ->assertForbidden();
    }

    public function test_readiness_http_surface_has_no_delete_or_manual_ready_command(): void
    {
        $routes =
            collect(
                Route::getRoutes()
                    ->getRoutes()
            );

        $readinessRoutes =
            $routes
                ->filter(
                    fn ($route): bool =>
                        str_contains(
                            $route->uri(),
                            'readiness'
                        )
                );

        $documentRoutes =
            $routes
                ->filter(
                    fn ($route): bool =>
                        str_starts_with(
                            $route->uri(),
                            'kdkmp/documents'
                        )
                );

        $this->assertFalse(
            $readinessRoutes
                ->contains(
                    fn ($route): bool =>
                        in_array(
                            'DELETE',
                            $route->methods(),
                            true
                        )
                )
        );

        $this->assertFalse(
            $documentRoutes
                ->contains(
                    fn ($route): bool =>
                        in_array(
                            'DELETE',
                            $route->methods(),
                            true
                        )
                )
        );

        foreach (
            $readinessRoutes
            as $route
        ) {
            $uri =
                strtolower(
                    $route->uri()
                );

            $name =
                strtolower(
                    (string)
                    $route->getName()
                );

            $this->assertStringNotContainsString(
                'set-ready',
                $uri
            );

            $this->assertStringNotContainsString(
                'toggle-ready',
                $uri
            );

            $this->assertStringNotContainsString(
                'set-ready',
                $name
            );

            $this->assertStringNotContainsString(
                'toggle-ready',
                $name
            );
        }
    }


    public function test_kdkmp_forecast_detail_exposes_canonical_readiness_entry_context(): void
{
    $context =
        $this->createOperationalContext(
            'FORECAST-READINESS-ENTRY'
        );

    $logisticsRequirement =
        $this->createRequirement(
            context: $context,
            type:
                ReadinessType::LOGISTICS,
            code:
                'LOG-FORECAST-ENTRY'
        );

    /*
     * Direct approved GREEN supply dari fixture
     * membuat KDKMP ini current contributor.
     *
     * Belum ada checklist, sehingga Operator
     * harus memperoleh prepare action.
     */
    $response =
        $this->actingAs(
            $context['operator']
        )
            ->get(
                '/kdkmp/forecasts/'
                .$context['forecast']->id
            );

    $response
        ->assertOk()
        ->assertInertia(
            fn (
                Assert $page
            ) =>
                $page
                    ->component(
                        'Kdkmp/Forecasts/Show'
                    )
                    ->where(
                        'forecast.id',
                        $context['forecast']->id
                    )
                    ->where(
                        'readinessContext.is_contributor',
                        true
                    )
                    ->where(
                        'readinessContext.logistics.checklist_id',
                        null
                    )
                    ->where(
                        'readinessContext.logistics.can_prepare',
                        true
                    )
                    ->where(
                        'readinessContext.logistics.can_open',
                        false
                    )
                    ->where(
                        'readinessContext.logistics.ready',
                        false
                    )
                    ->where(
                        'readinessContext.document.checklist_id',
                        null
                    )
                    ->where(
                        'readinessContext.document.can_prepare',
                        true
                    )
                    ->where(
                        'readinessContext.document.can_open',
                        false
                    )
        );

    /*
     * Prepare melalui HTTP command.
     */
    $this->actingAs(
        $context['operator']
    )
        ->post(
            '/kdkmp/forecasts/'
            .$context['forecast']->id
            .'/readiness/logistics/prepare'
        )
        ->assertRedirect();

    $checklist =
        ReadinessChecklist::query()
            ->where(
                'forecast_id',
                $context['forecast']->id
            )
            ->where(
                'organization_id',
                $context['kdkmp']->id
            )
            ->where(
                'readiness_type',
                ReadinessType::LOGISTICS->value
            )
            ->where(
                'is_current_version',
                true
            )
            ->firstOrFail();

    $this->assertSame(
        $logisticsRequirement->id,
        $checklist
            ->items
            ->firstOrFail()
            ->requirement_id
    );

    /*
     * Forecast page sekarang harus beralih
     * dari Prepare → Open.
     */
    $this->actingAs(
        $context['operator']
    )
        ->get(
            '/kdkmp/forecasts/'
            .$context['forecast']->id
        )
        ->assertOk()
        ->assertInertia(
            fn (
                Assert $page
            ) =>
                $page
                    ->component(
                        'Kdkmp/Forecasts/Show'
                    )
                    ->where(
                        'readinessContext.is_contributor',
                        true
                    )
                    ->where(
                        'readinessContext.logistics.checklist_id',
                        $checklist->id
                    )
                    ->where(
                        'readinessContext.logistics.version_no',
                        1
                    )
                    ->where(
                        'readinessContext.logistics.status',
                        ReadinessApprovalStatus
                            ::DRAFT
                            ->value
                    )
                    ->where(
                        'readinessContext.logistics.can_prepare',
                        false
                    )
                    ->where(
                        'readinessContext.logistics.can_open',
                        true
                    )
                    ->where(
                        'readinessContext.logistics.ready',
                        false
                    )
        );
}

public function test_manager_readiness_pages_render_real_inertia_components_with_read_only_review_contract(): void
{
    $context =
        $this->createOperationalContext(
            'MANAGER-READINESS-UI'
        );

    $checklist =
        $this->createSubmittedLogisticsChecklist(
            $context,
            'LOG-MANAGER-READINESS-UI'
        );

    /*
     * Manager queue.
     */
    $this->actingAs(
        $context['manager']
    )
        ->get(
            '/kdkmp/manager/readiness'
        )
        ->assertOk()
        ->assertInertia(
            fn (
                Assert $page
            ) =>
                $page
                    ->component(
                        'Kdkmp/Manager/Readiness/Index'
                    )
                    ->has(
                        'checklists',
                        1
                    )
                    ->where(
                        'checklists.0.id',
                        $checklist->id
                    )
                    ->where(
                        'checklists.0.readiness_type',
                        ReadinessType::LOGISTICS->value
                    )
                    ->where(
                        'checklists.0.status',
                        ReadinessApprovalStatus
                            ::PENDING_APPROVAL
                            ->value
                    )
                    ->where(
                        'checklists.0.submitted_by.id',
                        $context['operator']->id
                    )
        );

    /*
     * Read-only review page.
     */
    $response =
        $this->actingAs(
            $context['manager']
        )
            ->get(
                '/kdkmp/manager/readiness/'
                .$checklist->id
            );

    $response
        ->assertOk()
        ->assertInertia(
            fn (
                Assert $page
            ) =>
                $page
                    ->component(
                        'Kdkmp/Manager/Readiness/Show'
                    )
                    ->where(
                        'review.id',
                        $checklist->id
                    )
                    ->where(
                        'review.status',
                        ReadinessApprovalStatus
                            ::PENDING_APPROVAL
                            ->value
                    )
                    ->where(
                        'review.prepared_by.id',
                        $context['operator']->id
                    )
                    ->where(
                        'review.submitted_by.id',
                        $context['operator']->id
                    )
                    ->has(
                        'review.items',
                        1
                    )
                    ->where(
                        'can.approve',
                        true
                    )
                    ->where(
                        'can.reject',
                        true
                    )
        );

    /*
     * Manager surface hanya mendapat decision
     * permissions. Tidak ada payload edit authority
     * yang dikirim oleh controller.
     */
    $props =
        $response
            ->viewData(
                'page'
            )['props'] ?? [];

    $this->assertSame(
        [
            'approve',
            'reject',
        ],
        array_keys(
            $props['can'] ?? []
        )
    );
}

public function test_document_workspace_renders_only_reusable_organization_scoped_requirements(): void
{
    $context =
        $this->createOperationalContext(
            'DOCUMENT-WORKSPACE'
        );

    $organizationRequirement =
        $this->createRequirement(
            context: $context,
            type:
                ReadinessType::DOCUMENT,
            code:
                'DOC-ORG-WORKSPACE',
            scope:
                RequirementScope::ORGANIZATION
        );

    $forecastRequirement =
        $this->createRequirement(
            context: $context,
            type:
                ReadinessType::DOCUMENT,
            code:
                'DOC-FORECAST-WORKSPACE',
            scope:
                RequirementScope::FORECAST
        );

    $response =
        $this->actingAs(
            $context['operator']
        )
            ->get(
                '/kdkmp/documents'
            );

    $response
        ->assertOk()
        ->assertInertia(
            fn (
                Assert $page
            ) =>
                $page
                    ->component(
                        'Kdkmp/Documents/Index'
                    )
                    ->has(
                        'records',
                        0
                    )
                    ->has(
                        'requirements',
                        1
                    )
                    ->where(
                        'requirements.0.id',
                        $organizationRequirement->id
                    )
                    ->where(
                        'requirements.0.requirement_code',
                        'DOC-ORG-WORKSPACE'
                    )
                    ->where(
                        'requirements.0.scope',
                        RequirementScope
                            ::ORGANIZATION
                            ->value
                    )
        );

    /*
     * Forecast-specific requirement tetap legal
     * untuk Document Readiness checklist,
     * tetapi tidak boleh ditawarkan sebagai
     * reusable organization Document Record.
     */
    $serialized =
        json_encode(
            $response
                ->viewData(
                    'page'
                )['props'] ?? []
        );

    $this->assertIsString(
        $serialized
    );

    $this->assertStringNotContainsString(
        (string)
        $forecastRequirement->id,
        json_encode(
            $response
                ->viewData(
                    'page'
                )['props'][
                    'requirements'
                ] ?? []
        )
    );

    $this->assertStringNotContainsString(
        'DOC-FORECAST-WORKSPACE',
        $serialized
    );
}

  public function test_operator_dashboard_exposes_canonical_supply_and_action_queue_contract(): void
{
    $context =
        $this->createOperationalContext(
            'OPERATOR-DASHBOARD'
        );

    /*
     * Approved 300 kg Commitment dari fixture
     * diturunkan dari GREEN menjadi YELLOW.
     *
     * Canonical M06 harus menghasilkan:
     * - Safe Supply = 0;
     * - At-Risk = 300;
     * - Shortfall = 300;
     * - contributor set kosong.
     *
     * Dashboard tidak boleh menghitung ulang
     * keadaan ini.
     */
    $context['commitment']->update([
        'current_confidence' =>
            SupplyConfidence::YELLOW,
    ]);

    $this->actingAs(
        $context['operator']
    )
        ->get(
            '/kdkmp/operator'
        )
        ->assertOk()
        ->assertInertia(
            fn (
                Assert $page
            ) =>
                $page
                    ->component(
                        'Kdkmp/Operator/Dashboard'
                    )
                    ->where(
                        'organization.id',
                        $context['kdkmp']->id
                    )
                    ->where(
                        'summary.active_forecast_count',
                        1
                    )
                    ->where(
                        'summary.action_count',
                        2
                    )
                    ->has(
                        'primaryForecasts',
                        1
                    )
                    ->where(
                        'primaryForecasts.0.forecast.id',
                        $context['forecast']->id
                    )
                    ->where(
                        'primaryForecasts.0.procurement_state.total_safe_supply',
                        '0.000000'
                    )
                    ->where(
                        'primaryForecasts.0.procurement_state.at_risk_supply',
                        '300.000000'
                    )
                    ->where(
                        'primaryForecasts.0.procurement_state.shortfall',
                        '300.000000'
                    )
                    ->where(
                        'primaryForecasts.0.procurement_state.volume_ready',
                        false
                    )
                    ->where(
                        'primaryForecasts.0.procurement_state.ready_for_procurement',
                        false
                    )
                    ->where(
                        'actionQueue.0.kind',
                        'SUPPLY_RISK'
                    )
                    ->where(
                        'actionQueue.1.kind',
                        'FALLBACK_SHORTFALL'
                    )
        );
}

    private function createSubmittedLogisticsChecklist(
        array $context,
        string $requirementCode,
    ): ReadinessChecklist {
        $this->createRequirement(
            context: $context,
            type:
                ReadinessType::LOGISTICS,
            code:
                $requirementCode
        );

        $checklist =
            app(
                ReadinessChecklistPreparationService::class
            )->createInitialDraft(
                $context['operator'],
                $context['forecast'],
                ReadinessType::LOGISTICS
            );

        $item =
            $checklist
                ->items
                ->firstOrFail();

        app(
            ReadinessChecklistWorkflowService::class
        )->updateItem(
            $context['operator'],
            $checklist,
            $item,
            [
                'is_satisfied' =>
                    true,

                'note' =>
                    'HTTP fixture satisfied.',
            ]
        );

        return app(
            ReadinessChecklistWorkflowService::class
        )->submit(
            $context['operator'],
            $checklist
        );
    }

    private function createApprovedLogisticsChecklist(
        array $context,
        string $requirementCode,
    ): ReadinessChecklist {
        $checklist =
            $this->createSubmittedLogisticsChecklist(
                $context,
                $requirementCode
            );

        return app(
            ReadinessChecklistReviewService::class
        )->approve(
            $context['manager'],
            $checklist
        );
    }

    private function createApprovedDocumentChecklist(
        array $context,
        string $requirementCode,
    ): array {
        $requirement =
            $this->createRequirement(
                context: $context,
                type:
                    ReadinessType::DOCUMENT,
                code:
                    $requirementCode,
                scope:
                    RequirementScope::ORGANIZATION
            );

        $document =
            app(
                DocumentRecordService::class
            )->create(
                $context['operator'],
                $requirement,
                [
                    'document_name' =>
                        'Private Document '
                        .$requirementCode,

                    'reference_number' =>
                        'PRIVATE-REF-'
                        .$requirementCode,

                    'valid_from' =>
                        '2026-08-01 00:00:00',

                    'expires_at' =>
                        '2026-08-25 17:00:00',

                    'notes' =>
                        'Private KDKMP evidence.',
                ]
            );

        $document =
            app(
                DocumentRecordService::class
            )->markValid(
                $context['operator'],
                $document
            );

        $checklist =
            app(
                ReadinessChecklistPreparationService::class
            )->createInitialDraft(
                $context['operator'],
                $context['forecast'],
                ReadinessType::DOCUMENT
            );

        $item =
            $checklist
                ->items
                ->firstOrFail();

        app(
            ReadinessChecklistWorkflowService::class
        )->updateItem(
            $context['operator'],
            $checklist,
            $item,
            [
                'is_satisfied' =>
                    true,

                'document_record_id' =>
                    $document->id,

                'note' =>
                    'Document evidence linked.',
            ]
        );

        $checklist =
            app(
                ReadinessChecklistWorkflowService::class
            )->submit(
                $context['operator'],
                $checklist
            );

        $checklist =
            app(
                ReadinessChecklistReviewService::class
            )->approve(
                $context['manager'],
                $checklist
            );

        return [
            'requirement' =>
                $requirement,

            'document' =>
                $document->fresh(),

            'checklist' =>
                $checklist,
        ];
    }

    private function createOperationalContext(
        string $suffix,
    ): array {
        $unit =
            Unit::create([
                'code' =>
                    "kg-readiness-http-{$suffix}",

                'name' =>
                    "Kilogram Readiness HTTP {$suffix}",

                'symbol' =>
                    'kg',

                'decimal_precision' =>
                    6,

                'is_active' =>
                    true,
            ]);

        $commodity =
            Commodity::create([
                'code' =>
                    "COM-READINESS-HTTP-{$suffix}",

                'name' =>
                    "Commodity Readiness HTTP {$suffix}",

                'default_unit_id' =>
                    $unit->id,

                'harvest_behavior' =>
                    null,

                'notes' =>
                    null,

                'is_active' =>
                    true,
            ]);

        $admin =
            User::factory()->create([
                'organization_id' =>
                    null,

                'role' =>
                    UserRole::SYSTEM_ADMIN,

                'is_active' =>
                    true,
            ]);

        $sppg =
            $this->createOrganization(
                OrganizationType::SPPG,
                "SPPG-READINESS-HTTP-{$suffix}"
            );

        $kdkmp =
            $this->createOrganization(
                OrganizationType::KDKMP,
                "KDKMP-READINESS-HTTP-{$suffix}"
            );

        SupplyNetworkLink::create([
            'sppg_organization_id' =>
                $sppg->id,

            'kdkmp_organization_id' =>
                $kdkmp->id,

            'network_role' =>
                NetworkRole::PRIMARY,

            'is_active' =>
                true,

            'configured_by' =>
                $admin->id,
        ]);

        $sppgUser =
            User::factory()->create([
                'organization_id' =>
                    $sppg->id,

                'role' =>
                    UserRole::SPPG_USER,

                'is_active' =>
                    true,
            ]);

        $operator =
            $this->createKdkmpUser(
                $kdkmp,
                UserRole::KDKMP_OPERATOR
            );

        $manager =
            $this->createKdkmpUser(
                $kdkmp,
                UserRole::KDKMP_MANAGER
            );

        $forecast =
            DemandForecast::create([
                'sppg_organization_id' =>
                    $sppg->id,

                'commodity_id' =>
                    $commodity->id,

                'unit_id' =>
                    $unit->id,

                'forecast_code' =>
                    "FRC-READINESS-HTTP-{$suffix}",

                'target_volume' =>
                    '300.000000',

                'required_start_at' =>
                    '2026-08-20 08:00:00',

                'required_end_at' =>
                    '2026-08-25 17:00:00',

                'freshness_interval_hours' =>
                    24,

                'status' =>
                    ForecastStatus::PUBLISHED,

                'notes' =>
                    'Readiness HTTP fixture.',

                'published_at' =>
                    '2026-08-10 08:00:00',

                'version' =>
                    1,

                'created_by' =>
                    $sppgUser->id,

                'updated_by' =>
                    $sppgUser->id,
            ]);

        $producer =
            Producer::create([
                'organization_id' =>
                    $kdkmp->id,

                'producer_code' =>
                    "PROD-READINESS-HTTP-{$suffix}",

                'name' =>
                    "Private Producer HTTP {$suffix}",

                'village' =>
                    'Desa Privat',

                'district' =>
                    'Kecamatan Privat',

                'contact_phone' =>
                    '081234567890',

                'notes' =>
                    'Producer private fixture.',

                'is_active' =>
                    true,

                'created_by' =>
                    $operator->id,
            ]);

        $commitment =
            SupplyCommitment::create([
                'forecast_id' =>
                    $forecast->id,

                'organization_id' =>
                    $kdkmp->id,

                'producer_id' =>
                    $producer->id,

                'expected_harvest_id' =>
                    null,

                'commodity_id' =>
                    $commodity->id,

                'active_version_id' =>
                    null,

                'lifecycle_status' =>
                    CommitmentLifecycleStatus::ACTIVE,

                'current_confidence' =>
                    SupplyConfidence::GREEN,

                'last_confidence_verified_at' =>
                    '2026-08-10 09:00:00',

                'created_by' =>
                    $operator->id,
            ]);

        $commitmentVersion =
            CommitmentVersion::create([
                'commitment_id' =>
                    $commitment->id,

                'version_no' =>
                    1,

                'min_volume' =>
                    '300.000000',

                'max_volume' =>
                    '350.000000',

                'unit_id' =>
                    $unit->id,

                'availability_start_at' =>
                    '2026-08-20 07:00:00',

                'availability_end_at' =>
                    '2026-08-25 18:00:00',

                'notes' =>
                    'Approved Safe Supply fixture.',

                'approval_status' =>
                    CommitmentApprovalStatus::APPROVED,

                'change_reason' =>
                    null,

                'operator_justification' =>
                    null,

                'created_by' =>
                    $operator->id,

                'submitted_by' =>
                    $operator->id,

                'submitted_at' =>
                    '2026-08-10 08:30:00',

                'reviewed_by' =>
                    $manager->id,

                'reviewed_at' =>
                    '2026-08-10 09:00:00',

                'review_reason' =>
                    null,

                'approved_at' =>
                    '2026-08-10 09:00:00',

                'created_at' =>
                    '2026-08-10 08:00:00',
            ]);

        $commitment->update([
            'active_version_id' =>
                $commitmentVersion->id,
        ]);

        return [
            'unit' =>
                $unit,

            'commodity' =>
                $commodity,

            'admin' =>
                $admin,

            'sppg' =>
                $sppg,

            'kdkmp' =>
                $kdkmp,

            'sppgUser' =>
                $sppgUser,

            'operator' =>
                $operator,

            'manager' =>
                $manager,

            'forecast' =>
                $forecast,

            'producer' =>
                $producer,

            'commitment' =>
                $commitment,
        ];
    }

    private function createRequirement(
        array $context,
        ReadinessType $type,
        string $code,
        RequirementScope $scope =
            RequirementScope::FORECAST,
    ): ReadinessRequirement {
        return ReadinessRequirement::create([
            'readiness_type' =>
                $type,

            'requirement_code' =>
                $code,

            'label' =>
                "Requirement {$code}",

            'requirement_scope' =>
                $scope,

            'applies_to_organization_type' =>
                OrganizationType::KDKMP,

            /*
             * Commodity-scoped supaya fixture
             * tetap deterministic bila beberapa
             * context hidup dalam satu test.
             */
            'commodity_id' =>
                $context['commodity']->id,

            'is_required_default' =>
                true,

            'is_active' =>
                true,

            'sort_order' =>
                10,

            'config_json' =>
                null,
        ]);
    }

    private function createOrganization(
        OrganizationType $type,
        string $code,
    ): Organization {
        return Organization::create([
            'code' =>
                $code,

            'name' =>
                "Organization {$code}",

            'organization_type' =>
                $type,

            'is_active' =>
                true,

            'general_location' =>
                'Lokasi Test',
        ]);
    }

    private function createKdkmpUser(
        Organization $organization,
        UserRole $role,
    ): User {
        return User::factory()->create([
            'organization_id' =>
                $organization->id,

            'role' =>
                $role,

            'is_active' =>
                true,
        ]);
    }
}