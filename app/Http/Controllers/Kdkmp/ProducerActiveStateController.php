<?php

namespace App\Http\Controllers\Kdkmp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kdkmp\SetProducerActiveStateRequest;
use App\Models\Producer;
use App\Services\Supply\ProducerService;
use Illuminate\Http\RedirectResponse;

class ProducerActiveStateController extends Controller
{
    public function __construct(
        private readonly ProducerService $producerService
    ) {
    }

    public function __invoke(
        SetProducerActiveStateRequest $request,
        Producer $producer
    ): RedirectResponse {
        $isActive = (bool) $request->validated(
            'is_active'
        );

        $this->producerService->setActiveState(
            $request->user(),
            $producer,
            $isActive
        );

        return back()->with(
            'success',
            $isActive
                ? 'Produsen berhasil diaktifkan.'
                : 'Produsen berhasil dinonaktifkan.'
        );
    }
}