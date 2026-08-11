<?php

namespace App\Http\Controllers\Kdkmp;

use App\Http\Controllers\Controller;
use App\Models\SupplyCommitment;
use App\Services\Commitment\CommitmentLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class CommitmentCancellationController extends Controller
{
    public function __construct(
        private readonly CommitmentLifecycleService
            $lifecycleService,
    ) {
    }

    public function cancelDraft(
        Request $request,
        SupplyCommitment $commitment,
    ): RedirectResponse {
        $validated =
            $request->validate([
                'cancellation_reason' => [
                    'required',
                    'string',
                    'max:5000',
                ],
            ]);

        $this->lifecycleService
            ->cancel(
                actor:
                    $request->user(),

                commitment:
                    $commitment,

                reason:
                    $validated[
                        'cancellation_reason'
                    ],
            );

        return redirect()
            ->route(
                'kdkmp.commitments.show',
                $commitment
            )
            ->with(
                'success',
                'Commitment DRAFT berhasil dibatalkan.'
            );
    }

    public function cancelApproved(
        Request $request,
        SupplyCommitment $commitment,
    ): RedirectResponse {
        $validated =
            $request->validate([
                'cancellation_reason' => [
                    'required',
                    'string',
                    'max:5000',
                ],
            ]);

        $this->lifecycleService
            ->cancel(
                actor:
                    $request->user(),

                commitment:
                    $commitment,

                reason:
                    $validated[
                        'cancellation_reason'
                    ],
            );

        return redirect()
            ->route(
                'kdkmp.commitments.show',
                $commitment
            )
            ->with(
                'success',
                'Commitment APPROVED berhasil dibatalkan.'
            );
    }
}