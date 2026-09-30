<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerificarPermiso
{
    public function handle(
        Request $request,
        Closure $next,
        string $permiso
    ): Response {
        $usuario = $request->user();

        if (! $usuario) {
            return redirect()->route('login');
        }

        if (! $usuario->tienePermiso($permiso)) {
            abort(403, 'No tienes permiso para acceder a esta función.');
        }

        return $next($request);
    }
}