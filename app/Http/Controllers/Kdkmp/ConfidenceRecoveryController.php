<?php

namespace App\Http\Controllers\Kdkmp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kdkmp\StoreConfidenceRecoveryRequest;
use App\Models\SupplyCommitment;
use App\Services\Commitment\ConfidenceService;
use Illuminate\Http\RedirectResponse;

class ConfidenceRecoveryController extends Controller
{
    public function __construct(
        private readonly ConfidenceService $confidenceService
    ) {
    }

    public function store(
        StoreConfidenceRecoveryRequest $request,
        SupplyCommitment $commitment
    ): RedirectResponse {
        $validated =
            $request->validated();

        $this->confidenceService->requestRecovery(
            $request->user(),
            $commitment,
            $validated[
                'recovery_reason'
            ]
        );

        return redirect()
            ->route(
                'kdkmp.commitments.show',
                $commitment
            )
            ->with(
                'success',
                'Permintaan pemulihan confidence berhasil diajukan.'
            );
    }
}