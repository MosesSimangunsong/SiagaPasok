<?php

namespace Tests\Feature\Forecast;

use App\Enums\ForecastStatus;
use App\Enums\NetworkRole;
use App\Enums\OrganizationType;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Commodity;
use App\Models\DemandForecast;
use App\Models\Organization;
use App\Models\SupplyNetworkLink;
use App\Models\Unit;
use App\Models\User;
use App\Services\Forecast\DemandForecastService;
use App\Services\Supply\SupplyNetworkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DemandForecastLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_sppg_user_can_create_forecast(): void
    {
        [$unit, $commodity] =
            $this->createReferenceData();

        $kdkmp = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-F-01'
        );

        $operator = User::factory()->create([
            'organization_id' => $kdkmp->id,
            'role' => UserRole::KDKMP_OPERATOR,
        ]);

        $this->actingAs($operator)
            ->post(
                '/sppg/forecasts',
                $this->validPayload(
                    $unit,
                    $commodity
                )
            )
            ->assertForbidden();

        $this->assertDatabaseCount(
            'demand_forecasts',
            0
        );
    }

    public function test_sppg_can_create_valid_draft(): void
    {
        [$unit, $commodity] =
            $this->createReferenceData();

        $sppg = $this->createOrganization(
            OrganizationType::SPPG,
            'SPPG-F-01'
        );

        $user = $this->createSppgUser($sppg);

        $response = $this
            ->actingAs($user)
            ->post(
                '/sppg/forecasts',
                $this->validPayload(
                    $unit,
                    $commodity
                )
            );

        $forecast = DemandForecast::query()
            ->firstOrFail();

        $response->assertRedirect(
            route(
                'sppg.forecasts.show',
                $forecast
            )
        );

        $this->assertSame(
            ForecastStatus::DRAFT,
            $forecast->status
        );

        $this->assertSame(
            $sppg->id,
            $forecast->sppg_organization_id
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'action' => 'FORECAST_CREATED',
                'entity_id' => $forecast->id,
                'actor_user_id' => $user->id,
            ]
        );
    }

    public function test_target_volume_must_be_greater_than_zero(): void
    {
        [$unit, $commodity] =
            $this->createReferenceData();

        $sppg = $this->createOrganization(
            OrganizationType::SPPG,
            'SPPG-F-02'
        );

        $user = $this->createSppgUser($sppg);

        $payload = $this->validPayload(
            $unit,
            $commodity
        );

        $payload['target_volume'] = 0;

        $this->actingAs($user)
            ->from('/sppg/forecasts/create')
            ->post(
                '/sppg/forecasts',
                $payload
            )
            ->assertRedirect(
                '/sppg/forecasts/create'
            )
            ->assertSessionHasErrors(
                'target_volume'
            );

        $this->assertDatabaseCount(
            'demand_forecasts',
            0
        );
    }

    public function test_required_end_cannot_be_before_start(): void
    {
        [$unit, $commodity] =
            $this->createReferenceData();

        $sppg = $this->createOrganization(
            OrganizationType::SPPG,
            'SPPG-F-03'
        );

        $user = $this->createSppgUser($sppg);

        $payload = $this->validPayload(
            $unit,
            $commodity
        );

        $payload['required_start_at'] =
            '2026-08-20 12:00:00';

        $payload['required_end_at'] =
            '2026-08-20 08:00:00';

        $this->actingAs($user)
            ->from('/sppg/forecasts/create')
            ->post(
                '/sppg/forecasts',
                $payload
            )
            ->assertSessionHasErrors(
                'required_end_at'
            );

        $this->assertDatabaseCount(
            'demand_forecasts',
            0
        );
    }

    public function test_sppg_cannot_update_forecast_owned_by_other_sppg(): void
    {
        [$unit, $commodity] =
            $this->createReferenceData();

        $sppgA = $this->createOrganization(
            OrganizationType::SPPG,
            'SPPG-F-A'
        );

        $sppgB = $this->createOrganization(
            OrganizationType::SPPG,
            'SPPG-F-B'
        );

        $userA = $this->createSppgUser($sppgA);
        $userB = $this->createSppgUser($sppgB);

        $forecast = app(
            DemandForecastService::class
        )->createDraft(
            $userA,
            $this->validPayload(
                $unit,
                $commodity
            )
        );

        $payload = $this->validPayload(
            $unit,
            $commodity
        );

        $payload['version'] =
            $forecast->version;

        $this->actingAs($userB)
            ->put(
                "/sppg/forecasts/{$forecast->id}",
                $payload
            )
            ->assertForbidden();
    }

    public function test_publish_requires_active_primary_network(): void
    {
        [$unit, $commodity] =
            $this->createReferenceData();

        $sppg = $this->createOrganization(
            OrganizationType::SPPG,
            'SPPG-F-04'
        );

        $user = $this->createSppgUser($sppg);

        $forecast = app(
            DemandForecastService::class
        )->createDraft(
            $user,
            $this->validPayload(
                $unit,
                $commodity
            )
        );

        $this->actingAs($user)
            ->from(
                "/sppg/forecasts/{$forecast->id}"
            )
            ->post(
                "/sppg/forecasts/{$forecast->id}/publish",
                [
                    'version' =>
                        $forecast->version,
                ]
            )
            ->assertSessionHasErrors(
                'network'
            );

        $this->assertSame(
            ForecastStatus::DRAFT,
            $forecast->fresh()->status
        );
    }

    public function test_valid_draft_can_be_published(): void
    {
        [$unit, $commodity] =
            $this->createReferenceData();

        $admin = User::factory()->create();

        $sppg = $this->createOrganization(
            OrganizationType::SPPG,
            'SPPG-F-05'
        );

        $kdkmp = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-F-05'
        );

        $this->createPrimaryLink(
            $admin,
            $sppg,
            $kdkmp
        );

        $user = $this->createSppgUser($sppg);

        $forecast = app(
            DemandForecastService::class
        )->createDraft(
            $user,
            $this->validPayload(
                $unit,
                $commodity
            )
        );

        $this->actingAs($user)
            ->post(
                "/sppg/forecasts/{$forecast->id}/publish",
                [
                    'version' =>
                        $forecast->version,
                ]
            )
            ->assertRedirect(
                route(
                    'sppg.forecasts.show',
                    $forecast
                )
            );

        $forecast->refresh();

        $this->assertSame(
            ForecastStatus::PUBLISHED,
            $forecast->status
        );

        $this->assertNotNull(
            $forecast->published_at
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'entity_id' => $forecast->id,
                'action' => 'FORECAST_PUBLISHED',
            ]
        );
    }

    public function test_published_revision_records_before_after_and_reason(): void
    {
        [
            $user,
            $forecast,
        ] = $this->createPublishedForecast(
            'REV'
        );

        $oldTarget =
            (string) $forecast->target_volume;

        $newTarget = '145.500000';

        $this->actingAs($user)
            ->post(
                "/sppg/forecasts/{$forecast->id}/revise",
                [
                    'target_volume' =>
                        $newTarget,

                    'reason' =>
                        'Kebutuhan operasional meningkat.',

                    'version' =>
                        $forecast->version,
                ]
            )
            ->assertRedirect(
                route(
                    'sppg.forecasts.show',
                    $forecast
                )
            );

        $forecast->refresh();

        $this->assertSame(
            $newTarget,
            (string) $forecast->target_volume
        );

        $audit = AuditLog::query()
            ->where(
                'entity_id',
                $forecast->id
            )
            ->where(
                'action',
                'FORECAST_REVISED'
            )
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(
            $oldTarget,
            $audit
                ->previous_value_json[
                    'target_volume'
                ]
        );

        $this->assertSame(
            $newTarget,
            $audit
                ->new_value_json[
                    'target_volume'
                ]
        );

        $this->assertSame(
            'Kebutuhan operasional meningkat.',
            $audit->reason_note
        );
    }

    public function test_stale_version_is_rejected(): void
    {
        [$unit, $commodity] =
            $this->createReferenceData();

        $sppg = $this->createOrganization(
            OrganizationType::SPPG,
            'SPPG-F-06'
        );

        $user = $this->createSppgUser($sppg);

        $service = app(
            DemandForecastService::class
        );

        $forecast = $service->createDraft(
            $user,
            $this->validPayload(
                $unit,
                $commodity
            )
        );

        $staleVersion =
            $forecast->version;

        $updatedPayload = $this->validPayload(
            $unit,
            $commodity
        );

        $updatedPayload['target_volume'] = 120;

        $service->updateDraft(
            $user,
            $forecast,
            $updatedPayload,
            $staleVersion
        );

        $updatedPayload['version'] =
            $staleVersion;

        $this->actingAs($user)
            ->from(
                "/sppg/forecasts/{$forecast->id}/edit"
            )
            ->put(
                "/sppg/forecasts/{$forecast->id}",
                $updatedPayload
            )
            ->assertSessionHasErrors(
                'version'
            );
    }

    public function test_closed_forecast_is_terminal(): void
    {
        [
            $user,
            $forecast,
        ] = $this->createPublishedForecast(
            'CLOSE'
        );

        $this->actingAs($user)
            ->post(
                "/sppg/forecasts/{$forecast->id}/close",
                [
                    'version' =>
                        $forecast->version,
                ]
            )
            ->assertRedirect();

        $forecast->refresh();

        $this->assertSame(
            ForecastStatus::CLOSED,
            $forecast->status
        );

        $this->actingAs($user)
            ->from(
                "/sppg/forecasts/{$forecast->id}"
            )
            ->post(
                "/sppg/forecasts/{$forecast->id}/revise",
                [
                    'target_volume' => 200,
                    'reason' => 'Tidak boleh.',
                    'version' =>
                        $forecast->version,
                ]
            )
            ->assertSessionHasErrors('status');

        $this->actingAs($user)
            ->from(
                "/sppg/forecasts/{$forecast->id}"
            )
            ->post(
                "/sppg/forecasts/{$forecast->id}/cancel",
                [
                    'cancellation_reason' =>
                        'Tidak boleh.',

                    'version' =>
                        $forecast->version,
                ]
            )
            ->assertSessionHasErrors('status');

        $this->assertSame(
            ForecastStatus::CLOSED,
            $forecast->fresh()->status
        );
    }

    public function test_published_forecast_freezes_network_configuration_until_closed(): void
    {
        [$unit, $commodity] =
            $this->createReferenceData();

        $admin = User::factory()->create();

        $sppg = $this->createOrganization(
            OrganizationType::SPPG,
            'SPPG-C32'
        );

        $kdkmpA = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-C32-A'
        );

        $kdkmpB = $this->createOrganization(
            OrganizationType::KDKMP,
            'KDKMP-C32-B'
        );

        $networkService = app(
            SupplyNetworkService::class
        );

        $networkService->createLink(
            $admin,
            $sppg,
            $kdkmpA,
            NetworkRole::PRIMARY,
            true,
        );

        $networkLink =
            $networkService->createLink(
                $admin,
                $sppg,
                $kdkmpB,
                NetworkRole::NETWORK,
                true,
            );

        $user = $this->createSppgUser($sppg);

        $forecastService = app(
            DemandForecastService::class
        );

        $forecast =
            $forecastService->createDraft(
                $user,
                $this->validPayload(
                    $unit,
                    $commodity
                )
            );

        $forecast =
            $forecastService->publish(
                $user,
                $forecast,
                $forecast->version
            );

        try {
            $networkService->assignPrimary(
                $admin,
                $networkLink
            );

            $this->fail(
                'Network topology changed during PUBLISHED Forecast.'
            );
        } catch (
            ValidationException $exception
        ) {
            $this->assertArrayHasKey(
                'network_configuration',
                $exception->errors()
            );
        }

        $forecast =
            $forecastService->close(
                $user,
                $forecast,
                $forecast->version
            );

        $newPrimary =
            $networkService->assignPrimary(
                $admin,
                $networkLink
            );

        $this->assertSame(
            NetworkRole::PRIMARY,
            $newPrimary->network_role
        );
    }

    private function createPublishedForecast(
        string $suffix,
    ): array {
        [$unit, $commodity] =
            $this->createReferenceData(
                $suffix
            );

        $admin = User::factory()->create();

        $sppg = $this->createOrganization(
            OrganizationType::SPPG,
            "SPPG-PUB-{$suffix}"
        );

        $kdkmp = $this->createOrganization(
            OrganizationType::KDKMP,
            "KDKMP-PUB-{$suffix}"
        );

        $this->createPrimaryLink(
            $admin,
            $sppg,
            $kdkmp
        );

        $user = $this->createSppgUser($sppg);

        $service = app(
            DemandForecastService::class
        );

        $forecast = $service->createDraft(
            $user,
            $this->validPayload(
                $unit,
                $commodity
            )
        );

        $forecast = $service->publish(
            $user,
            $forecast,
            $forecast->version
        );

        return [
            $user,
            $forecast,
        ];
    }

    private function createReferenceData(
        string $suffix = '',
    ): array {
        $normalized = $suffix !== ''
            ? "-{$suffix}"
            : '';

        $unit = Unit::create([
            'code' => "kg{$normalized}",
            'name' => "Kilogram {$suffix}",
            'symbol' => 'kg',
            'decimal_precision' => 2,
            'is_active' => true,
        ]);

        $commodity = Commodity::create([
            'code' => "KANGKUNG{$normalized}",
            'name' => "Kangkung {$suffix}",
            'default_unit_id' => $unit->id,
            'harvest_behavior' => null,
            'notes' => null,
            'is_active' => true,
        ]);

        return [
            $unit,
            $commodity,
        ];
    }

    private function validPayload(
        Unit $unit,
        Commodity $commodity
    ): array {
        return [
            'commodity_id' => $commodity->id,
            'unit_id' => $unit->id,
            'target_volume' => 100,
            'required_start_at' =>
                '2026-08-20 08:00:00',

            'required_end_at' =>
                '2026-08-20 12:00:00',

            'freshness_interval_hours' => 24,
            'notes' => 'Forecast test',
        ];
    }

    private function createOrganization(
        OrganizationType $type,
        string $code
    ): Organization {
        return Organization::create([
            'code' => $code,
            'name' => $code,
            'organization_type' => $type,
            'is_active' => true,
            'general_location' => 'Lokasi Test',
        ]);
    }

    private function createSppgUser(
        Organization $organization
    ): User {
        return User::factory()->create([
            'organization_id' =>
                $organization->id,

            'role' => UserRole::SPPG_USER,
            'is_active' => true,
        ]);
    }

    private function createPrimaryLink(
        User $admin,
        Organization $sppg,
        Organization $kdkmp
    ): SupplyNetworkLink {
        return SupplyNetworkLink::create([
            'sppg_organization_id' =>
                $sppg->id,

            'kdkmp_organization_id' =>
                $kdkmp->id,

            'network_role' =>
                NetworkRole::PRIMARY,

            'is_active' => true,
            'configured_by' => $admin->id,
        ]);
    }
}