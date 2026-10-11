<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Promocion extends Model
{
    use HasFactory;
    protected $casts = ['producto_ids'=>'array'];
    public function productosAplicables(): array { return $this->producto_ids ?: ($this->producto_id ? [(int)$this->producto_id] : []); }
    public function aplicaProducto(int $id): bool { return $this->aplicaSobre === 'Pedido' || in_array($id,$this->productosAplicables()); }

    protected $table = 'promociones';
    public function producto() { return $this->belongsTo(Producto::class); }
    public function vigente(): bool
    {
        return $this->estadoAplicacion() === 'Activa y vigente';
    }
    public function estadoAplicacion(): string
    {
        $ahora = now();
        if (!$this->activo) return 'Inactiva';
        if (!in_array($this->tipo, ['Porcentaje','Monto fijo'])
            || !in_array($this->aplicaSobre, ['Pedido','Producto'])
            || ($this->aplicaSobre === 'Producto' && !$this->productosAplicables())) return 'Configuración incompleta';
        if ($ahora->toDateString() < $this->fechaInicio) return 'Programada: todavía no inicia';
        if ($ahora->toDateString() > $this->fechaFin) return 'Vencida';
        $inicio = $this->horaInicio ? substr($this->horaInicio,0,5) : null;
        $fin = $this->horaFin ? substr($this->horaFin,0,5) : null;
        if (($inicio && $ahora->format('H:i') < $inicio) || ($fin && $ahora->format('H:i') > $fin)) {
            return 'Fuera de horario: '.$inicio.' a '.$fin.' (Bolivia)';
        }
        return 'Activa y vigente';
    }
    public function descuentoCentavos(int $base): int
    {
        $descuento = $this->tipo === 'Porcentaje' ? (int) round($base * (float) $this->valor / 100) : (int) round((float) $this->valor * 100);
        if ($this->topeDescuento !== null) $descuento = min($descuento, (int) round((float) $this->topeDescuento * 100));
        return max(0, min($base, $descuento));
    }

    protected $fillable = [
        'nombre',
        'tipo',
        'aplicaSobre',
        'valor',
        'topeDescuento',
        'fechaInicio',
        'fechaFin',
        'horaInicio',
        'horaFin',
        'activo',
        'producto_id', 'producto_ids',
    ];
}
