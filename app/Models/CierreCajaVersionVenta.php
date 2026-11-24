<?php

namespace App\Models;

use App\Casts\FechaLocal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

class CierreCajaVersionVenta extends Model
{
    use \App\Models\Concerns\HistorialFinanciero;
    protected $table = 'cierre_caja_version_ventas';

    protected function performUpdate(Builder $query)
    {
        throw new LogicException('El detalle de una versión de caja es histórico.');
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
        throw new LogicException('No se puede eliminar el detalle de una versión de caja.');
    }

    protected $fillable = [
        'cierre_caja_version_id', 'venta_id', 'fecha_cobro', 'metodo_pago',
        'total', 'monto_efectivo', 'monto_qr', 'monto_tarjeta', 'origen',
    ];

    protected $casts = [
        'fecha_cobro' => FechaLocal::class,
        'total' => 'decimal:2',
        'monto_efectivo' => 'decimal:2',
        'monto_qr' => 'decimal:2',
        'monto_tarjeta' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('El detalle de una versión de caja es histórico.');
        });
        static::deleting(function () {
            throw new LogicException('No se puede eliminar el detalle de una versión de caja.');
        });
    }

    public function version()
    {
        return $this->belongsTo(CierreCajaVersion::class, 'cierre_caja_version_id');
    }

    public function venta()
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }
}
