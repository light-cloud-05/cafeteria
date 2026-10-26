<?php

namespace App\Models;

use App\Casts\FechaLocal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

class CierreCajaVersion extends Model
{
    use \App\Models\Concerns\HistorialFinanciero;
    protected $table = 'cierre_caja_versiones';

    // Estos métodos también protegen saveQuietly/deleteQuietly, que omiten los eventos.
    protected function performUpdate(Builder $query)
    {
        throw new LogicException('Una versión de caja se conserva: registra una nueva versión.');
    }

    protected function incrementOrDecrement($column, $amount, $extra, $method)
    {
        throw new LogicException('Los importes de caja no se modifican: registra una nueva versión.');
    }

    protected function incrementOrDecrementEach(array $columns, array $extra, string $method)
    {
        throw new LogicException('Los importes de caja no se modifican: registra una nueva versión.');
    }

    protected function performDeleteOnModel()
    {
        throw new LogicException('No se puede eliminar una versión de caja.');
    }

    protected $fillable = [
        'cierre_caja_id', 'numero_version', 'responsable_id', 'registrado_en',
        'total_ventas', 'efectivo_esperado', 'qr_registrado', 'efectivo_contado',
        'observacion', 'motivo_correccion',
    ];

    protected $casts = [
        'numero_version' => 'integer',
        'registrado_en' => FechaLocal::class,
        'total_ventas' => 'decimal:2',
        'efectivo_esperado' => 'decimal:2',
        'qr_registrado' => 'decimal:2',
        'efectivo_contado' => 'decimal:2',
        'diferencia' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Una versión de caja se conserva: registra una nueva versión.');
        });
        static::deleting(function () {
            throw new LogicException('No se puede eliminar una versión de caja.');
        });
    }

    public function cierre()
    {
        return $this->belongsTo(CierreCaja::class, 'cierre_caja_id');
    }

    public function responsable()
    {
        return $this->belongsTo(User::class, 'responsable_id')->withTrashed();
    }

    public function detalles()
    {
        return $this->hasMany(CierreCajaVersionVenta::class, 'cierre_caja_version_id');
    }
}
