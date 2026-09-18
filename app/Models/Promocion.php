<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Promocion extends Model
{
    use HasFactory;

    protected $table = 'promociones';

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
    ];
}