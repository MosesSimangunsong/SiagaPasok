<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDemoMode
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        abort_unless(
            (bool) config(
                'siagapasok.demo.enabled',
                false
            ),
            404
        );

        return $next($request);
    }
}