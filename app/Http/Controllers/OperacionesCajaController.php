<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CierreCaja;
use App\Services\Caja\{AutorizacionCaja, CierreDiarioService, RectificacionCierreDiarioService, DiaOperativoCaja, FalloCoordinacionCaja, ImporteCaja};
use App\Support\DisponibilidadCajaWeb;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class OperacionesCajaController extends Controller
{
    public function __construct(private readonly AutorizacionCaja $autorizacion,
        private readonly DisponibilidadCajaWeb $disponibilidad) {}

    public function confirmacion(Request $request)
    {
        return $this->segura(function () use ($request) {
            $this->autorizacion->confirmacion();
            $datos = $request->validate(['fecha' => 'nullable|date_format:Y-m-d']);
            $fecha = $datos['fecha'] ?? DiaOperativoCaja::ahora()->subDay()->toDateString();
            $this->fechaTerminada($fecha);
            $resumen = app(CierreDiarioService::class)->previsualizar($fecha);
            $habilitada = $this->disponibilidad->habilitada();

            return response()->view('caja.confirmar', compact('fecha', 'resumen', 'habilitada'));
        });
    }

    public function rectificacion(string $cierre)
    {
        return $this->segura(function () use ($cierre) {
            $this->autorizacion->rectificacion();
            $cierre = CierreCaja::with('versionVigente')->findOrFail($cierre);
            if (!$cierre->fecha_operativa || !$cierre->versionVigente) {
                throw ValidationException::withMessages(['cierre' => 'Solo se rectifican cierres oficiales con versión registrada.']);
            }
            $this->fechaTerminada($cierre->fecha_operativa->toDateString());
            $version = $cierre->versionVigente;
            $habilitada = $this->disponibilidad->habilitada();

            return response()->view('caja.rectificar', compact('cierre', 'version', 'habilitada'));
        });
    }

    public function confirmar(Request $request)
    {
        return $this->segura(function ($estado) use ($request) {
            $this->autorizacion->confirmacion();
            if (!$this->disponibilidad->habilitada()) return $this->bloqueada();
            $datos = $this->entrada($request, false);
            $estado->servicioInvocado = true;
            $nueva = app(CierreDiarioService::class)->confirmar($datos['fecha'], $datos['efectivo_contado'], $datos['observacion'] ?? null);

            return redirect()->route('admin.caja.show', $nueva->cierre_caja_id)->with('success', 'Cierre diario registrado.');
        });
    }

    public function rectificar(Request $request, string $cierre)
    {
        return $this->segura(function ($estado) use ($request, $cierre) {
            $this->autorizacion->rectificacion();
            if (!$this->disponibilidad->habilitada()) return $this->bloqueada();
            $datos = $this->entrada($request, true);
            $estado->servicioInvocado = true;
            $nueva = app(RectificacionCierreDiarioService::class)->rectificar((int)$cierre,
                (int)$datos['version_esperada'], $datos['efectivo_contado'], $datos['motivo'], $datos['observacion'] ?? null);

            return redirect()->route('admin.caja.show', $nueva->cierre_caja_id)->with('success', 'Nueva versión de rectificación registrada.');
        });
    }

    private function entrada(Request $request, bool $rectificacion): array
    {
        $permitidos = $rectificacion ? ['efectivo_contado', 'observacion', 'motivo', 'version_esperada'] : ['fecha', 'efectivo_contado', 'observacion'];
        if (array_diff(array_keys($request->all()), [...$permitidos, '_token'])) {
            throw ValidationException::withMessages(['formulario' => 'La solicitud contiene campos no admitidos.']);
        }
        $reglas = ['efectivo_contado' => ['required', 'string', function ($campo, $valor, $fallar) {
            try { ImporteCaja::centavos($valor); } catch (\InvalidArgumentException) { $fallar('Indica un efectivo no negativo con punto decimal y hasta dos decimales.'); }
        }], 'observacion' => 'nullable|string|max:4000'];
        if ($rectificacion) {
            $reglas += ['motivo' => 'required|string|max:1000', 'version_esperada' => 'required|integer|min:1|max:4294967294'];
        } else $reglas['fecha'] = 'required|date_format:Y-m-d';
        $datos = $request->validate($reglas);
        if (!$rectificacion) $this->fechaTerminada($datos['fecha']);

        return $datos;
    }

    private function fechaTerminada(string $fecha): void
    {
        if ($fecha >= DiaOperativoCaja::ahora()->toDateString()) {
            throw ValidationException::withMessages(['fecha' => 'Elige un día operativo terminado en Bolivia.']);
        }
    }

    private function bloqueada()
    {
        return $this->resultadoSeguro(['mensaje' => 'Las operaciones financieras de caja todavía no están habilitadas. No se inició la operación.', 'incierto' => false], 503);
    }

    private function resultadoSeguro(array $datos, int $estado)
    {
        if (!auth()->user()) {
            // Un callback puede invalidar la sesión después del commit: no renderizar un layout
            // que presupone un usuario ni mostrar la excepción original.
            return response('No se puede confirmar el resultado. Inicia sesión y consulta el historial sin reenviar la operación.', 401)->header('Cache-Control', 'private, no-store');
        }
        return response()->view('caja.resultado', $datos, $estado)->header('Cache-Control', 'private, no-store');
    }

    private function segura(Closure $operacion)
    {
        $estado = (object) ['servicioInvocado' => false];
        try {
            return $operacion($estado)->header('Cache-Control', 'private, no-store');
        } catch (ValidationException $e) {
            // Sin mensajes del motor SQL ni entradas rechazadas copiadas automáticamente a la sesión.
            $mensajes = [
                'fecha' => 'Elige una fecha válida YYYY-MM-DD de un día terminado en Bolivia y sin cierre previo.',
                'efectivo_contado' => 'Indica un importe no negativo con punto y hasta dos decimales, dentro del límite monetario.',
                'observacion' => 'La observación admite hasta 4000 caracteres. Explica cualquier diferencia al confirmar.',
                'motivo' => 'Indica un motivo de rectificación de hasta 1000 caracteres, sin credenciales ni datos sensibles.',
                'version_esperada' => 'La versión es inválida o ya no está vigente. Consulta el historial y abre un nuevo formulario.',
                'caja' => 'Caja no está disponible o venció el tiempo de espera. Consulta el estado antes de una nueva solicitud.',
                'ventas' => 'Existen datos financieros inconsistentes. Solicita su revisión antes de continuar.',
                'cierre' => 'Solo se admiten cierres oficiales de días terminados con historial coherente.',
                'rectificacion' => 'Debe cambiar el efectivo contado o la observación para registrar una nueva versión.',
                'formulario' => 'La solicitud contiene campos no admitidos. No envíes totales, diferencias ni identificadores de responsable.',
            ];
            $errores = array_intersect_key($mensajes, $e->errors());
            // El tipo ValidationException no demuestra rollback: también puede lanzarse en afterCommit.
            // Sin resultado transaccional explícito, no afirmar rechazo tras invocar el servicio.
            $incierto = $estado->servicioInvocado;
            $mensaje = $incierto
                ? 'El servicio no completó su respuesta. Consulta el cierre y su historial antes de otra solicitud.'
                : 'La solicitud fue rechazada. Revisa los campos y consulta la versión vigente.';
            return $this->resultadoSeguro(compact('mensaje', 'errores', 'incierto'), 422);
        } catch (AuthenticationException | AuthorizationException | ModelNotFoundException | HttpExceptionInterface $e) {
            if ($estado->servicioInvocado) {
                $codigo = match (true) {
                    $e instanceof AuthenticationException => 401,
                    $e instanceof AuthorizationException => 403,
                    $e instanceof ModelNotFoundException => 404,
                    default => $e->getStatusCode(),
                };
                return $this->resultadoSeguro(['mensaje' => 'El contexto de autorización o consulta cambió. No se puede confirmar el resultado; consulta el historial desde una sesión autorizada.', 'incierto' => true], $codigo);
            }
            throw $e;
        } catch (FalloCoordinacionCaja $e) {
            return $this->resultadoSeguro(['mensaje' => $e->resultadoIncierto
                ? 'El resultado es incierto. Consulta el cierre y su historial antes de intentar otra operación.'
                : 'No se pudo adquirir la coordinación. Consulta el estado antes de realizar una nueva solicitud.', 'incierto' => $e->resultadoIncierto], $e->resultadoIncierto ? 409 : 503);
        } catch (Throwable) {
            // Una excepción genérica también puede ocurrir después del commit. No afirmar rollback.
            return $this->resultadoSeguro(['mensaje' => 'No se pudo confirmar el resultado. Consulta el cierre y su historial antes de intentar otra operación.', 'incierto' => true], 409);
        }
    }
}
