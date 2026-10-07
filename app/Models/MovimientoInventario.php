<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MovimientoInventario extends Model
{
    use HasFactory;
    use \App\Models\Concerns\FiltraFechaLocal;
    protected $casts = ['fecha_hora'=>\App\Casts\FechaLocal::class];

    protected $table = 'movimiento_inventarios';

    public function producto() { return $this->belongsTo(Producto::class); }
    public function usuario() { return $this->belongsTo(User::class, 'usuario_id'); }
    public function proveedor() { return $this->belongsTo(Proveedor::class); }

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
