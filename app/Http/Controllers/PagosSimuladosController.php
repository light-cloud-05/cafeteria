<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\{Pedido, TransaccionPago};
use App\Services\Caja\FalloCoordinacionCaja;
use App\Services\Pagos\{EscenarioSimulado, EstadoPago, FinalizarCobroService, IntentosPagoService};
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PDOException;

class PagosSimuladosController extends Controller
{
    public function crear(Request $request, int $id)
    {
        $datos = $this->datos($request->query(), ['medio' => 'nullable|in:QR_SIMULADO,TARJETA_SIMULADA', 'reintento' => 'nullable|uuid']);
        $medio = $datos['medio'] ?? 'QR_SIMULADO';
        $pedido = Pedido::with('venta')->findOrFail($id);
        $anterior = $datos['reintento'] ?? null;
        if ($anterior) {
            $base = $pedido->transaccionesPago()->where('referencia', $anterior)->firstOrFail();
            abort_if($base->estado === EstadoPago::PENDIENTE || $base->estado === EstadoPago::APROBADO, 409, 'El intento no admite reintento.');
        }
        // La preparación se conserva en sesión: recargar no genera otra solicitud.
        $slot = 'pagos.inicios.'.$id.'.'.$medio.'.'.($anterior ?? 'inicial');
        $borrador = $request->session()->get($slot);
        if ($borrador) {
            $existente = TransaccionPago::where('clave_idempotencia', $borrador['clave_idempotencia'])
                ->where('usuario_id', $request->user()->id)->first();
            if ($existente) return redirect()->route('admin.pagos.show', $existente->referencia);
        }
        $activo = $pedido->transaccionesPago()->where('estado', 'PENDIENTE')->first();
        if ($activo) return redirect()->route('admin.pagos.show', $activo->referencia);
        // Confirmación del importe en servidor; GET no crea ni resuelve pagos.
        app(FinalizarCobroService::class)->importe($pedido, $pedido->versionCobro());
        $borrador = ['clave_idempotencia' => $borrador['clave_idempotencia'] ?? (string)Str::uuid(),
            'version_pedido' => $pedido->versionCobro(), 'medio' => $medio];
        $request->session()->put($slot, $borrador);
        return view('pagos.crear', compact('pedido', 'borrador'));
    }

    public function store(Request $request, int $id, IntentosPagoService $servicio)
    {
        $datos = $this->datos($request->except('_token'), [
            'medio' => 'required|in:QR_SIMULADO,TARJETA_SIMULADA',
            'version_pedido' => 'required|regex:/\A[a-f0-9]{64}\z/', 'clave_idempotencia' => 'required|uuid',
        ]);
        try {
            $intento = $servicio->crear($id, $datos['medio'], $datos['version_pedido'], $datos['clave_idempotencia']);
            return redirect()->route('admin.pagos.show', $intento->referencia);
        } catch (FalloCoordinacionCaja|QueryException|PDOException) {
            // Nunca repetir tras un posible commit ni mostrar detalles de conexión.
            $clave = $datos['clave_idempotencia'];
            return response()->view('pagos.recuperacion', compact('clave'), 503);
        }
    }

    public function recuperar(Request $request)
    {
        $datos = $this->datos($request->query(), ['clave_idempotencia' => 'required|uuid']);
        $intento = TransaccionPago::where('clave_idempotencia', strtolower($datos['clave_idempotencia']))
            ->where('usuario_id', $request->user()->id)->first();
        if ($intento) return redirect()->route('admin.pagos.show', $intento->referencia);
        $clave = $datos['clave_idempotencia'];
        // Ausencia observada no demuestra rollback: no habilitar un cobro nuevo aquí.
        return response()->view('pagos.recuperacion', compact('clave'), 409);
    }

    public function show(Request $request, string $referencia)
    {
        $intento = $this->intento($referencia)->load('pedido');
        if ($request->expectsJson()) {
            return response()->json(['referencia' => $intento->referencia, 'estado' => $intento->estado->value,
                'medio' => $intento->medio, 'monto' => $intento->monto, 'moneda' => $intento->moneda,
                'origen' => $intento->origen])->header('Cache-Control', 'no-store');
        }
        $escenarios = array_filter(EscenarioSimulado::cases(), fn ($e) => $e->permite($intento->medio));
        return response()->view('pagos.show', compact('intento', 'escenarios'))->header('Cache-Control', 'no-store');
    }

    public function resolver(Request $request, string $referencia, IntentosPagoService $servicio)
    {
        $datos = $this->datos($request->except('_token'), ['escenario' => 'required|in:'.implode(',', array_column(EscenarioSimulado::cases(), 'value'))]);
        $escenario = EscenarioSimulado::from($datos['escenario']);
        return $this->operar($referencia, fn ($id) => $servicio->simular($id, $escenario), $escenario->etiqueta());
    }

    public function consultar(Request $request, string $referencia, IntentosPagoService $servicio)
    {
        $this->datos($request->except('_token'), []);
        return $this->operar($referencia, fn ($id) => $servicio->procesar($id));
    }

    public function cancelar(Request $request, string $referencia, IntentosPagoService $servicio)
    {
        $this->datos($request->except('_token'), []);
        return $this->operar($referencia, fn ($id) => $servicio->cancelar($id));
    }

    public function historial(Request $request)
    {
        $datos = $this->datos($request->query(), ['pedido' => 'nullable|integer|min:1', 'page' => 'nullable|integer|min:1']);
        $pedido = isset($datos['pedido']) ? Pedido::findOrFail($datos['pedido']) : null;
        $intentos = TransaccionPago::with('pedido')->when($pedido, fn ($q) => $q->where('pedido_id', $pedido->id))
            ->orderByDesc('id')->paginate(20)->withQueryString();
        return response()->view('pagos.historial', compact('intentos', 'pedido'))->header('Cache-Control', 'no-store');
    }

    private function intento(string $referencia): TransaccionPago
    {
        return TransaccionPago::where('referencia', $referencia)->firstOrFail();
    }

    private function operar(string $referencia, \Closure $accion, ?string $escenario = null)
    {
        $intento = $this->intento($referencia);
        try {
            $accion($intento->id);
            return redirect()->route('admin.pagos.show', $referencia)->with('pago_escenario', $escenario);
        } catch (FalloCoordinacionCaja|QueryException|PDOException) {
            // La referencia ya es conocida: mostrar consulta del mismo intento, nunca otro.
            $clave = null;
            return response()->view('pagos.recuperacion', compact('clave', 'referencia'), 503);
        }
    }

    private function datos(array $entrada, array $reglas): array
    {
        // No aceptar ni flashear campos inesperados, incluidos datos de tarjetas.
        abort_if(array_diff(array_keys($entrada), array_keys($reglas)) !== [], 422, 'Campos no admitidos para la simulación.');
        $validador = Validator::make($entrada, $reglas);
        abort_if($validador->fails(), 422, 'Solicitud de simulación inválida.');
        return $validador->validated();
    }
}
