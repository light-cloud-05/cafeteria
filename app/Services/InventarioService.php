<?php
namespace App\Services;

use App\Models\{Producto, Stock, MovimientoInventario, Bitacora};
use Illuminate\Validation\ValidationException;

class InventarioService
{
    // El llamador abre una transacción y bloquea primero el producto.
    public function stock(Producto $producto): Stock
    {
        return Stock::firstOrCreate(['producto_id' => $producto->id], ['stock_actual' => 0, 'stock_minimo' => 0]);
    }

    public function actualizar(Producto $producto, Stock $stock, float $nuevo, int $usuario): void
    {
        $antes = (float) $stock->stock_actual;
        $agotadoAntes = $antes <= (float) $stock->stock_minimo;
        $agotadoAhora = $nuevo <= (float) $stock->stock_minimo;
        $stock->update(['stock_actual' => $nuevo, 'fecha_actualizacion' => now()]);
        if ($agotadoAntes !== $agotadoAhora) {
            Bitacora::create([
                'tablaAfectada' => 'stocks', 'idRegistroAfectado' => $stock->id,
                'accion' => $agotadoAhora ? 'PRODUCTO_AGOTADO' : 'PRODUCTO_REPUESTO',
                'valoresAnteriores' => json_encode(['stock_actual' => $antes]),
                'valoresNuevos' => json_encode(['stock_actual' => $nuevo]),
                'fechaHora' => now(), 'idUsuario' => $usuario,
            ]);
        }
        // La disponibilidad comercial manual se conserva. El aviso depende del stock.
    }
}
