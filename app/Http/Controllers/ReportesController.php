<?php
namespace App\Http\Controllers;
use App\Models\{Venta,Producto,MovimientoInventario};
use Illuminate\Http\Request;
class ReportesController extends Controller
{
    public function diarias(Request $request)
    {
        $datos=$request->validate(['fecha'=>'nullable|date_format:Y-m-d']);$fecha=$datos['fecha'] ?? now()->toDateString();
        $ventas=Venta::with('usuario')->enFecha('fecha_hora',$fecha)->where('estado','Completado')->orderBy('fecha_hora')->get();
        $total=$ventas->sum('total');$cantidad=$ventas->pluck('pedido_id')->unique()->count();
        return view('reportes.diarias',compact('ventas','total','cantidad','fecha'));
    }
    private function rango(Request $request): array
    {
        $datos=$request->validate(['desde'=>'nullable|date_format:Y-m-d','hasta'=>'nullable|date_format:Y-m-d']);
        $desde=$datos['desde'] ?? now()->toDateString();$hasta=$datos['hasta'] ?? $desde;
        if($hasta < $desde) throw \Illuminate\Validation\ValidationException::withMessages(['hasta'=>'La fecha final no puede ser anterior a la inicial.']);
        return [$desde,$hasta];
    }
    public function pagos(Request $request)
    {
        [$desde,$hasta]=$this->rango($request);
        $ventas=Venta::entreFechas('fecha_hora',$desde,$hasta)->where('estado','Completado')->get();
        $resumen=collect();
        foreach($ventas as $venta) foreach(['QR'=>(float)$venta->monto_qr,'Efectivo'=>(float)$venta->monto_efectivo] as $metodo=>$monto) {
            if($monto<=0) continue;
            $fila=$resumen->get($metodo,['cantidad'=>0,'total'=>0]);$fila['cantidad']++;$fila['total']+=$monto;$resumen->put($metodo,$fila);
        }
        return view('reportes.pagos',compact('resumen','desde','hasta'));
    }
    public function inventario(Request $request)
    {
        $datos=$request->validate(['producto_id'=>'nullable|integer|exists:productos,id','modo'=>'nullable|in:stock,movimientos']);
        [$desde,$hasta]=$this->rango($request);$modo=$datos['modo'] ?? 'stock';
        $productos=Producto::orderBy('nombre')->get();
        $stocks=Producto::with('stock')->when($request->filled('producto_id'),fn($q)=>$q->whereKey($datos['producto_id']))->orderBy('nombre')->get();
        $movimientos=MovimientoInventario::with(['producto','usuario','proveedor'])->when($request->filled('producto_id'),fn($q)=>$q->where('producto_id',$datos['producto_id']))
            ->entreFechas('fecha_hora',$desde,$hasta)->latest('fecha_hora')->paginate(30)->withQueryString();
        return view('reportes.inventario',compact('productos','stocks','movimientos','modo','desde','hasta'));
    }
}
