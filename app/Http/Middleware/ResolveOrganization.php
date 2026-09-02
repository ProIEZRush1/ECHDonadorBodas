<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveOrganization
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user && $user->is_active && $user->organization, 403, 'Tu cuenta no tiene acceso a un workspace activo.');
        abort_if($user->organization->status !== 'active', 403, 'Este workspace está suspendido.');

        app()->instance('currentOrganization', $user->organization);
        view()->share('currentOrganization', $user->organization);

        return $next($request);
    }
}
