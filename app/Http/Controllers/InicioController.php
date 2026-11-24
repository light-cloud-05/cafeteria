<?php
namespace App\Http\Controllers;
use App\Models\{Venta, Pedido, Stock};
use Illuminate\Http\Request;
class InicioController extends Controller
{
    public function index(Request $request)
    {
        $usuario = $request->user();
        $metricas = [];
        $simulacionVentas = false;
        if ($usuario->tienePermiso('ver_reportes')) {
            $ventas = Venta::enFecha('fecha_hora', now()->toDateString())->where('estado','Completado')->get();
            $simulacionVentas = \App\Services\Pagos\ContextoLecturaPagos::verificar($ventas);
            try {
                $calculo = app(\App\Services\Reportes\VentasReporteService::class)->resumir($ventas);
                $valor = \App\Support\ImporteReporteVista::formatear($calculo['totales']['total']);
            } catch (\UnexpectedValueException) { $valor = 'No disponible'; }
            $metricas[] = ['titulo'=>'Ventas de hoy','valor'=>$valor,'detalle'=>'Total de ventas completadas','icono'=>'reportes','ruta'=>'admin.reportes.diarias'];
        }
        if ($usuario->tienePermiso('consultar_historial_pedidos')) {
            $metricas[] = ['titulo' => 'Pedidos de hoy', 'valor' => Pedido::enFecha('fechaHora', now()->toDateString())->count(), 'detalle' => 'Pedidos registrados durante el día', 'icono' => 'pedidos', 'ruta' => 'admin.indexpedido'];
        }
        if ($usuario->tienePermiso('ver_agotados')) {
            $metricas[] = ['titulo' => 'Stock bajo', 'valor' => Stock::whereColumn('stock_actual', '<=', 'stock_minimo')->count(), 'detalle' => 'Productos que requieren reposición', 'icono' => 'inventario', 'ruta' => 'admin.stock.agotados'];
        }
        return view('welcome', compact('metricas','simulacionVentas'));
    }
}
