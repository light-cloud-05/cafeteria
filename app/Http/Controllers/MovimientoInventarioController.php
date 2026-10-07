<?php
namespace App\Http\Controllers;
use App\Models\{Producto, Proveedor, MovimientoInventario};
use App\Services\InventarioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MovimientoInventarioController extends Controller
{
    public function index()
    {
        $movimientos = MovimientoInventario::with(['producto','usuario','proveedor'])->latest('fecha_hora')->paginate(30);
        return view('inventario.movimientos', compact('movimientos'));
    }
    public function create(string $tipo)
    {
        $this->autorizar($tipo);
        $productos = Producto::with('stock')->orderBy('nombre')->get();
        $proveedores = Proveedor::where('estado', 1)->orderBy('nombre')->get();
        return view('inventario.form', compact('tipo','productos','proveedores'));
    }
    private function autorizar(string $tipo): void
    {
        $permiso = ['entrada'=>'registrar_entradas','salida'=>'registrar_salidas','ajuste'=>'registrar_ajustes'][$tipo] ?? null;
        abort_unless($permiso, 404);
        abort_unless(auth()->user()->tienePermiso($permiso), 403);
    }
    public function store(Request $request, string $tipo, InventarioService $inventario)
    {
        $this->autorizar($tipo);
        $datos = $request->validate([
            'producto_id'=>'required|integer|exists:productos,id',
            'cantidad'=>['required','numeric',$tipo === 'ajuste' ? 'min:0' : 'gt:0','max:99999999.99','decimal:0,2'],
            'motivo'=>'required|string|max:255', 'fecha_hora'=>'required|date|before_or_equal:now',
            'proveedor_id'=>'nullable|integer|exists:proveedores,id',
            'volver_stock'=>'sometimes|boolean',
        ]);
        $mensaje = DB::transaction(function () use ($datos,$tipo,$inventario,$request) {
            $producto = Producto::lockForUpdate()->findOrFail($datos['producto_id']);
            $stock = $inventario->stock($producto);
            if ($tipo === 'salida' && !$producto->disponible) {
                throw ValidationException::withMessages(['producto_id'=>'El producto está desactivado.']);
            }
            $proveedor = $tipo === 'entrada' ? ($datos['proveedor_id'] ?? null) : null;
            if ($proveedor && !Proveedor::whereKey($proveedor)->where('estado',1)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['proveedor_id'=>'Selecciona un proveedor activo.']);
            }
            $actual = (float) $stock->stock_actual;
            $cantidad = (float) $datos['cantidad'];
            $nuevo = round(match ($tipo) { 'entrada'=>$actual+$cantidad, 'salida'=>$actual-$cantidad, 'ajuste'=>$cantidad }, 2);
            if ($nuevo < 0 || $nuevo > 99999999.99) {
                throw ValidationException::withMessages(['cantidad'=>'Stock insuficiente o fuera del límite permitido.']);
            }
            if ($tipo === 'ajuste' && $nuevo === $actual) return 'No hay ajuste necesario.';
            MovimientoInventario::create([
                'tipo'=>$tipo, 'cantidad'=>$tipo === 'ajuste' ? $nuevo-$actual : $cantidad,
                'fecha_hora'=>$datos['fecha_hora'], 'motivo'=>$datos['motivo'],
                'producto_id'=>$producto->id, 'proveedor_id'=>$proveedor, 'usuario_id'=>$request->user()->id,
            ]);
            $inventario->actualizar($producto,$stock,$nuevo,$request->user()->id);
            return 'Movimiento registrado y stock actualizado correctamente.';
        });
        $ruta = $tipo === 'ajuste' && $request->boolean('volver_stock')
            && $request->user()->tienePermiso('consultar_stock')
            ? 'admin.indexstock' : 'admin.movimiento.crear.'.$tipo;
        return redirect()->route($ruta)->with('success',$mensaje)->with('borrador_guardado',true);
    }
}
