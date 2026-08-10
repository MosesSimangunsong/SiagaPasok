<?php

namespace App\Http\Controllers\Admin;

use App\Enums\NetworkRole;
use App\Enums\OrganizationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SetSupplyNetworkLinkActiveStateRequest;
use App\Http\Requests\Admin\StoreSupplyNetworkLinkRequest;
use App\Models\Organization;
use App\Models\SupplyNetworkLink;
use App\Services\Supply\SupplyNetworkService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SupplyNetworkController extends Controller
{
    public function index(): Response
    {
        $sppgs = Organization::query()
            ->where(
                'organization_type',
                OrganizationType::SPPG->value
            )
            ->with([
                'sppgNetworkLinks' => fn ($query) => $query
                    ->with([
                        'kdkmpOrganization',
                        'configuredBy',
                    ])
                    ->orderByDesc('is_active')
                    ->orderBy('network_role')
                    ->orderBy('id'),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Organization $sppg) => [
                'id' => $sppg->id,
                'code' => $sppg->code,
                'name' => $sppg->name,
                'general_location' => $sppg->general_location,
                'is_active' => $sppg->is_active,

                'links' => $sppg->sppgNetworkLinks
                    ->map(fn (SupplyNetworkLink $link) => [
                        'id' => $link->id,
                        'network_role' => $link->network_role->value,
                        'network_role_label' => $link->network_role->label(),
                        'is_active' => $link->is_active,

                        'kdkmp' => [
                            'id' => $link->kdkmpOrganization->id,
                            'code' => $link->kdkmpOrganization->code,
                            'name' => $link->kdkmpOrganization->name,
                            'general_location' => $link
                                ->kdkmpOrganization
                                ->general_location,
                            'is_active' => $link
                                ->kdkmpOrganization
                                ->is_active,
                        ],

                        'configured_by' => [
                            'id' => $link->configuredBy->id,
                            'name' => $link->configuredBy->name,
                        ],

                        'updated_at' => $link->updated_at
                            ?->toIso8601String(),
                    ])
                    ->values(),
            ]);

        $kdkmps = Organization::query()
            ->where(
                'organization_type',
                OrganizationType::KDKMP->value
            )
            ->orderBy('name')
            ->get()
            ->map(fn (Organization $organization) => [
                'id' => $organization->id,
                'code' => $organization->code,
                'name' => $organization->name,
                'general_location' => $organization->general_location,
                'is_active' => $organization->is_active,
            ]);

        return Inertia::render(
            'Admin/SupplyNetwork/Index',
            [
                'sppgs' => $sppgs,
                'kdkmps' => $kdkmps,

                'networkRoles' => array_map(
                    fn (NetworkRole $role) => [
                        'value' => $role->value,
                        'label' => $role->label(),
                    ],
                    NetworkRole::cases()
                ),
            ]
        );
    }

    public function store(
        StoreSupplyNetworkLinkRequest $request,
        SupplyNetworkService $service
    ): RedirectResponse {
        $data = $request->validated();

        $sppg = Organization::query()
            ->findOrFail($data['sppg_organization_id']);

        $kdkmp = Organization::query()
            ->findOrFail($data['kdkmp_organization_id']);

        $service->createLink(
            $request->user(),
            $sppg,
            $kdkmp,
            NetworkRole::from($data['network_role']),
            $data['is_active'],
        );

        return redirect()
            ->route('admin.supply-network.index')
            ->with(
                'success',
                'Supply network link berhasil ditambahkan.'
            );
    }

    public function assignPrimary(
        SupplyNetworkLink $link,
        SupplyNetworkService $service
    ): RedirectResponse {
        $service->assignPrimary(
            request()->user(),
            $link
        );

        return redirect()
            ->route('admin.supply-network.index')
            ->with(
                'success',
                'KDKMP PRIMARY berhasil diperbarui.'
            );
    }

    public function setActiveState(
        SetSupplyNetworkLinkActiveStateRequest $request,
        SupplyNetworkLink $link,
        SupplyNetworkService $service
    ): RedirectResponse {
        $service->setActiveState(
            $request->user(),
            $link,
            $request->boolean('is_active'),
        );

        return redirect()
            ->route('admin.supply-network.index')
            ->with(
                'success',
                $request->boolean('is_active')
                    ? 'Network link berhasil diaktifkan.'
                    : 'Network link berhasil dinonaktifkan.'
            );
    }
}