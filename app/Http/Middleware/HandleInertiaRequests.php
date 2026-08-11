<?php

namespace App\Http\Middleware;

use App\Models\Notification;
use Illuminate\Http\Request;
use Inertia\Middleware;
use App\Support\Demo\DemoAccountRegistry;
use App\Support\Demo\DemoIdentifiers;

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

        $demoEnabled = (bool) config(
    'siagapasok.demo.enabled',
    false
);

$demoAccounts =
    $demoEnabled && $user
        ? array_map(
            static fn (
                array $account
            ): array => [
                ...$account,
                'href' => route(
                    'demo.switch-account',
                    [
                        'account' =>
                            $account['key'],
                    ]
                ),
            ],
            DemoAccountRegistry::options(
                $user
            )
        )
        : [];

        $canApplyDemoSupplyRisk =
    $demoEnabled
    && $user !== null
    && $user->isKdkmpOperator()
    && $user->email
        === DemoIdentifiers
            ::PRIMARY_OPERATOR_EMAIL
    && $user->organization?->code
        === DemoIdentifiers
            ::PRIMARY_KDKMP_CODE;

        $unreadNotificationCount = $user === null
            ? 0
            : Notification::query()
                ->where(
                    'recipient_user_id',
                    $user->id
                )
                ->whereNull(
                    'read_at'
                )
                ->count();

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
                        'last_login_at' => $user
                            ->last_login_at
                            ?->toIso8601String(),
                        'organization' => $user->organization
                            ? [
                                'id' => $user->organization->id,
                                'code' => $user->organization->code,
                                'name' => $user->organization->name,
                                'organization_type' => $user
                                    ->organization
                                    ->organization_type
                                    ->value,
                                'organization_type_label' => $user
                                    ->organization
                                    ->organization_type
                                    ->label(),
                                'is_active' => $user
                                    ->organization
                                    ->is_active,
                            ]
                            : null,
                    ]
                    : null,
            ],

'demo' => [
    'enabled' =>
        $demoEnabled,

    'label' => (string) config(
        'siagapasok.demo.label',
        'SIMULASI'
    ),

    'accounts' =>
        $demoAccounts,

    'actions' => [
        'supply_risk' =>
            $canApplyDemoSupplyRisk
                ? [
                    'label' =>
                        'Gangguan Pasokan',
                    'href' =>
                        route(
                            'demo.scenario.supply-risk'
                        ),
                ]
                : null,
    ],
],

            'notification_center' => [
                'unread_count' => $unreadNotificationCount,

                'href' => $user
                    ? route(
                        'notifications.index'
                    )
                    : null,
            ],

            'flash' => [
                'success' => fn () => $request
                    ->session()
                    ->get('success'),
            ],
        ];
    }
}