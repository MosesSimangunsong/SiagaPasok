<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\CommodityController;
use App\Http\Controllers\Admin\MasterDataController;
use App\Http\Controllers\Admin\OrganizationController;
use App\Http\Controllers\Admin\SupplyNetworkController;
use App\Http\Controllers\Admin\UnitController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Kdkmp\ExpectedHarvestController;
use App\Http\Controllers\Kdkmp\ForecastController as KdkmpForecastController;
use App\Http\Controllers\Kdkmp\ProducerActiveStateController;
use App\Http\Controllers\Kdkmp\ProducerController;
use App\Http\Controllers\Kdkmp\CommitmentConfidenceController;
use App\Http\Controllers\Kdkmp\CommitmentController;
use App\Http\Controllers\Kdkmp\CommitmentRevisionController;
use App\Http\Controllers\Kdkmp\CommitmentVersionController;
use App\Http\Controllers\Kdkmp\ConfidenceRecoveryController;
use App\Http\Controllers\Kdkmp\CommitmentApprovalController;
use App\Http\Controllers\Kdkmp\ConfidenceRecoveryReviewController;
use App\Http\Controllers\Kdkmp\FallbackRequestActionController;
use App\Http\Controllers\Kdkmp\FallbackRequestApprovalController;
use App\Http\Controllers\Kdkmp\FallbackRequestController;
use App\Http\Controllers\Kdkmp\FallbackNetworkController;
use App\Http\Controllers\Kdkmp\FallbackOfferActionController;
use App\Http\Controllers\Kdkmp\FallbackOfferController;
use App\Http\Controllers\Kdkmp\FallbackOfferReviewController;
use App\Http\Controllers\Kdkmp\IncomingFallbackOfferController;
use App\Http\Controllers\Kdkmp\DocumentRecordController;
use App\Http\Controllers\Kdkmp\ReadinessApprovalController;
use App\Http\Controllers\Kdkmp\ReadinessController;
use App\Http\Controllers\Kdkmp\OperatorDashboardController;
use App\Http\Controllers\RoleLandingController;
use App\Http\Controllers\Sppg\DemandForecastActionController;
use App\Http\Controllers\Sppg\DemandForecastController;
use App\Http\Controllers\Sppg\ForecastReadinessController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Kdkmp\CommitmentCancellationController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get(
        '/login',
        [AuthenticatedSessionController::class, 'create']
    )->name('login');

    Route::post(
        '/login',
        [AuthenticatedSessionController::class, 'store']
    )->name('login.store');
});

Route::middleware([
    'auth',
    'active.account',
])->group(function (): void {
    Route::get(
        '/',
        RoleLandingController::class
    )->name('home');

    Route::post(
        '/logout',
        [AuthenticatedSessionController::class, 'destroy']
    )->name('logout');

    /*
|--------------------------------------------------------------------------
| Shared — Notification Center
|--------------------------------------------------------------------------
|
| Notification adalah recipient-scoped action signal.
| Semua query dan mutation ownership ditegakkan server-side.
|
*/

Route::get(
    '/notifications',
    [
        NotificationController::class,
        'index',
    ]
)->name(
    'notifications.index'
);

Route::patch(
    '/notifications/{notification}/read',
    [
        NotificationController::class,
        'markRead',
    ]
)->name(
    'notifications.read'
);

    /*
    |--------------------------------------------------------------------------
    | System Admin
    |--------------------------------------------------------------------------
    */

    Route::prefix('admin')
        ->name('admin.')
        ->middleware('role:SYSTEM_ADMIN')
        ->group(function (): void {
            Route::get(
                '/',
                AdminDashboardController::class
            )->name('dashboard');

            Route::resource(
                'organizations',
                OrganizationController::class
            )->except([
                'show',
                'destroy',
            ]);

            Route::resource(
                'users',
                UserController::class
            )->except([
                'show',
                'destroy',
            ]);

            Route::get(
                '/master-data',
                MasterDataController::class
            )->name('master-data.index');

            Route::get(
                '/master-data/units/create',
                [UnitController::class, 'create']
            )->name('units.create');

            Route::post(
                '/master-data/units',
                [UnitController::class, 'store']
            )->name('units.store');

            Route::get(
                '/master-data/units/{unit}/edit',
                [UnitController::class, 'edit']
            )->name('units.edit');

            Route::put(
                '/master-data/units/{unit}',
                [UnitController::class, 'update']
            )->name('units.update');

            Route::post(
                '/master-data/commodities',
                [CommodityController::class, 'store']
            )->name('commodities.store');

            Route::get(
                '/master-data/commodities/{commodity}/edit',
                [CommodityController::class, 'edit']
            )->name('commodities.edit');

            Route::put(
                '/master-data/commodities/{commodity}',
                [CommodityController::class, 'update']
            )->name('commodities.update');

            Route::get(
                '/master-data/commodities/create',
                [CommodityController::class, 'create']
            )->name('commodities.create');

            Route::get(
                '/supply-network',
                [SupplyNetworkController::class, 'index']
            )->name('supply-network.index');

            Route::post(
                '/supply-network',
                [SupplyNetworkController::class, 'store']
            )->name('supply-network.store');

            Route::post(
                '/supply-network/{link}/assign-primary',
                [
                    SupplyNetworkController::class,
                    'assignPrimary',
                ]
            )->name(
                'supply-network.assign-primary'
            );

            Route::patch(
                '/supply-network/{link}/active-state',
                [
                    SupplyNetworkController::class,
                    'setActiveState',
                ]
            )->name(
                'supply-network.active-state'
            );
        });

    /*
    |--------------------------------------------------------------------------
    | SPPG
    |--------------------------------------------------------------------------
    */

    Route::prefix('sppg')
        ->name('sppg.')
        ->middleware('role:SPPG_USER')
        ->group(function (): void {
            Route::get(
                '/',
                fn () => redirect()->route(
                    'sppg.forecasts.index'
                )
            )->name('dashboard');

            Route::get(
                '/forecasts',
                [
                    DemandForecastController::class,
                    'index',
                ]
            )->name('forecasts.index');

            Route::get(
    '/forecasts/{forecast}/readiness',
    [
        ForecastReadinessController::class,
        'show',
    ]
)->name(
    'forecasts.readiness.show'
);

            Route::get(
                '/forecasts/create',
                [
                    DemandForecastController::class,
                    'create',
                ]
            )->name('forecasts.create');

            Route::post(
                '/forecasts',
                [
                    DemandForecastController::class,
                    'store',
                ]
            )->name('forecasts.store');

            Route::get(
                '/forecasts/{forecast}',
                [
                    DemandForecastController::class,
                    'show',
                ]
            )->name('forecasts.show');

            Route::get(
                '/forecasts/{forecast}/edit',
                [
                    DemandForecastController::class,
                    'edit',
                ]
            )->name('forecasts.edit');

            Route::put(
                '/forecasts/{forecast}',
                [
                    DemandForecastController::class,
                    'update',
                ]
            )->name('forecasts.update');

            Route::post(
                '/forecasts/{forecast}/publish',
                [
                    DemandForecastActionController::class,
                    'publish',
                ]
            )->name('forecasts.publish');

            Route::post(
                '/forecasts/{forecast}/revise',
                [
                    DemandForecastActionController::class,
                    'revise',
                ]
            )->name('forecasts.revise');

            Route::post(
                '/forecasts/{forecast}/cancel',
                [
                    DemandForecastActionController::class,
                    'cancel',
                ]
            )->name('forecasts.cancel');

            Route::post(
                '/forecasts/{forecast}/close',
                [
                    DemandForecastActionController::class,
                    'close',
                ]
            )->name('forecasts.close');
        });

    /*
    |--------------------------------------------------------------------------
    | KDKMP — Forecast
    |--------------------------------------------------------------------------
    |
    | Forecast tetap read-only bagi KDKMP.
    | Role dan organization scope ditegakkan kembali oleh policy.
    |
    */

    Route::get(
        '/kdkmp/forecasts',
        [
            KdkmpForecastController::class,
            'index',
        ]
    )->name('kdkmp.forecasts.index');

    Route::get(
        '/kdkmp/forecasts/{forecast}',
        [
            KdkmpForecastController::class,
            'show',
        ]
    )->name('kdkmp.forecasts.show');

    /*
    |--------------------------------------------------------------------------
    | KDKMP — Producer Registry
    |--------------------------------------------------------------------------
    |
    | Producer adalah data internal KDKMP.
    | Operator dapat create/update/activate/deactivate.
    | Manager hanya memperoleh read access sesuai ProducerPolicy.
    | Tidak ada hard-delete route.
    |
    */

    Route::get(
        '/kdkmp/producers',
        [
            ProducerController::class,
            'index',
        ]
    )->name('kdkmp.producers.index');

    Route::get(
        '/kdkmp/producers/create',
        [
            ProducerController::class,
            'create',
        ]
    )->name('kdkmp.producers.create');

    Route::post(
        '/kdkmp/producers',
        [
            ProducerController::class,
            'store',
        ]
    )->name('kdkmp.producers.store');

    Route::get(
        '/kdkmp/producers/{producer}',
        [
            ProducerController::class,
            'show',
        ]
    )->name('kdkmp.producers.show');

    Route::get(
        '/kdkmp/producers/{producer}/edit',
        [
            ProducerController::class,
            'edit',
        ]
    )->name('kdkmp.producers.edit');

    Route::put(
        '/kdkmp/producers/{producer}',
        [
            ProducerController::class,
            'update',
        ]
    )->name('kdkmp.producers.update');

    Route::patch(
        '/kdkmp/producers/{producer}/active-state',
        ProducerActiveStateController::class
    )->name('kdkmp.producers.active-state');

    /*
    |--------------------------------------------------------------------------
    | KDKMP — Expected Harvest
    |--------------------------------------------------------------------------
    |
    | Expected Harvest adalah planning context internal KDKMP.
    | Operator dapat create/update.
    | Manager hanya memperoleh read access sesuai policy.
    | Tidak ada submit/approve/reject/delete endpoint.
    |
    */

    Route::get(
        '/kdkmp/expected-harvests',
        [
            ExpectedHarvestController::class,
            'index',
        ]
    )->name('kdkmp.expected-harvests.index');

    Route::get(
        '/kdkmp/expected-harvests/create',
        [
            ExpectedHarvestController::class,
            'create',
        ]
    )->name('kdkmp.expected-harvests.create');

    Route::post(
        '/kdkmp/expected-harvests',
        [
            ExpectedHarvestController::class,
            'store',
        ]
    )->name('kdkmp.expected-harvests.store');

    Route::get(
        '/kdkmp/expected-harvests/{expectedHarvest}',
        [
            ExpectedHarvestController::class,
            'show',
        ]
    )->name('kdkmp.expected-harvests.show');

    Route::get(
        '/kdkmp/expected-harvests/{expectedHarvest}/edit',
        [
            ExpectedHarvestController::class,
            'edit',
        ]
    )->name('kdkmp.expected-harvests.edit');

    Route::put(
        '/kdkmp/expected-harvests/{expectedHarvest}',
        [
            ExpectedHarvestController::class,
            'update',
        ]
    )->name('kdkmp.expected-harvests.update');

    /*
    |--------------------------------------------------------------------------
    | KDKMP — Supply Commitment & Confidence
    |--------------------------------------------------------------------------
    |
    | Detail Commitment bersifat organization-scoped untuk Operator/Manager.
    | Mutation maker hanya tersedia bagi KDKMP Operator.
    | Approval Manager memiliki endpoint terpisah.
    |
    */

    Route::get(
        '/kdkmp/commitments',
        [
            CommitmentController::class,
            'index',
        ]
    )->name('kdkmp.commitments.index');

    Route::get(
        '/kdkmp/confidence',
        [
            CommitmentController::class,
            'confidence',
        ]
    )->name('kdkmp.confidence.index');

    Route::middleware(
        'role:KDKMP_OPERATOR'
    )->group(function (): void {
        Route::get(
            '/kdkmp/commitments/create',
            [
                CommitmentController::class,
                'create',
            ]
        )->name(
            'kdkmp.commitments.create'
        );

        Route::post(
            '/kdkmp/commitments',
            [
                CommitmentController::class,
                'store',
            ]
        )->name(
            'kdkmp.commitments.store'
        );
    });

    Route::post(
    '/kdkmp/commitments/{commitment}/cancel',
    [
        CommitmentCancellationController::class,
        'cancelDraft',
    ]
)->name(
    'kdkmp.commitments.cancel'
);

    Route::get(
        '/kdkmp/commitments/{commitment}',
        [
            CommitmentController::class,
            'show',
        ]
    )->name(
        'kdkmp.commitments.show'
    );

    Route::middleware(
        'role:KDKMP_OPERATOR'
    )->group(function (): void {
        Route::get(
            '/kdkmp/commitments/{commitment}/versions/{version}/edit',
            [
                CommitmentVersionController::class,
                'edit',
            ]
        )->name(
            'kdkmp.commitments.versions.edit'
        );

        Route::put(
            '/kdkmp/commitments/{commitment}/versions/{version}',
            [
                CommitmentVersionController::class,
                'update',
            ]
        )->name(
            'kdkmp.commitments.versions.update'
        );

        Route::post(
            '/kdkmp/commitments/{commitment}/versions/{version}/submit',
            [
                CommitmentVersionController::class,
                'submit',
            ]
        )->name(
            'kdkmp.commitments.versions.submit'
        );

        Route::get(
            '/kdkmp/commitments/{commitment}/revisions/create',
            [
                CommitmentRevisionController::class,
                'create',
            ]
        )->name(
            'kdkmp.commitments.revisions.create'
        );

        Route::post(
            '/kdkmp/commitments/{commitment}/revisions',
            [
                CommitmentRevisionController::class,
                'store',
            ]
        )->name(
            'kdkmp.commitments.revisions.store'
        );

        Route::post(
            '/kdkmp/commitments/{commitment}/confidence/downgrade',
            [
                CommitmentConfidenceController::class,
                'downgrade',
            ]
        )->name(
            'kdkmp.commitments.confidence.downgrade'
        );

        Route::post(
            '/kdkmp/commitments/{commitment}/recovery-requests',
            [
                ConfidenceRecoveryController::class,
                'store',
            ]
        )->name(
            'kdkmp.commitments.recovery.store'
        );
    });

    /*
|--------------------------------------------------------------------------
| KDKMP — Logistics & Document Readiness
|--------------------------------------------------------------------------
|
| Readiness adalah organization-scoped per Forecast + Contributor + Type.
|
| Operator:
| - prepare;
| - edit current DRAFT items;
| - submit;
| - create immutable revision.
|
| Manager memperoleh read access melalui shared detail,
| tetapi approval commands berada di Manager surface.
|
*/

Route::get(
    '/kdkmp/readiness',
    [
        ReadinessController::class,
        'index',
    ]
)->name(
    'kdkmp.readiness.index'
);

Route::get(
    '/kdkmp/readiness/{checklist}',
    [
        ReadinessController::class,
        'show',
    ]
)->name(
    'kdkmp.readiness.show'
);

Route::get(
    '/kdkmp/documents',
    [
        DocumentRecordController::class,
        'index',
    ]
)->name(
    'kdkmp.documents.index'
);

Route::middleware(
    'role:KDKMP_OPERATOR'
)->group(function (): void {
    Route::post(
        '/kdkmp/forecasts/{forecast}/readiness/{type}/prepare',
        [
            ReadinessController::class,
            'prepare',
        ]
    )
        ->where(
            'type',
            'logistics|document'
        )
        ->name(
            'kdkmp.readiness.prepare'
        );

    Route::put(
        '/kdkmp/readiness/{checklist}/items/{item}',
        [
            ReadinessController::class,
            'updateItem',
        ]
    )->name(
        'kdkmp.readiness.items.update'
    );

    Route::post(
        '/kdkmp/readiness/{checklist}/submit',
        [
            ReadinessController::class,
            'submit',
        ]
    )->name(
        'kdkmp.readiness.submit'
    );

    Route::post(
        '/kdkmp/readiness/{checklist}/revisions',
        [
            ReadinessController::class,
            'createRevision',
        ]
    )->name(
        'kdkmp.readiness.revisions.store'
    );

    Route::post(
        '/kdkmp/documents',
        [
            DocumentRecordController::class,
            'store',
        ]
    )->name(
        'kdkmp.documents.store'
    );

    Route::put(
        '/kdkmp/documents/{documentRecord}',
        [
            DocumentRecordController::class,
            'update',
        ]
    )->name(
        'kdkmp.documents.update'
    );

    Route::post(
        '/kdkmp/documents/{documentRecord}/validate',
        [
            DocumentRecordController::class,
            'markValid',
        ]
    )->name(
        'kdkmp.documents.validate'
    );

    Route::post(
        '/kdkmp/documents/{documentRecord}/revoke',
        [
            DocumentRecordController::class,
            'revoke',
        ]
    )->name(
        'kdkmp.documents.revoke'
    );
});


    /*
    |--------------------------------------------------------------------------
    | KDKMP — Fallback Request
    |--------------------------------------------------------------------------
    |
    | Requester workspace hanya menampilkan Fallback Request milik
    | organization sendiri.
    |
    | Operator:
    | - create;
    | - submit.
    |
    | Manager:
    | - cancel.
    |
    | Broadcast supplier NETWORK menggunakan surface terpisah.
    | Tidak ada hard delete atau generic PATCH lifecycle endpoint.
    |
    */

    Route::get(
        '/kdkmp/fallback-requests',
        [
            FallbackRequestController::class,
            'index',
        ]
    )->name(
        'kdkmp.fallback-requests.index'
    );

    Route::middleware(
        'role:KDKMP_OPERATOR'
    )->group(function (): void {
        Route::get(
            '/kdkmp/fallback-requests/create',
            [
                FallbackRequestController::class,
                'create',
            ]
        )->name(
            'kdkmp.fallback-requests.create'
        );

        Route::post(
            '/kdkmp/fallback-requests',
            [
                FallbackRequestController::class,
                'store',
            ]
        )->name(
            'kdkmp.fallback-requests.store'
        );

        Route::post(
            '/kdkmp/fallback-requests/{fallbackRequest}/submit',
            [
                FallbackRequestActionController::class,
                'submit',
            ]
        )->name(
            'kdkmp.fallback-requests.submit'
        );
    });

    Route::get(
        '/kdkmp/fallback-requests/{fallbackRequest}',
        [
            FallbackRequestController::class,
            'show',
        ]
    )->name(
        'kdkmp.fallback-requests.show'
    );

    Route::middleware(
        'role:KDKMP_MANAGER'
    )->group(function (): void {
        Route::post(
            '/kdkmp/fallback-requests/{fallbackRequest}/cancel',
            [
                FallbackRequestActionController::class,
                'cancel',
            ]
        )->name(
            'kdkmp.fallback-requests.cancel'
        );
    });


    /*
|--------------------------------------------------------------------------
| KDKMP — Fallback Network
|--------------------------------------------------------------------------
|
| Broadcast inbox untuk KDKMP yang merupakan active NETWORK.
|
| Payload pada surface ini aggregate-safe.
| Offer mutation dibuat pada surface terpisah.
|
*/

Route::get(
    '/kdkmp/fallback-network',
    [
        FallbackNetworkController::class,
        'index',
    ]
)->name(
    'kdkmp.fallback-network.index'
);

Route::get(
    '/kdkmp/fallback-network/{fallbackRequest}',
    [
        FallbackNetworkController::class,
        'show',
    ]
)->name(
    'kdkmp.fallback-network.show'
);


/*
|--------------------------------------------------------------------------
| KDKMP — Fallback Offer
|--------------------------------------------------------------------------
|
| Supplier-private Offer workspace.
| Source Commitment dan Producer tetap hanya milik supplier organization.
|
*/

Route::get(
    '/kdkmp/fallback-offers',
    [
        FallbackOfferController::class,
        'index',
    ]
)->name(
    'kdkmp.fallback-offers.index'
);

Route::get(
    '/kdkmp/fallback-offers/{fallbackOffer}',
    [
        FallbackOfferController::class,
        'show',
    ]
)->name(
    'kdkmp.fallback-offers.show'
);

Route::middleware(
    'role:KDKMP_OPERATOR'
)->group(function (): void {
    Route::get(
        '/kdkmp/fallback-network/{fallbackRequest}/offers/create',
        [
            FallbackOfferController::class,
            'create',
        ]
    )->name(
        'kdkmp.fallback-offers.create'
    );

    Route::post(
        '/kdkmp/fallback-network/{fallbackRequest}/offers',
        [
            FallbackOfferController::class,
            'store',
        ]
    )->name(
        'kdkmp.fallback-offers.store'
    );

    Route::post(
        '/kdkmp/fallback-offers/{fallbackOffer}/submit',
        [
            FallbackOfferActionController::class,
            'submit',
        ]
    )->name(
        'kdkmp.fallback-offers.submit'
    );
});


    /*
    |--------------------------------------------------------------------------
    | KDKMP Manager — Commitment Approval & Recovery Review
    |--------------------------------------------------------------------------
    |
    | Manager hanya melakukan review dan explicit business decision.
    | Payload Commitment milik Operator tidak mempunyai edit route di sini.
    | Organization + maker-checker enforcement tetap berada di policy/service.
    |
    */

    Route::prefix(
        'kdkmp/manager'
    )
        ->name('kdkmp.manager.')
        ->middleware(
            'role:KDKMP_MANAGER'
        )
        ->group(function (): void {
            /*
            |--------------------------------------------------------------------------
            | Commitment Approval Queue
            |--------------------------------------------------------------------------
            */

            

            Route::get(
                '/approvals',
                [
                    CommitmentApprovalController::class,
                    'index',
                ]
            )->name(
                'approvals.index'
            );

            Route::get(
                '/approvals/{commitment}/versions/{version}',
                [
                    CommitmentApprovalController::class,
                    'show',
                ]
            )->name(
                'approvals.show'
            );

            Route::post(
                '/approvals/{commitment}/versions/{version}/approve',
                [
                    CommitmentApprovalController::class,
                    'approve',
                ]
            )->name(
                'approvals.approve'
            );

            Route::post(
                '/approvals/{commitment}/versions/{version}/reject',
                [
                    CommitmentApprovalController::class,
                    'reject',
                ]
            )->name(
                'approvals.reject'
            );

            /*
|--------------------------------------------------------------------------
| Approved Commitment Cancellation
|--------------------------------------------------------------------------
|
| Cancellation terhadap Commitment yang sudah menjadi
| operational supply truth adalah explicit Manager decision.
|
| Tidak ada generic lifecycle PATCH.
|
*/

Route::post(
    '/commitments/{commitment}/cancel',
    [
        CommitmentCancellationController::class,
        'cancelApproved',
    ]
)->name(
    'commitments.cancel'
);

            /*
            |--------------------------------------------------------------------------
            | Confidence Recovery Review
            |--------------------------------------------------------------------------
            */

            Route::get(
                '/recoveries',
                [
                    ConfidenceRecoveryReviewController::class,
                    'index',
                ]
            )->name(
                'recoveries.index'
            );

            Route::get(
                '/recoveries/{recovery}',
                [
                    ConfidenceRecoveryReviewController::class,
                    'show',
                ]
            )->name(
                'recoveries.show'
            );

            Route::post(
                '/recoveries/{recovery}/approve',
                [
                    ConfidenceRecoveryReviewController::class,
                    'approve',
                ]
            )->name(
                'recoveries.approve'
            );

            Route::post(
                '/recoveries/{recovery}/reject',
                [
                    ConfidenceRecoveryReviewController::class,
                    'reject',
                ]
            )->name(
                'recoveries.reject'
            );

            /*
            |--------------------------------------------------------------------------
            | Fallback Request Approval
            |--------------------------------------------------------------------------
            |
            | Manager requester hanya melakukan
            | review dan explicit broadcast decision.
            |
            */

            Route::get(
                '/fallback-requests',
                [
                    FallbackRequestApprovalController::class,
                    'index',
                ]
            )->name(
                'fallback-requests.index'
            );

            Route::get(
                '/fallback-requests/{fallbackRequest}',
                [
                    FallbackRequestApprovalController::class,
                    'show',
                ]
            )->name(
                'fallback-requests.show'
            );

            Route::post(
                '/fallback-requests/{fallbackRequest}/approve',
                [
                    FallbackRequestApprovalController::class,
                    'approve',
                ]
            )->name(
                'fallback-requests.approve'
            );

            Route::post(
                '/fallback-requests/{fallbackRequest}/reject',
                [
                    FallbackRequestApprovalController::class,
                    'reject',
                ]
            )->name(
                'fallback-requests.reject'
            );

            /*
            |--------------------------------------------------------------------------
            | Outgoing Fallback Offer Review
            |--------------------------------------------------------------------------
            |
            | Supplier Manager melakukan review terhadap
            | Offer milik organization supplier sendiri.
            |
            */

            Route::get(
                '/outgoing-offers',
                [
                    FallbackOfferReviewController::class,
                    'index',
                ]
            )->name(
                'outgoing-offers.index'
            );

            Route::post(
                '/outgoing-offers/{fallbackOffer}/approve',
                [
                    FallbackOfferReviewController::class,
                    'approve',
                ]
            )->name(
                'outgoing-offers.approve'
            );

            Route::post(
                '/outgoing-offers/{fallbackOffer}/reject',
                [
                    FallbackOfferReviewController::class,
                    'reject',
                ]
            )->name(
                'outgoing-offers.reject'
            );

            Route::post(
                '/outgoing-offers/{fallbackOffer}/withdraw',
                [
                    FallbackOfferReviewController::class,
                    'withdraw',
                ]
            )->name(
                'outgoing-offers.withdraw'
            );

            /*
            |--------------------------------------------------------------------------
            | Incoming Fallback Offer Decision
            |--------------------------------------------------------------------------
            |
            | Requester Manager hanya melihat aggregate
            | AVAILABLE Offer lalu melakukan Accept/Reject.
            |
            */

            Route::get(
                '/incoming-offers',
                [
                    IncomingFallbackOfferController::class,
                    'index',
                ]
            )->name(
                'incoming-offers.index'
            );

            Route::get(
                '/incoming-offers/{fallbackOffer}',
                [
                    IncomingFallbackOfferController::class,
                    'show',
                ]
            )->name(
                'incoming-offers.show'
            );

            Route::post(
                '/incoming-offers/{fallbackOffer}/accept',
                [
                    IncomingFallbackOfferController::class,
                    'accept',
                ]
            )->name(
                'incoming-offers.accept'
            );

            Route::post(
    '/incoming-offers/{fallbackOffer}/reject',
    [
        IncomingFallbackOfferController::class,
        'reject',
    ]
)->name(
    'incoming-offers.reject'
);

/*
|--------------------------------------------------------------------------
| Readiness Approval Queue
|--------------------------------------------------------------------------
|
| Manager hanya melakukan read-only review dan explicit decision.
| Payload readiness tetap milik Operator.
|
*/

Route::get(
    '/readiness',
    [
        ReadinessApprovalController::class,
        'index',
    ]
)->name(
    'readiness.index'
);

Route::get(
    '/readiness/{checklist}',
    [
        ReadinessApprovalController::class,
        'show',
    ]
)->name(
    'readiness.show'
);

Route::post(
    '/readiness/{checklist}/approve',
    [
        ReadinessApprovalController::class,
        'approve',
    ]
)->name(
    'readiness.approve'
);

Route::post(
    '/readiness/{checklist}/reject',
    [
        ReadinessApprovalController::class,
        'reject',
    ]
)->name(
    'readiness.reject'
);
        });

    Route::get(
    '/kdkmp/operator',
    OperatorDashboardController::class
)
    ->middleware(
        'role:KDKMP_OPERATOR'
    )
    ->name(
        'kdkmp.operator.dashboard'
    );

    Route::inertia(
        '/kdkmp/manager',
        'RoleLanding',
        [
            'workspace' => 'Workspace KDKMP',
            'roleLabel' => 'KDKMP Manager',
            'description' => (
                'Kelola approval dan keputusan '
                .'pasokan KDKMP.'
            ),
            'actionLabel' => 'Buka Approval Queue',
            'actionHref' => '/kdkmp/manager/approvals',
        ]
    )
        ->middleware('role:KDKMP_MANAGER')
        ->name('kdkmp.manager.dashboard');
});