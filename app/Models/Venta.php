<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Venta extends Model
{
    use HasFactory;
    use \App\Models\Concerns\FiltraFechaLocal;
    protected $casts = ['fecha_hora'=>\App\Casts\FechaLocal::class];

    protected $table = 'ventas';
    public function pedido() { return $this->belongsTo(Pedido::class); }
    public function usuario() { return $this->belongsTo(User::class, 'usuario_id'); }
    protected $fillable = [
        'fecha_hora',
        'total',
        'metodo_pago',
        'monto_recibido',
        'cambio',
        'estado',
        'pedido_id',  // 👈 Agregado
        'usuario_id', // 👈 Agregado
    ];
}
