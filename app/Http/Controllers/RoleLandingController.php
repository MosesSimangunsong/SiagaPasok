<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RoleLandingController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        return match ($request->user()->role) {
            UserRole::SYSTEM_ADMIN => redirect()->route('admin.dashboard'),
            UserRole::SPPG_USER => redirect()->route('sppg.dashboard'),
            UserRole::KDKMP_OPERATOR => redirect()->route('kdkmp.operator.dashboard'),
            UserRole::KDKMP_MANAGER => redirect()->route('kdkmp.manager.dashboard'),
        };
    }
}