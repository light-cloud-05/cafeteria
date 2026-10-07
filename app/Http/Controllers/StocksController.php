<?php
namespace App\Http\Controllers;
use App\Models\{Producto, Categoria, Stock};
use Illuminate\Http\Request;
class StocksController extends Controller
{
    public function minimo(Request $request, int $id)
    {
        $datos = $request->validate(['stock_minimo'=>'required|numeric|min:0|max:99999999.99|decimal:0,2']);
        \Illuminate\Support\Facades\DB::transaction(function() use($id,$datos) {
            $producto = Producto::lockForUpdate()->findOrFail($id);
            $servicio = app(\App\Services\InventarioService::class);
            $stock = $servicio->stock($producto);
            $antes = $stock->stock_actual <= $stock->stock_minimo;
            $stock->update($datos);
            $despues = $stock->stock_actual <= $stock->stock_minimo;
            if ($antes !== $despues) \App\Models\Bitacora::create([
                'tablaAfectada'=>'stocks','idRegistroAfectado'=>$stock->id,
                'accion'=>$despues ? 'PRODUCTO_AGOTADO' : 'PRODUCTO_REPUESTO',
                'valoresAnteriores'=>null,'valoresNuevos'=>json_encode($datos),
                'fechaHora'=>now(),'idUsuario'=>auth()->id(),
            ]);
        });
        return back()->with('success','Stock mínimo actualizado.');
    }
    public function index(Request $request) { return $this->listado($request,'actual'); }
    public function minimos(Request $request) { return $this->listado($request,'minimos'); }
    public function agotados(Request $request) { return $this->listado($request,'agotados'); }
    private function listado(Request $request, string $modo)
    {
        $request->validate(['categoria_id'=>'nullable|integer|exists:categorias,id']);
        $consulta = Producto::with(['categoria','stock'])->orderBy('nombre');
        if ($request->filled('categoria_id')) $consulta->where('categoria_id',$request->categoria_id);
        if ($modo !== 'actual') $consulta->whereHas('stock',fn($q)=>$q->whereColumn('stock_actual','<=','stock_minimo'));
        $productos = $consulta->get();
        $categorias = Categoria::orderBy('nombre')->get();
        return view('inventario.stock',compact('productos','categorias','modo'));
    }
}
