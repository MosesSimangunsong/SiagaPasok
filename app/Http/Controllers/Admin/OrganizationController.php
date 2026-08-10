<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrganizationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreOrganizationRequest;
use App\Http\Requests\Admin\UpdateOrganizationRequest;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public function index(): Response
    {
        $organizations = Organization::query()
            ->withCount('users')
            ->orderBy('name')
            ->get()
            ->map(fn (Organization $organization) => [
                'id' => $organization->id,
                'code' => $organization->code,
                'name' => $organization->name,
                'organization_type' => $organization->organization_type->value,
                'organization_type_label' => $organization->organization_type->label(),
                'general_location' => $organization->general_location,
                'is_active' => $organization->is_active,
                'users_count' => $organization->users_count,
            ]);

        return Inertia::render('Admin/Organizations/Index', [
            'organizations' => $organizations,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Organizations/Create', [
            'organizationTypes' => $this->organizationTypeOptions(),
        ]);
    }

    public function store(
    StoreOrganizationRequest $request
): RedirectResponse {
    $data = $request->validated();
    $data['is_active'] = true;

    Organization::create($data);

    return redirect()
        ->route('admin.organizations.index')
        ->with('success', 'Organisasi berhasil ditambahkan.');
}

    public function edit(Organization $organization): Response
    {
        $organization->load([
            'users' => fn ($query) => $query->orderBy('name'),
        ]);

        return Inertia::render('Admin/Organizations/Edit', [
            'organization' => [
                'id' => $organization->id,
                'code' => $organization->code,
                'name' => $organization->name,
                'organization_type' => $organization->organization_type->value,
                'organization_type_label' => $organization->organization_type->label(),
                'general_location' => $organization->general_location,
                'is_active' => $organization->is_active,
                'users' => $organization->users->map(fn ($user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role?->value,
                    'role_label' => $user->role?->label(),
                    'is_active' => $user->is_active,
                ]),
            ],
            'organizationTypes' => $this->organizationTypeOptions(),
        ]);
    }

    public function update(
        UpdateOrganizationRequest $request,
        Organization $organization
    ): RedirectResponse {
        $organization->update($request->validated());

        return redirect()
            ->route('admin.organizations.edit', $organization)
            ->with('success', 'Organisasi berhasil diperbarui.');
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function organizationTypeOptions(): array
    {
        return array_map(
            fn (OrganizationType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ],
            OrganizationType::cases(),
        );
    }
}