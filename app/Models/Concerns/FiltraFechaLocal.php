<?php
namespace App\Models\Concerns;
use Illuminate\Support\Carbon;
trait FiltraFechaLocal
{
    public function scopeEnFecha($query,string $columna,string $fecha)
    {
        return $query->entreFechas($columna,$fecha,$fecha);
    }
    public function scopeEntreFechas($query,string $columna,?string $desde,?string $hasta)
    {
        if($desde) $query->where($columna,'>=',Carbon::parse($desde,config('app.timezone'))->startOfDay()->utc()->format('Y-m-d H:i:s'));
        if($hasta) $query->where($columna,'<',Carbon::parse($hasta,config('app.timezone'))->startOfDay()->addDay()->utc()->format('Y-m-d H:i:s'));
        return $query;
    }
}
