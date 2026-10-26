<?php

namespace App\Models\Builders;

use Illuminate\Database\Eloquent\Builder;
use LogicException;

/** Impide reescrituras genéricas Eloquent; SQL directo sigue siendo un límite de privilegios de BD. */
class HistorialFinancieroBuilder extends Builder
{
    public function update(array $values) { throw new LogicException('El historial financiero solo admite nuevos registros.'); }
    public function upsert(array $values, $uniqueBy, $update = null) { throw new LogicException('No se permite sobrescribir el historial.'); }
    public function delete() { throw new LogicException('No se permite eliminar el historial financiero.'); }
    public function forceDelete() { throw new LogicException('No se permite eliminar el historial financiero.'); }
    public function touch($column = null) { throw new LogicException('No se permite modificar el historial financiero.'); }
    public function increment($column, $amount = 1, array $extra = []) { throw new LogicException('No se permite modificar el historial financiero.'); }
    public function decrement($column, $amount = 1, array $extra = []) { throw new LogicException('No se permite modificar el historial financiero.'); }
    public function incrementEach(array $columns, array $extra = []) { throw new LogicException('No se permite modificar el historial financiero.'); }
    public function decrementEach(array $columns, array $extra = []) { throw new LogicException('No se permite modificar el historial financiero.'); }
    public function updateOrInsert(array $attributes, array|callable $values = []) { throw new LogicException('No se permite sobrescribir el historial financiero.'); }
    public function truncate() { throw new LogicException('No se permite vaciar el historial financiero.'); }
}
