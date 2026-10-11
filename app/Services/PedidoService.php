<?php
namespace App\Services;
use App\Models\{Producto,Promocion};
use Illuminate\Validation\ValidationException;
class PedidoService
{
    public function calcular(array $datos): array
    {
        $detalles=[]; $subtotal=0; $baseDescuento=0;
        $promocion = empty($datos['promocion_id']) ? null : Promocion::lockForUpdate()->findOrFail($datos['promocion_id']);
        if ($promocion && !$promocion->vigente()) throw ValidationException::withMessages(['promocion_id'=>'La promoción no está vigente o no está configurada.']);
        foreach(collect($datos['detalles'])->sortBy('producto_id') as $i=>$linea) {
            $producto = Producto::with(['categoria','stock'])->lockForUpdate()->findOrFail($linea['producto_id']);
            if (!$producto->vendible() || $linea['cantidad'] > $producto->stock->stock_actual) {
                throw ValidationException::withMessages(["detalles.$i.cantidad"=>'Producto agotado, inactivo o stock insuficiente: '.$producto->nombre]);
            }
            $precio = (int)round((float)$producto->precio*100);
            $importe = $precio*$linea['cantidad']; $subtotal += $importe;
            $aplica = $promocion && $promocion->aplicaSobre === 'Producto' && $promocion->aplicaProducto($producto->id);
            if ($aplica) $baseDescuento += $importe;
            $detalles[]=['producto_id'=>$producto->id,'cantidad'=>$linea['cantidad'],'opcion'=>$linea['opcion'] ?? '',
                'precioUnitario'=>$precio/100,'subtotal'=>$importe/100,'promocion_id'=>$aplica ? $promocion->id : null];
        }
        if ($subtotal > 99999999) throw ValidationException::withMessages(['detalles'=>'El importe excede el límite permitido.']);
        if ($promocion && $promocion->aplicaSobre === 'Pedido') $baseDescuento = $subtotal;
        if ($promocion && $promocion->aplicaSobre === 'Producto' && $baseDescuento === 0) {
            throw ValidationException::withMessages(['promocion_id'=>'Agrega el producto al que corresponde la promoción.']);
        }
        $descuento = $promocion ? $promocion->descuentoCentavos($baseDescuento) : 0;
        return ['detalles'=>$detalles,'subtotal'=>$subtotal/100,'descuento'=>$descuento/100,
            'total'=>($subtotal-$descuento)/100,'promocion_id'=>$promocion?->id];
    }
}
