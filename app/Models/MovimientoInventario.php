<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MovimientoInventario extends Model
{
    use HasFactory;

    protected $table = 'movimiento_inventarios';

    protected $fillable = [
        'tipo',
        'cantidad',
        'fecha_hora',
        'motivo',
        'usuario_id',
        'producto_id',
        'proveedor_id'       
    ];
}