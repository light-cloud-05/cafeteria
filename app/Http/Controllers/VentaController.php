<?php
namespace App\Http\Controllers;
use App\Models\{Venta,Pedido,Producto,MovimientoInventario};
use App\Services\InventarioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
    public function store(Request $request,int $id,InventarioService $inventario)
    {
        $datos=$request->validate(['metodo_pago'=>'required|in:Efectivo,QR,Mixto','monto_recibido'=>'nullable|required_if:metodo_pago,Efectivo,Mixto|numeric|min:0|max:999999.99|decimal:0,2','monto_qr'=>'nullable|required_if:metodo_pago,Mixto|numeric|gt:0|max:999999.99|decimal:0,2','version_pedido'=>'required|string|size:64']);
        DB::transaction(function() use($id,$datos,$request,$inventario) {
            $pedido=Pedido::lockForUpdate()->findOrFail($id);$this->pendiente($pedido);
            if (!hash_equals($pedido->versionCobro(),$datos['version_pedido'])) {
                throw ValidationException::withMessages(['pedido'=>'El pedido cambió desde que abriste el cobro. Recarga la página y confirma el nuevo total con el cliente.']);
            }
            $detalles=$pedido->detalles()->orderBy('producto_id')->get();
            if($detalles->isEmpty()) throw ValidationException::withMessages(['pedido'=>'El pedido no tiene productos.']);
            $subtotal=(int)round($detalles->sum('subtotal')*100);
            $descuento=(int)round((float)$pedido->descuento*100);
            $total=$subtotal-$descuento;
            if($total < 0 || $total > 99999999) throw ValidationException::withMessages(['pedido'=>'Importe del pedido fuera del límite permitido.']);
            $qr=$datos['metodo_pago']==='QR' ? $total : ($datos['metodo_pago']==='Mixto' ? (int)round((float)$datos['monto_qr']*100) : 0);
            if($datos['metodo_pago']==='Mixto' && $qr >= $total) throw ValidationException::withMessages(['monto_qr'=>'En un pago combinado el QR debe ser menor al total.']);
            $efectivo=$datos['metodo_pago']==='QR' ? 0 : (int)round((float)$datos['monto_recibido']*100);
            $recibido=$qr+$efectivo;
            if($recibido < $total) throw ValidationException::withMessages(['monto_recibido'=>'El monto recibido es menor al total.']);
            app(\App\Services\ReservaPedidoService::class)->mover($pedido);
            Venta::create(['pedido_id'=>$pedido->id,'usuario_id'=>$request->user()->id,'fecha_hora'=>now(),'metodo_pago'=>$datos['metodo_pago'],
                'monto_qr'=>$qr/100,'monto_efectivo'=>($total-$qr)/100,'total'=>$total/100,'monto_recibido'=>$recibido/100,'cambio'=>($recibido-$total)/100,'estado'=>'Completado']);
            $pedido->update(['estado'=>'Pagado','total'=>$total/100]);
        });
        return redirect()->route('admin.pedido.ver',$id)->with('success','Venta registrada; pedido pagado y reserva de stock confirmada.');
    }
}
