<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Stock extends Model
{
    use HasFactory;
    use \App\Models\Concerns\FiltraFechaLocal;
    protected $casts = ['fecha_actualizacion'=>\App\Casts\FechaLocal::class];

    protected $table = 'stocks';

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    protected $fillable = [
        'stock_actual',
        'stock_minimo',
        'fecha_actualizacion',
        'producto_id',
    ];
}
