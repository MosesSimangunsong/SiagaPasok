<?php

namespace App\Http\Controllers\Demo;

use App\Http\Controllers\Controller;
use App\Support\Demo\DemoAccountRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DemoAccountSwitchController extends Controller
{
    public function __invoke(
        Request $request,
        string $account
    ): RedirectResponse {
        $targetUser =
            DemoAccountRegistry::resolve(
                $account
            );

        abort_unless(
            $targetUser,
            404
        );

        Auth::guard('web')->login(
            $targetUser
        );

        $request->session()->regenerate();

        return redirect()
            ->route('home')
            ->with(
                'success',
                "Akun simulasi aktif: {$targetUser->name}."
            );
    }
}