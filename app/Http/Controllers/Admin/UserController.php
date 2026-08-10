<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        $users = User::query()
            ->with('organization')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => $this->serializeUser($user));

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
        ]);
    }

    public function create(Request $request): Response
    {
        $selectedOrganizationId = null;

        if ($request->filled('organization')) {
            $candidate = Organization::query()->find(
                $request->integer('organization')
            );

            $selectedOrganizationId = $candidate?->id;
        }

        return Inertia::render('Admin/Users/Create', [
            'organizations' => $this->organizationOptions(),
            'roles' => $this->roleOptions(),
            'selectedOrganizationId' => $selectedOrganizationId,
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        User::create($request->validated());

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Pengguna berhasil ditambahkan.');
    }

    public function edit(User $user): Response
    {
        $user->load('organization');

        return Inertia::render('Admin/Users/Edit', [
            'user' => $this->serializeUser($user),
            'organizations' => $this->organizationOptions(),
            'roles' => $this->roleOptions(),
        ]);
    }

    public function update(
        UpdateUserRequest $request,
        User $user
    ): RedirectResponse {
        $data = $request->validated();

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->update($data);

        return redirect()
            ->route('admin.users.edit', $user)
            ->with('success', 'Pengguna berhasil diperbarui.');
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'organization_id' => $user->organization_id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role?->value,
            'role_label' => $user->role?->label(),
            'is_active' => $user->is_active,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'organization' => $user->organization
                ? [
                    'id' => $user->organization->id,
                    'code' => $user->organization->code,
                    'name' => $user->organization->name,
                    'organization_type' => $user->organization
                        ->organization_type
                        ->value,
                ]
                : null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function organizationOptions(): array
    {
        return Organization::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Organization $organization) => [
                'id' => $organization->id,
                'code' => $organization->code,
                'name' => $organization->name,
                'organization_type' => $organization->organization_type->value,
                'organization_type_label' => $organization
                    ->organization_type
                    ->label(),
                'is_active' => $organization->is_active,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function roleOptions(): array
    {
        return array_map(
            fn (UserRole $role) => [
                'value' => $role->value,
                'label' => $role->label(),
            ],
            UserRole::cases(),
        );
    }
}