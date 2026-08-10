<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'organizations' => Organization::query()->count(),
                'active_organizations' => Organization::query()
                    ->where('is_active', true)
                    ->count(),
                'users' => User::query()->count(),
                'active_users' => User::query()
                    ->where('is_active', true)
                    ->count(),
            ],
        ]);
    }
}