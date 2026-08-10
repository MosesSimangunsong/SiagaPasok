<?php

namespace App\Http\Controllers\Kdkmp;

use App\Enums\SupplyConfidence;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kdkmp\DowngradeCommitmentConfidenceRequest;
use App\Models\SupplyCommitment;
use App\Services\Commitment\ConfidenceService;
use Illuminate\Http\RedirectResponse;

class CommitmentConfidenceController extends Controller
{
    public function __construct(
        private readonly ConfidenceService $confidenceService
    ) {
    }

    public function downgrade(
        DowngradeCommitmentConfidenceRequest $request,
        SupplyCommitment $commitment
    ): RedirectResponse {
        $validated =
            $request->validated();

        $this->confidenceService->downgrade(
            actor:
                $request->user(),

            commitment:
                $commitment,

            toConfidence:
                SupplyConfidence::from(
                    $validated[
                        'to_confidence'
                    ]
                ),

            reasonCode:
                $validated[
                    'reason_code'
                ] ?? null,

            reasonNote:
                $validated[
                    'reason_note'
                ],
        );

        return redirect()
            ->route(
                'kdkmp.commitments.show',
                $commitment
            )
            ->with(
                'success',
                'Confidence pasokan berhasil diperbarui.'
            );
    }
}