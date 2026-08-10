<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        if ($user) {
            $user->loadMissing('organization');
        }

        return [
            ...parent::share($request),

            'auth' => [
                'user' => $user
                    ? [
                        'id' => $user->id,
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
                                'organization_type_label' => $user
                                    ->organization
                                    ->organization_type
                                    ->label(),
                                'is_active' => $user->organization->is_active,
                            ]
                            : null,
                    ]
                    : null,
            ],

            'flash' => [
                'success' => fn () => $request->session()->get('success'),
            ],
        ];
    }
}