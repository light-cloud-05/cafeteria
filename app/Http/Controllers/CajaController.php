<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CierreCaja;
use App\Services\Caja\{AutorizacionCaja, CierreDiarioService, DiaOperativoCaja};
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class CajaController extends Controller
{
    public function __construct(private readonly AutorizacionCaja $autorizacion,
        private readonly CierreDiarioService $servicio) {}

    public function index(Request $request)
    {
        return $this->lectura(function () use ($request) {
            $this->autorizacion->consulta();
            $filtros = $request->validate([
                'tipo' => 'nullable|in:oficiales,historicos,todos',
                'desde' => 'nullable|date_format:Y-m-d', 'hasta' => 'nullable|date_format:Y-m-d',
                'page' => 'nullable|integer|min:1|max:1000000',
                'por_pagina' => 'nullable|integer|in:10,20,50',
            ]);
            if (!empty($filtros['desde']) && !empty($filtros['hasta']) && $filtros['hasta'] < $filtros['desde']) {
                throw ValidationException::withMessages(['hasta' => 'La fecha final no puede ser anterior a la inicial.']);
            }
            $tipo = $filtros['tipo'] ?? 'oficiales';
            if ($tipo === 'historicos' && (!empty($filtros['desde']) || !empty($filtros['hasta']))) {
                throw ValidationException::withMessages(['tipo' => 'Los históricos sin fecha operativa no admiten ese filtro de fechas.']);
            }
            $consulta = CierreCaja::with(['versionVigente.responsable', 'usuario']);
            if ($tipo === 'oficiales') $consulta->oficiales();
            if ($tipo === 'historicos') $consulta->whereNull('fecha_operativa');
            if (!empty($filtros['desde'])) $consulta->where('fecha_operativa', '>=', $filtros['desde']);
            if (!empty($filtros['hasta'])) $consulta->where('fecha_operativa', '<=', $filtros['hasta']);
            $cierres = $consulta->orderByDesc('fecha_operativa')->orderByDesc('id')
                ->paginate((int) ($filtros['por_pagina'] ?? 20))->appends($filtros);

            return view('caja.index', compact('cierres', 'filtros', 'tipo'));
        });
    }

    public function previsualizacion(Request $request)
    {
        return $this->lectura(function () use ($request) {
            $this->autorizacion->consulta();
            $datos = $request->validate(['fecha' => 'nullable|date_format:Y-m-d']);
            $fecha = $datos['fecha'] ?? DiaOperativoCaja::ahora()->toDateString();
            $resumen = null;
            $errorFinanciero = null;
            try {
                $resumen = $this->servicio->previsualizar($fecha);
            } catch (ValidationException $e) {
                if (isset($e->errors()['fecha'])) throw $e;
                $errorFinanciero = 'No se puede calcular esta consulta: existen inconsistencias en los datos financieros. Solicita su revisión.';
            }
            $abierto = $fecha === DiaOperativoCaja::ahora()->toDateString();
            $cierre = CierreCaja::oficiales()->where('fecha_operativa', $fecha)->first(['id']);

            return response()->view('caja.previsualizacion', compact('fecha', 'resumen', 'errorFinanciero', 'abierto', 'cierre'), $errorFinanciero ? 422 : 200);
        });
    }

    public function show(Request $request, string $cierre)
    {
        return $this->lectura(function () use ($request, $cierre) {
            $this->autorizacion->consulta();
            $this->paginas($request);
            $cierre = CierreCaja::with(['usuario', 'versionVigente.responsable'])->findOrFail($cierre);
            $version = $cierre->versionVigente;
            $detalles = $version?->detalles()->orderBy('venta_id')->paginate(20, ['*'], 'ventas_page');
            $versiones = $cierre->versiones()->with('responsable')->orderByDesc('numero_version')
                ->paginate(20, ['*'], 'versiones_page');
            $detalles?->appends($request->only('versiones_page'));
            $versiones->appends($request->only('ventas_page'));

            return view('caja.show', compact('cierre', 'version', 'detalles', 'versiones'));
        });
    }

    public function version(Request $request, string $cierre, string $version)
    {
        return $this->lectura(function () use ($request, $cierre, $version) {
            $this->autorizacion->consulta();
            $this->paginas($request);
            $cierre = CierreCaja::with('versionVigente')->findOrFail($cierre);
            $version = $cierre->versiones()->with('responsable')->whereKey($version)->firstOrFail();
            $detalles = $version->detalles()->orderBy('venta_id')->paginate(20, ['*'], 'ventas_page');

            return view('caja.version', compact('cierre', 'version', 'detalles'));
        });
    }

    private function paginas(Request $request): void
    {
        $request->validate(['ventas_page' => 'nullable|integer|min:1|max:1000000',
            'versiones_page' => 'nullable|integer|min:1|max:1000000']);
    }

    private function lectura(Closure $operacion)
    {
        try {
            $resultado = $operacion();
            $respuesta = $resultado instanceof \Symfony\Component\HttpFoundation\Response ? $resultado : response($resultado);
            $respuesta->headers->set('Cache-Control', 'private, no-store');

            return $respuesta;
        } catch (ValidationException | AuthenticationException | AuthorizationException | ModelNotFoundException | HttpExceptionInterface $e) {
            throw $e;
        } catch (Throwable) {
            // Nunca trasladar SQL, mensajes PDO, importes parciales o trazas al navegador.
            return response()->view('caja.error', [], 503)->header('Cache-Control', 'private, no-store');
        }
    }
}
