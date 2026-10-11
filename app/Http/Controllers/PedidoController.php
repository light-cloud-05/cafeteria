<?php
namespace App\Http\Controllers;
use App\Models\{Pedido,Producto,Promocion};
use App\Services\PedidoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class PedidoController extends Controller
{
    public function index(Request $request)
    {
        $datos=$request->validate(['fecha'=>'nullable|date_format:Y-m-d','estado'=>'nullable|in:Registrado,Pagado,Completado,Cancelado']);
        $pedidos=Pedido::with('usuario')->when($request->filled('fecha'),fn($q)=>$q->enFecha('fechaHora',$datos['fecha']))
            ->when($request->filled('estado'),fn($q)=>$q->where('estado',$datos['estado']))->latest('fechaHora')->paginate(25)->withQueryString();
        return view('pedidos.index',compact('pedidos'));
    }
    private function formulario(Pedido $pedido)
    {
        $productos=Producto::with(['categoria','stock'])->orderBy('nombre')->get();
        $promociones=Promocion::with('producto')->orderBy('nombre')->get();
        if($pedido->stock_reservado) foreach($productos as $p) { if($p->stock) $p->stock->stock_actual += $pedido->detalles->where('producto_id',$p->id)->sum('cantidad'); }
        $desdeCaja = request()->routeIs('admin.venta.pedido.*');
        return view('pedidos.form',compact('pedido','productos','promociones','desdeCaja'));
    }
    public function create() { return $this->formulario(new Pedido()); }
    private function validar(Request $request): array
    {
        return $request->validate(['detalles'=>'required|array|min:1|max:100',
            'detalles.*.producto_id'=>'required|integer|distinct|exists:productos,id',
            'detalles.*.cantidad'=>'required|integer|min:1|max:10000',
            'detalles.*.opcion'=>'nullable|string|max:255','promocion_id'=>'nullable|integer|exists:promociones,id']);
    }
    public function store(Request $request, PedidoService $servicio)
    {
        $datos=$this->validar($request);
        $pedido=DB::transaction(function() use($datos,$request,$servicio) {
            $calculo=$servicio->calcular($datos);$detalles=$calculo['detalles'];unset($calculo['detalles']);
            $fecha=now()->toDateString();
            DB::table('numeradores_pedidos')->insertOrIgnore(['fecha'=>$fecha,'ultimo'=>0]);
            $contador=DB::table('numeradores_pedidos')->where('fecha',$fecha)->lockForUpdate()->first();
            $numero=$contador->ultimo+1;DB::table('numeradores_pedidos')->where('fecha',$fecha)->update(['ultimo'=>$numero]);
            $pedido=Pedido::create($calculo+['fecha_numero'=>$fecha,'numero_diario'=>$numero]+['fechaHora'=>now(),'estado'=>'Registrado','usuario_id'=>$request->user()->id]);
            $pedido->detalles()->createMany($detalles);app(\App\Services\ReservaPedidoService::class)->mover($pedido);return $pedido;
        },3);
        return redirect()->route('admin.pedido.ver',$pedido)->with('success','Pedido registrado correctamente.')->with('borrador_guardado','pedido-'.auth()->id());
    }
    private function editable(Pedido $pedido): void
    {
        if($pedido->estado !== 'Registrado' || $pedido->venta()->exists()) throw ValidationException::withMessages(['pedido'=>'Solo se puede modificar o cancelar un pedido registrado y sin cobrar.']);
    }
    public function edit(int $id)
    {
        $pedido=Pedido::with('detalles')->findOrFail($id);$this->editable($pedido);return $this->formulario($pedido);
    }
    public function update(Request $request,int $id,PedidoService $servicio)
    {
        $datos=$this->validar($request);
        DB::transaction(function() use($id,$datos,$servicio) {
            $pedido=Pedido::lockForUpdate()->findOrFail($id);$this->editable($pedido);
            app(\App\Services\ReservaPedidoService::class)->mover($pedido,true);
            $calculo=$servicio->calcular($datos);$detalles=$calculo['detalles'];unset($calculo['detalles']);
            $pedido->update($calculo);$pedido->detalles()->delete();$pedido->detalles()->createMany($detalles);app(\App\Services\ReservaPedidoService::class)->mover($pedido);
        },3);
        $desdeCaja = $request->routeIs('admin.venta.pedido.*');
        return redirect()->route($desdeCaja ? 'admin.venta.crear' : 'admin.pedido.ver',$id)
            ->with('success',$desdeCaja ? 'Pedido corregido. Confirma el nuevo total con el cliente antes de cobrar.' : 'Pedido actualizado.')
            ->with('borrador_guardado','pedido-'.auth()->id().'-editar-'.$id);
    }
    public function cancelar(int $id)
    {
        DB::transaction(function() use($id) { $pedido=Pedido::lockForUpdate()->findOrFail($id);$this->editable($pedido);app(\App\Services\ReservaPedidoService::class)->mover($pedido,true);$pedido->update(['estado'=>'Cancelado']); });
        return redirect()->route('admin.pedido.ver',$id)->with('success','Pedido cancelado.');
    }
    public function estado(Request $request,int $id)
    {
        $datos=$request->validate(['estado'=>'required|in:Completado']);
        DB::transaction(function() use($id,$datos) {
            $pedido=Pedido::lockForUpdate()->findOrFail($id);
            if($pedido->estado !== 'Pagado' || !$pedido->venta()->exists()) throw ValidationException::withMessages(['estado'=>'Solo un pedido pagado puede completarse. El estado Pagado se asigna al registrar el cobro.']);
            $pedido->update($datos);
        },3);
        return redirect()->route('admin.pedido.ver',$id)->with('success','Pedido completado.');
    }
    public function show(int $id)
    {
        abort_unless(collect(['registrar_pedidos','modificar_pedidos','cambiar_estado_pedidos','consultar_historial_pedidos','cobrar_ventas'])->contains(fn($p)=>auth()->user()->tienePermiso($p)),403);
        $pedido=Pedido::with(['usuario','detalles.producto','venta','promocion'])->findOrFail($id);
        return view('pedidos.show',compact('pedido'));
    }
}
