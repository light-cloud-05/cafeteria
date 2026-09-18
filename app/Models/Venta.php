<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Venta extends Model
{
    use HasFactory;

    protected $table = 'ventas';
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