<?php

declare(strict_types=1);

namespace App\Services\Caja;

use App\Models\{Rol, User};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\{Auth, DB};
use LogicException;

final class AutorizacionCaja
{
    public const CONSULTAR = 'consultar_caja';
    public const CONFIRMAR = 'confirmar_cierre_caja';
    public const RECTIFICAR = 'rectificar_cierre_caja';
    public const COBRAR = 'cobrar_ventas';

    /** El cobro conserva su permiso propio; no requiere permisos de consulta o cierre. */
    public function cobro(): User
    {
        return $this->comprobar([self::COBRAR]);
    }

    public function revalidarCobro(User $previo): User
    {
        return $this->comprobar([self::COBRAR], $previo);
    }

    public function consulta(): User
    {
        return $this->comprobar([self::CONSULTAR]);
    }

    public function confirmacion(): User
    {
        return $this->comprobar([self::CONSULTAR, self::CONFIRMAR]);
    }

    /** Preparado para la futura operación; no implementa ninguna rectificación. */
    public function rectificacion(): User
    {
        return $this->comprobar([self::CONSULTAR, self::RECTIFICAR]);
    }

    public function revalidarConfirmacion(User $previo): User
    {
        return $this->comprobar([self::CONSULTAR, self::CONFIRMAR], $previo);
    }

    public function revalidarRectificacion(User $previo): User
    {
        return $this->comprobar([self::CONSULTAR, self::RECTIFICAR], $previo);
    }

    private function comprobar(array $permisos, ?User $previo = null): User
    {
        $identidad = Auth::user();
        if (!$identidad instanceof User || !$identidad->exists) {
            throw new AuthenticationException('Se requiere un responsable autenticado.');
        }
        if (!$previo && DB::transactionLevel() !== 0) {
            throw new LogicException('La autorización inicial debe realizarse antes de iniciar una transacción.');
        }
        if ($previo) {
            if (DB::transactionLevel() === 0) {
                throw new LogicException('La revalidación bloqueada requiere la transacción coordinada.');
            }
            if ((string) $identidad->getKey() !== (string) $previo->getKey()) {
                throw new AuthorizationException('El contexto del responsable cambió.');
            }
            // RolController::update() bloquea primero el rol y después sincroniza el pivote.
            // No invertir ese orden tomando el usuario antes del rol. No buscar otro rol bajo bloqueo.
            $rol = Rol::whereKey($previo->rol_id)->lockForUpdate()->first();
            $actor = User::whereKey($identidad->getKey())->lockForUpdate()->first();
            if ($actor && (string) $actor->rol_id !== (string) $previo->rol_id) {
                throw new AuthorizationException('El rol del responsable cambió; inicia una nueva operación.');
            }
        } else {
            // Releer desde la base: nunca confiar en relaciones o estados cacheados de Auth::user().
            $actor = User::whereKey($identidad->getKey())->first();
            $rol = $actor?->rol;
        }
        if (!$actor || (int) $actor->estado !== 1 || !$rol || !(bool) $rol->estado) {
            throw new AuthenticationException('El responsable o su rol no están activos.');
        }
        $actor->setRelation('rol', $rol);
        foreach ($permisos as $permiso) {
            // Misma fuente y semántica que VerificarPermiso. Sin excepciones por nombre del rol.
            if (!$actor->tienePermiso($permiso)) {
                throw new AuthorizationException('No tienes permiso para esta operación de caja.');
            }
            // Lectura actual de InnoDB: no aceptar un permiso revocado por una snapshot anterior.
            // El bloqueo del rol serializa este punto con RolController::update()->sync().
            if ($previo && !$rol->permisos()->where('permisos.nombre', $permiso)
                ->orderBy('permisos.id')->lockForUpdate()->first(['permisos.id'])) {
                throw new AuthorizationException('El permiso de caja fue revocado.');
            }
        }

        return $actor;
    }
}
