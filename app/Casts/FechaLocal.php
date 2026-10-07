<?php
namespace App\Casts;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Support\Carbon;
use DateTimeInterface;
class FechaLocal implements CastsAttributes
{
    public function get($model,string $key,$value,array $attributes)
    {
        return $value === null ? null : Carbon::parse($value,'UTC')->setTimezone(config('app.timezone'));
    }
    public function set($model,string $key,$value,array $attributes)
    {
        if($value === null) return null;
        $fecha=$value instanceof DateTimeInterface ? Carbon::instance($value) : Carbon::parse($value,config('app.timezone'));
        return $fecha->copy()->utc()->format('Y-m-d H:i:s');
    }
}
