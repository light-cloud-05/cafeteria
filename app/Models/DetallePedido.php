<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetallePedido extends Model
{
    use HasFactory;

    protected $table = 'detalle_pedidos';

    protected $fillable = [
        'precioUnitario',
        'cantidad',
        'opcion',
        'subtotal',
        'pedido_id',
        'producto_id',
        'promocion_id',
    ];
}