<?php

namespace Tests\Feature\Demo;

use App\Enums\ReadinessType;
use App\Models\DemandForecast;
use App\Models\ReadinessChecklist;
use App\Models\User;
use App\Services\Demo\DemoFallbackOfferService;
use App\Services\Demo\DemoFallbackRequestService;
use App\Services\Demo\DemoFallbackSourceService;
use App\Services\Demo\DemoSupplyRiskService;
use App\Services\Readiness\ReadyForProcurementEvaluationService;
use App\Support\Demo\DemoIdentifiers;
use App\Support\Demo\DemoScenarioActionResolver;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoMultiContributorReadinessStageTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_demo_route_is_unavailable_when_demo_mode_is_disabled(): void
    {
        $context =
            $this->recoveredScenario();

        config()->set(
            'siagapasok.demo.enabled',
            false
        );

        $this->actingAs(
            $context['primary_operator']
        )
            ->post(
                route(
                    'demo.scenario.readiness.logistics.prepare'
                )
            )
            ->assertNotFound();

        $this->assertDatabaseCount(
            'readiness_checklists',
            0
        );
    }

    public function test_both_contributors_complete_readiness_before_rfp_becomes_true(): void
    {
        $context =
            $this->recoveredScenario();

        $evaluationService =
            app(
                ReadyForProcurementEvaluationService::class
            );

        $before =
            $evaluationService->evaluate(
                $context['forecast']
            );

        $this->assertSame(
            '400.000000',
            $before->totalSafeSupply
        );

        $this->assertSame(
            '0.000000',
            $before->shortfall
        );

        $this->assertTrue(
            $before->volumeReady
        );

        $this->assertCount(
            2,
            $before
                ->contributorOrganizationIds
        );

        $this->assertFalse(
            $before
                ->allContributorsLogisticsReady
        );

        $this->assertFalse(
            $before
                ->allContributorsDocumentReady
        );

        $this->assertFalse(
            $before->readyForProcurement
        );

        $this->assertContains(
            ReadyForProcurementEvaluationService
                ::REASON_LOGISTICS_NOT_READY,
            $before->reasonCodes
        );

        $this->assertContains(
            ReadyForProcurementEvaluationService
                ::REASON_DOCUMENT_NOT_READY,
            $before->reasonCodes
        );

        $resolver =
            app(
                DemoScenarioActionResolver::class
            );

        $primaryAction =
            $resolver->resolve(
                $context['primary_operator']
            );

        $networkAction =
            $resolver->resolve(
                $context['network_operator']
            );

        $this->assertSame(
            'readiness_logistics_prepare',
            $primaryAction['key']
        );

        $this->assertSame(
            'readiness_logistics_prepare',
            $networkAction['key']
        );

        $this->completeContributorReadiness(
            operator:
                $context['primary_operator'],
            manager:
                $context['primary_manager'],
        );

        $middle =
            $evaluationService->evaluate(
                $context['forecast']
            );

        $this->assertFalse(
            $middle->readyForProcurement
        );

        $this->assertTrue(
            $middle->volumeReady
        );

        $this->completeContributorReadiness(
            operator:
                $context['network_operator'],
            manager:
                $context['network_manager'],
        );

        $this->assertDatabaseCount(
            'readiness_checklists',
            4
        );

        $organizationPairs = [
            [
                'operator' =>
                    $context[
                        'primary_operator'
                    ],

                'manager' =>
                    $context[
                        'primary_manager'
                    ],
            ],
            [
                'operator' =>
                    $context[
                        'network_operator'
                    ],

                'manager' =>
                    $context[
                        'network_manager'
                    ],
            ],
        ];

        foreach (
            $organizationPairs
            as $pair
        ) {
            foreach (
                [
                    ReadinessType::LOGISTICS,
                    ReadinessType::DOCUMENT,
                ]
                as $type
            ) {
                $checklist =
                    ReadinessChecklist::query()
                        ->with(
                            'items.requirement'
                        )
                        ->where(
                            'forecast_id',
                            $context[
                                'forecast'
                            ]->id
                        )
                        ->where(
                            'organization_id',
                            $pair[
                                'operator'
                            ]->organization_id
                        )
                        ->where(
                            'readiness_type',
                            $type->value
                        )
                        ->where(
                            'is_current_version',
                            true
                        )
                        ->firstOrFail();

                $this->assertTrue(
                    $checklist->isApproved()
                );

                $this->assertSame(
                    1,
                    $checklist->version_no
                );

                $this->assertSame(
                    $pair['operator']->id,
                    $checklist->prepared_by
                );

                $this->assertSame(
                    $pair['operator']->id,
                    $checklist->submitted_by
                );

                $this->assertSame(
                    $pair['manager']->id,
                    $checklist->reviewed_by
                );

                $this->assertNotSame(
                    $checklist->submitted_by,
                    $checklist->reviewed_by
                );

                $this->assertCount(
                    1,
                    $checklist->items
                );

                $this->assertTrue(
                    $checklist
                        ->items
                        ->first()
                        ->is_satisfied
                );
            }
        }

        $after =
            $evaluationService->evaluate(
                $context['forecast']
            );

        $this->assertSame(
            '400.000000',
            $after->totalSafeSupply
        );

        $this->assertSame(
            '150.000000',
            $after->atRiskSupply
        );

        $this->assertSame(
            '0.000000',
            $after->shortfall
        );

        $this->assertTrue(
            $after->volumeReady
        );

        $this->assertTrue(
            $after
                ->allContributorsLogisticsReady
        );

        $this->assertTrue(
            $after
                ->allContributorsDocumentReady
        );

        $this->assertTrue(
            $after->readyForProcurement
        );

        $this->assertSame(
            [],
            $after->reasonCodes
        );

        $this->assertCount(
            2,
            $after
                ->contributorReadinessResults
        );

        foreach (
            $after
                ->contributorReadinessResults
            as $contributorReadiness
        ) {
            $this->assertTrue(
                $contributorReadiness
                    ->allReady()
            );
        }

        $this->assertSame(
            4,
            DB::table(
                'audit_logs'
            )
                ->where(
                    'action',
                    'READINESS_APPROVED'
                )
                ->count()
        );

        $this->assertNull(
            $resolver->resolve(
                $context['primary_operator']
            )
        );

        $this->assertNull(
            $resolver->resolve(
                $context['primary_manager']
            )
        );

        $this->assertNull(
            $resolver->resolve(
                $context['network_operator']
            )
        );

        $this->assertNull(
            $resolver->resolve(
                $context['network_manager']
            )
        );
    }

    private function completeContributorReadiness(
        User $operator,
        User $manager
    ): void {
        $this->actingAs(
            $operator
        )
            ->post(
                route(
                    'demo.scenario.readiness.logistics.prepare'
                )
            )
            ->assertRedirect(
                route('home')
            );

        $this->actingAs(
            $manager
        )
            ->post(
                route(
                    'demo.scenario.readiness.logistics.approve'
                )
            )
            ->assertRedirect(
                route('home')
            );

        $this->actingAs(
            $operator
        )
            ->post(
                route(
                    'demo.scenario.readiness.document.prepare'
                )
            )
            ->assertRedirect(
                route('home')
            );

        $this->actingAs(
            $manager
        )
            ->post(
                route(
                    'demo.scenario.readiness.document.approve'
                )
            )
            ->assertRedirect(
                route('home')
            );
    }

    /**
     * @return array{
     *     primary_operator: User,
     *     primary_manager: User,
     *     network_operator: User,
     *     network_manager: User,
     *     forecast: DemandForecast
     * }
     */
    private function recoveredScenario(): array
    {
        config()->set(
            'siagapasok.demo.enabled',
            true
        );

        $this->seed(
            DatabaseSeeder::class
        );

        $primaryOperator =
            User::query()
                ->where(
                    'email',
                    DemoIdentifiers
                        ::PRIMARY_OPERATOR_EMAIL
                )
                ->firstOrFail();

        $primaryManager =
            User::query()
                ->where(
                    'email',
                    DemoIdentifiers
                        ::PRIMARY_MANAGER_EMAIL
                )
                ->firstOrFail();

        $networkOperator =
            User::query()
                ->where(
                    'email',
                    DemoIdentifiers
                        ::NETWORK_OPERATOR_EMAIL
                )
                ->firstOrFail();

        $networkManager =
            User::query()
                ->where(
                    'email',
                    DemoIdentifiers
                        ::NETWORK_MANAGER_EMAIL
                )
                ->firstOrFail();

        $forecast =
            DemandForecast::query()
                ->where(
                    'forecast_code',
                    DemoIdentifiers
                        ::FORECAST_CODE
                )
                ->firstOrFail();

        app(
            DemoSupplyRiskService::class
        )->apply(
            $primaryOperator
        );

        $requestService =
            app(
                DemoFallbackRequestService::class
            );

        $requestService
            ->prepareAndSubmit(
                $primaryOperator
            );

        $requestService
            ->approveBroadcast(
                $primaryManager
            );

        $sourceService =
            app(
                DemoFallbackSourceService::class
            );

        $sourceService
            ->prepareAndSubmit(
                $networkOperator
            );

        $sourceService
            ->approve(
                $networkManager
            );

        $offerService =
            app(
                DemoFallbackOfferService::class
            );

        $offerService
            ->prepareAndSubmit(
                $networkOperator
            );

        $offerService
            ->approveForAvailability(
                $networkManager
            );

        $offerService
            ->accept(
                $primaryManager
            );

        return [
            'primary_operator' =>
                $primaryOperator,

            'primary_manager' =>
                $primaryManager,

            'network_operator' =>
                $networkOperator,

            'network_manager' =>
                $networkManager,

            'forecast' =>
                $forecast,
        ];
    }
}