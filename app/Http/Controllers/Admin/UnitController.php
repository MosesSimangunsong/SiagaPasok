<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUnitRequest;
use App\Http\Requests\Admin\UpdateUnitRequest;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class UnitController extends Controller
{
    public function create(): Response
    {
        return Inertia::render(
            'Admin/MasterData/Units/Create'
        );
    }

    public function store(
        StoreUnitRequest $request
    ): RedirectResponse {
        Unit::create($request->validated());

        return redirect()
            ->route('admin.master-data.index')
            ->with('success', 'Unit berhasil ditambahkan.');
    }

    public function edit(Unit $unit): Response
    {
        return Inertia::render(
            'Admin/MasterData/Units/Edit',
            [
                'unit' => [
                    'id' => $unit->id,
                    'code' => $unit->code,
                    'name' => $unit->name,
                    'symbol' => $unit->symbol,
                    'decimal_precision' => $unit->decimal_precision,
                    'is_active' => $unit->is_active,
                ],
            ]
        );
    }

    public function update(
        UpdateUnitRequest $request,
        Unit $unit
    ): RedirectResponse {
        $unit->update($request->validated());

        return redirect()
            ->route('admin.master-data.index')
            ->with('success', 'Unit berhasil diperbarui.');
    }
}