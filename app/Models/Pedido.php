<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pedido extends Model
{
    use HasFactory;
    use \App\Models\Concerns\FiltraFechaLocal;
    protected $casts = ['fechaHora'=>\App\Casts\FechaLocal::class];

    protected $table = 'pedidos';

    public function usuario() { return $this->belongsTo(User::class, 'usuario_id'); }
    public function detalles() { return $this->hasMany(DetallePedido::class); }
    public function venta() { return $this->hasOne(Venta::class); }
    public function promocion() { return $this->belongsTo(Promocion::class); }
    public function versionCobro(): string
    {
        return hash('sha256',json_encode([
            'estado'=>$this->estado,'total'=>(string)$this->total,'descuento'=>(string)$this->descuento,
            'detalles'=>$this->detalles()->orderBy('id')->get(['id','producto_id','cantidad','precioUnitario','subtotal','opcion'])->toArray(),
        ],JSON_THROW_ON_ERROR));
    }

    protected $fillable = [
        'fechaHora',
        'total',
        'subtotal',
        'descuento',
        'promocion_id',
        'estado',
        'usuario_id',
    ];
}
