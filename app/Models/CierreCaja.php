<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CierreCaja extends Model
{
    use HasFactory;

    protected $table = 'cierre_cajas';

    protected $fillable = [
            'fechaInicio',
            'fechaFin',
            'totalSistema',
            'totalDeclarado',
            'diferencia',
            'observacion',
            'idUsuario'
    ];
}