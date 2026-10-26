<?php

namespace App\Models\Concerns;

use App\Models\Builders\HistorialFinancieroBuilder;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

trait HistorialFinanciero
{
    public function newEloquentBuilder($query)
    {
        return new HistorialFinancieroBuilder($query);
    }

    protected function performUpdate(Builder $query)
    {
        throw new LogicException('El historial financiero no se modifica.');
    }

    protected function performDeleteOnModel()
    {
        throw new LogicException('El historial financiero no se elimina.');
    }

    protected function incrementOrDecrement($column, $amount, $extra, $method)
    {
        throw new LogicException('El historial financiero no se modifica.');
    }

    protected function incrementOrDecrementEach(array $columns, array $extra, string $method)
    {
        throw new LogicException('El historial financiero no se modifica.');
    }
}
