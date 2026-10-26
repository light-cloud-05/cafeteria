<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Caja\{AutorizacionCaja, ConsolidacionCajaService, DiaOperativoCaja};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ConsolidadosCajaController extends Controller
{
    public function __construct(private readonly AutorizacionCaja $autorizacion, private readonly ConsolidacionCajaService $servicio) {}

    public function mensual(Request $request) { return $this->consultar($request, true); }
    public function anual(Request $request) { return $this->consultar($request, false); }

    private function consultar(Request $request, bool $mensual)
    {
        try {
            $this->autorizacion->consulta();
            $reglas = ['anio' => 'nullable|integer|between:1000,9999'];
            if ($mensual) $reglas['mes'] = 'nullable|integer|between:1,12';
            $datos = $request->validate($reglas);
            $ahora = DiaOperativoCaja::ahora();
            $anio = (int)($datos['anio'] ?? $ahora->year);
            $mes = (int)($datos['mes'] ?? $ahora->month);
            $resumen = $mensual ? $this->servicio->mensual($anio, $mes) : $this->servicio->anual($anio);

            return response()->view('caja.'.($mensual ? 'mensual' : 'anual'), compact('resumen', 'anio', 'mes'))->header('Cache-Control', 'private, no-store');
        } catch (ValidationException | AuthenticationException | AuthorizationException $e) {
            throw $e;
        } catch (Throwable) {
            return response()->view('caja.error', [], 503)->header('Cache-Control', 'private, no-store');
        }
    }
}
