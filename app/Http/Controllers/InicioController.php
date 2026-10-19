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
        if ($usuario->tienePermiso('ver_reportes')) {
            $metricas[] = ['titulo' => 'Ventas de hoy', 'valor' => 'Bs '.number_format(Venta::enFecha('fecha_hora', now()->toDateString())->where('estado', 'Completado')->sum('total'), 2), 'detalle' => 'Total de ventas completadas', 'icono' => 'reportes', 'ruta' => 'admin.reportes.diarias'];
        }
        if ($usuario->tienePermiso('consultar_historial_pedidos')) {
            $metricas[] = ['titulo' => 'Pedidos de hoy', 'valor' => Pedido::enFecha('fechaHora', now()->toDateString())->count(), 'detalle' => 'Pedidos registrados durante el día', 'icono' => 'pedidos', 'ruta' => 'admin.indexpedido'];
        }
        if ($usuario->tienePermiso('ver_agotados')) {
            $metricas[] = ['titulo' => 'Stock bajo', 'valor' => Stock::whereColumn('stock_actual', '<=', 'stock_minimo')->count(), 'detalle' => 'Productos que requieren reposición', 'icono' => 'inventario', 'ruta' => 'admin.stock.agotados'];
        }
        return view('welcome', compact('metricas'));
    }
}
