<?php
namespace App\Http\Controllers;
use App\Models\{Venta,Pedido};
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
class VentaController extends Controller
{
    public function siguiente(int $id)
    {
        $siguiente=Pedido::where('estado','Registrado')->doesntHave('venta')
            ->where('id','<>',$id)->orderBy('fechaHora')->orderBy('id')->first();
        return $siguiente ? redirect()->route('admin.venta.crear',$siguiente->id)
            : redirect()->route('admin.indexventa')->with('success','No hay más pedidos pendientes de cobro.');
    }
    public function index()
    {
        $pedidos=Pedido::where('estado','Registrado')->doesntHave('venta')->with('usuario')->latest('fechaHora')->paginate(25);
        $ventas=Venta::with(['usuario','pedido'])->latest('fecha_hora')->limit(25)->get();
        \App\Services\Pagos\ContextoLecturaPagos::verificar($ventas);
        return view('ventas.index',compact('pedidos','ventas'));
    }
    public function create(int $id)
    {
        $pedido=Pedido::with(['detalles.producto.stock','detalles.producto.categoria','venta'])->findOrFail($id);$this->pendiente($pedido);
        $conflictos=$pedido->detalles->groupBy('producto_id')->filter(function($lineas) use($pedido) {
            $producto=$lineas->first()->producto;
            return !$producto || (!$pedido->stock_reservado && (!$producto->vendible() || $lineas->sum('cantidad') > $producto->stock->stock_actual));
        });
        return view('ventas.form',compact('pedido','conflictos'));
    }
    private function pendiente(Pedido $pedido): void
    {
        if($pedido->estado !== 'Registrado' || $pedido->venta()->exists()) throw ValidationException::withMessages(['pedido'=>'Este pedido ya fue cobrado o no está pendiente de cobro.']);
    }
    public function store(Request $request,int $id)
    {
        $datos=$request->validate(['metodo_pago'=>'required|in:Efectivo,QR,Mixto','monto_recibido'=>'nullable|required_if:metodo_pago,Efectivo,Mixto|numeric|min:0|max:999999.99|decimal:0,2','monto_qr'=>'nullable|required_if:metodo_pago,Mixto|numeric|gt:0|max:999999.99|decimal:0,2','version_pedido'=>'required|string|size:64']);
        app(\App\Services\Pagos\FinalizarCobroService::class)->presencial($id,$datos);
        return redirect()->route('admin.pedido.ver',$id)->with('success','Venta registrada; pedido pagado y reserva de stock confirmada.');
    }
}
