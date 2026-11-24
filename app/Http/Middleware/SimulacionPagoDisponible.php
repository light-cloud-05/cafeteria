<?php

namespace App\Http\Middleware;

use App\Support\DisponibilidadPagosSimulados;
use Closure;
use Illuminate\Http\Request;

class SimulacionPagoDisponible
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(app(DisponibilidadPagosSimulados::class)->habilitada(), 404, 'Simulación local no disponible.');
        $respuesta = $next($request);
        $respuesta->headers->set('Cache-Control', 'no-store');
        return $respuesta;
    }
}
