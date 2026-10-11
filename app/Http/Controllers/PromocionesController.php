<?php
namespace App\Http\Controllers;
use App\Models\{Promocion,Producto};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class PromocionesController extends Controller
{
    public function consultar()
    {
        abort_unless(collect(['registrar_pedidos','modificar_pedidos','cobrar_ventas','gestion_promociones'])
            ->contains(fn($p)=>auth()->user()->tienePermiso($p)),403);
        return $this->index();
    }
    public function index() { $promociones=Promocion::with('producto')->orderBy('nombre')->get();$productos=Producto::orderBy('nombre')->get();return view('promociones.index',compact('promociones','productos')); }
    public function create() { $promocion=new Promocion(['activo'=>1,'tipo'=>'Porcentaje','aplicaSobre'=>'Pedido']);return $this->formulario($promocion); }
    public function edit(int $id) { return $this->formulario(Promocion::findOrFail($id)); }
    private function formulario(Promocion $promocion) { $productos=Producto::with('stock')->orderBy('nombre')->get();return view('promociones.form',compact('promocion','productos')); }
    private function validar(Request $request,?Promocion $promocion=null): array
    {
        $datos=$request->validate([
            'nombre'=>['required','string','max:100',Rule::unique('promociones','nombre')->ignore($promocion?->id)],
            'tipo'=>'required|in:Porcentaje,Monto fijo','aplicaSobre'=>'required|in:Producto,Pedido',
            'producto_id'=>'nullable|integer|exists:productos,id',
            'producto_ids'=>['nullable',Rule::requiredIf($request->aplicaSobre==='Producto' && !$request->filled('producto_id')),'array','min:1'],
            'producto_ids.*'=>'integer|distinct|exists:productos,id',
            'valor'=>['required','numeric','gt:0',$request->tipo==='Porcentaje' ? 'max:100' : 'max:999999.99','decimal:0,2'],
            'topeDescuento'=>'nullable|numeric|min:0|max:999999.99|decimal:0,2',
            'fechaInicio'=>'required|date_format:Y-m-d','fechaFin'=>'required|date_format:Y-m-d|after_or_equal:fechaInicio',
            'horaInicio'=>'nullable|required_with:horaFin|date_format:H:i','horaFin'=>'nullable|required_with:horaInicio|date_format:H:i|after_or_equal:horaInicio',
            'activo'=>'required|boolean',
        ],['valor.max'=>'El porcentaje no puede superar 100% (o el monto excede el límite permitido).','fechaFin.after_or_equal'=>'La fecha final no puede ser anterior a la inicial.','horaFin.after_or_equal'=>'La hora final no puede ser anterior a la inicial.']);
        $datos['producto_ids']=$datos['aplicaSobre']==='Pedido' ? null : array_map('intval',$datos['producto_ids'] ?? [$datos['producto_id']]);
        $datos['producto_id']=$datos['producto_ids'][0] ?? null;
        return $datos;
    }
    private function respuesta(Promocion $promocion,string $mensaje)
    {
        $respuesta=redirect()->route('admin.indexpromocion')->with('success',$mensaje.' Estado: '.$promocion->estadoAplicacion().'.');
        if($promocion->producto_id && (!$promocion->producto?->stock || $promocion->producto->stock->stock_actual <= 0)) $respuesta->with('error','Advertencia: el producto de esta promoción no tiene stock.');
        return $respuesta;
    }
    public function store(Request $request) { return $this->respuesta(Promocion::create($this->validar($request)),'Promoción registrada.'); }
    public function update(Request $request,int $id) { $promocion=Promocion::findOrFail($id);$promocion->update($this->validar($request,$promocion));return $this->respuesta($promocion,'Promoción actualizada.'); }
    public function destroy(int $id) { Promocion::findOrFail($id)->update(['activo'=>false]);return redirect()->route('admin.indexpromocion')->with('success','Promoción desactivada.'); }
    public function activar(int $id)
    {
        $promocion=Promocion::findOrFail($id);
        if(!in_array($promocion->tipo,['Porcentaje','Monto fijo']) || !in_array($promocion->aplicaSobre,['Producto','Pedido']) || ($promocion->aplicaSobre==='Producto' && !$promocion->productosAplicables())) return redirect()->route('admin.editpromocion',$id)->with('error','Completa la configuración de esta promoción antes de activarla.');
        $promocion->update(['activo'=>true]);return $this->respuesta($promocion,'Promoción activada.');
    }
}
