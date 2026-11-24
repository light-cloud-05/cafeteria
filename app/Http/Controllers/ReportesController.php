<?php
namespace App\Http\Controllers;
use App\Models\{Venta,Producto,MovimientoInventario};
use Illuminate\Http\Request;
class ReportesController extends Controller
{
    public function diarias(Request $request)
    {
        $datos=$request->validate(['fecha'=>'nullable|date_format:Y-m-d']);$fecha=$datos['fecha'] ?? now()->toDateString();
        $ventas=Venta::with(['usuario','pedido'])->enFecha('fecha_hora',$fecha)->where('estado','Completado')->orderBy('fecha_hora')->get();
        return $this->reporte('diarias',$ventas,compact('fecha'));
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
        $ventas=Venta::with('pedido')->entreFechas('fecha_hora',$desde,$hasta)->where('estado','Completado')->orderBy('fecha_hora')->get();
        return $this->reporte('pagos',$ventas,compact('desde','hasta'));
    }
    private function reporte(string $vista, $ventas, array $filtros)
    {
        try { $calculo=app(\App\Services\Reportes\VentasReporteService::class)->resumir($ventas); }
        catch (\UnexpectedValueException) {
            return response()->view('reportes.error',[],422)->header('Cache-Control','private, no-store');
        }
        $total=$calculo['totales']['total'];$cantidad=$ventas->count();
        return response()->view('reportes.'.$vista,$filtros+$calculo+compact('ventas','total','cantidad'))
            ->header('Cache-Control','private, no-store');
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
