<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\FechaLocal;
use App\Services\Pagos\EstadoPago;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Support\Facades\DB;
use LogicException;

class TransaccionPago extends Model
{
    protected $table = 'transacciones_pago';
    protected $guarded = ['*']; // El servicio asigna exclusivamente campos conocidos.
    protected $casts = [
        'estado' => EstadoPago::class, 'monto' => 'decimal:2',
        'vence_en' => FechaLocal::class, 'confirmado_en' => FechaLocal::class, 'resuelto_en' => FechaLocal::class,
    ];

    public function pedido() { return $this->belongsTo(Pedido::class); }
    public function usuario() { return $this->belongsTo(User::class)->withTrashed(); }
    public function venta() { return $this->belongsTo(Venta::class); }

    protected function performUpdate(Builder $query)
    {
        if (DB::transactionLevel() !== 1
            || static::whereKey($this->getKey())->lockForUpdate()->toBase()->value('estado') !== EstadoPago::PENDIENTE->value) {
            throw new LogicException('Resolver un intento exige una transacción y su estado pendiente vigente.');
        }
        EstadoPago::from($this->getRawOriginal('estado'))->verificarTransicion($this->estado);
        $permitidos = ['estado', 'venta_id', 'confirmado_en', 'resuelto_en', 'codigo_resultado', 'updated_at'];
        if (array_diff(array_keys($this->getDirty()), $permitidos)
            || !$this->resuelto_en || !$this->codigo_resultado
            || (($this->estado === EstadoPago::APROBADO) !== ($this->venta_id !== null && $this->confirmado_en !== null))
            || ($this->estado !== EstadoPago::APROBADO && ($this->venta_id !== null || $this->confirmado_en !== null))) {
            throw new LogicException('El intento no permite alterar su identidad, importe o resolución.');
        }
        return parent::performUpdate($query);
    }

    protected function performDeleteOnModel() { throw new LogicException('El historial de pagos se conserva.'); }
    protected function incrementOrDecrement($column, $amount, $extra, $method) { throw new LogicException('El intento es inmutable.'); }
    protected function incrementOrDecrementEach(array $columns, array $extra, string $method) { throw new LogicException('El intento es inmutable.'); }
}
