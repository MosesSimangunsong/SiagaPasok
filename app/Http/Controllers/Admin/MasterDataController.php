<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Commodity;
use App\Models\Unit;
use Inertia\Inertia;
use Inertia\Response;

class MasterDataController extends Controller
{
    public function __invoke(): Response
    {
        $units = Unit::query()
            ->withCount('commodities')
            ->orderBy('name')
            ->get()
            ->map(fn (Unit $unit) => [
                'id' => $unit->id,
                'code' => $unit->code,
                'name' => $unit->name,
                'symbol' => $unit->symbol,
                'decimal_precision' => $unit->decimal_precision,
                'is_active' => $unit->is_active,
                'commodities_count' => $unit->commodities_count,
            ]);

        $commodities = Commodity::query()
            ->with('defaultUnit')
            ->orderBy('name')
            ->get()
            ->map(fn (Commodity $commodity) => [
                'id' => $commodity->id,
                'code' => $commodity->code,
                'name' => $commodity->name,
                'default_unit_id' => $commodity->default_unit_id,
                'default_unit' => [
                    'id' => $commodity->defaultUnit->id,
                    'name' => $commodity->defaultUnit->name,
                    'symbol' => $commodity->defaultUnit->symbol,
                ],
                'harvest_behavior' => $commodity->harvest_behavior,
                'notes' => $commodity->notes,
                'is_active' => $commodity->is_active,
            ]);

        return Inertia::render('Admin/MasterData/Index', [
            'units' => $units,
            'commodities' => $commodities,
        ]);
    }
}