<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bitacora extends Model
{
    use HasFactory;
    use \App\Models\Concerns\FiltraFechaLocal;

    protected $table = 'bitacoras';

    protected $fillable = [
        'tablaAfectada',
        'idRegistroAfectado',
        'accion',
        'valoresAnteriores',
        'valoresNuevos',
        'fechaHora',
        'idUsuario',
        'ip',
    ];

    protected $casts = [
        'fechaHora' => \App\Casts\FechaLocal::class,
    ];

    protected static function booted(): void
    {
        static::creating(function (Bitacora $registro) {
            if ($registro->ip === null && (!app()->runningInConsole() || app()->runningUnitTests())) {
                $registro->ip = request()->ip();
            }
        });
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'idUsuario')->withTrashed();
    }
}
