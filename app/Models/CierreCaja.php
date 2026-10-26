<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

class CierreCaja extends Model
{
    use HasFactory;
    use \App\Models\Concerns\HistorialFinanciero;

    protected $table = 'cierre_cajas';

    // Las correcciones se anexan como versiones; la cabecera y los históricos no se reescriben.
    protected function performUpdate(Builder $query)
    {
        throw new LogicException('El cierre de caja se conserva: registra una nueva versión.');
    }

    protected function incrementOrDecrement($column, $amount, $extra, $method)
    {
        throw new LogicException('Los importes de caja no se modifican: registra una nueva versión.');
    }

    protected function incrementOrDecrementEach(array $columns, array $extra, string $method)
    {
        throw new LogicException('Los importes de caja no se modifican: registra una nueva versión.');
    }

    protected function performDeleteOnModel()
    {
        throw new LogicException('No se puede eliminar un cierre de caja.');
    }

    protected $casts = [
        'fecha_operativa' => 'date:Y-m-d',
        'totalSistema' => 'decimal:2',
        'totalDeclarado' => 'decimal:2',
        'diferencia' => 'decimal:2',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'idUsuario')->withTrashed();
    }

    public function versiones()
    {
        return $this->hasMany(CierreCajaVersion::class, 'cierre_caja_id');
    }

    public function versionVigente()
    {
        return $this->hasOne(CierreCajaVersion::class, 'cierre_caja_id')
            ->ofMany('numero_version', 'max');
    }

    public function scopeOficiales($query)
    {
        return $query->whereNotNull('fecha_operativa');
    }

    protected $fillable = [
            'fecha_operativa',
            'fechaInicio',
            'fechaFin',
            'totalSistema',
            'totalDeclarado',
            'diferencia',
            'observacion',
            'idUsuario'
    ];
}
