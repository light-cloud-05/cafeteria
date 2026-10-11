<?php
namespace App\Services;
use App\Models\{Pedido,Producto,MovimientoInventario};
use Illuminate\Validation\ValidationException;
class ReservaPedidoService {
    public function mover(Pedido $pedido, bool $devolver=false): void {
        if ($devolver && !$pedido->stock_reservado) return;
        if (!$devolver && $pedido->stock_reservado) return;
        foreach($pedido->detalles()->orderBy('producto_id')->get()->groupBy('producto_id') as $id=>$lineas){
            $producto=Producto::lockForUpdate()->findOrFail($id);
            $inventario=app(InventarioService::class);$stock=$inventario->stock($producto);
            $cantidad=(int)$lineas->sum('cantidad');
            if(!$devolver && (!$producto->disponible || !$producto->categoria?->estado || $stock->stock_actual<$cantidad)) throw ValidationException::withMessages(['pedido'=>'Stock insuficiente: '.$producto->nombre]);
            $inventario->actualizar($producto,$stock,(float)$stock->stock_actual+($devolver ? $cantidad : -$cantidad),auth()->id());
            MovimientoInventario::create(['tipo'=>$devolver ? 'entrada' : 'salida','cantidad'=>$cantidad,'fecha_hora'=>now(),'motivo'=>($devolver ? 'Devolución' : 'Reserva').' del pedido #'.$pedido->numero_diario,'producto_id'=>$id,'usuario_id'=>auth()->id()]);
        }
        $pedido->update(['stock_reservado'=>!$devolver]);
    }
}
