<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCommodityRequest;
use App\Http\Requests\Admin\UpdateCommodityRequest;
use App\Models\Commodity;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CommodityController extends Controller
{
    public function create(): Response
    {
        return Inertia::render(
            'Admin/MasterData/Commodities/Create',
            [
                'units' => $this->unitOptions(),
            ]
        );
    }

    public function store(
        StoreCommodityRequest $request
    ): RedirectResponse {
        Commodity::create($request->validated());

        return redirect()
            ->route('admin.master-data.index')
            ->with('success', 'Komoditas berhasil ditambahkan.');
    }

    public function edit(Commodity $commodity): Response
    {
        return Inertia::render(
            'Admin/MasterData/Commodities/Edit',
            [
                'commodity' => [
                    'id' => $commodity->id,
                    'code' => $commodity->code,
                    'name' => $commodity->name,
                    'default_unit_id' => $commodity->default_unit_id,
                    'harvest_behavior' => $commodity->harvest_behavior,
                    'notes' => $commodity->notes,
                    'is_active' => $commodity->is_active,
                ],
                'units' => $this->unitOptions(),
            ]
        );
    }

    public function update(
        UpdateCommodityRequest $request,
        Commodity $commodity
    ): RedirectResponse {
        $commodity->update($request->validated());

        return redirect()
            ->route('admin.master-data.index')
            ->with('success', 'Komoditas berhasil diperbarui.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function unitOptions(): array
    {
        return Unit::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Unit $unit) => [
                'id' => $unit->id,
                'code' => $unit->code,
                'name' => $unit->name,
                'symbol' => $unit->symbol,
                'is_active' => $unit->is_active,
            ])
            ->values()
            ->all();
    }
}