<?php
namespace App\Http\Controllers;
use App\Models\Bitacora;
use Illuminate\Http\Request;
class BitacorasController extends Controller
{
    public function __construct() { $this->middleware(['auth','permiso:consultar_bitacora']); }
    public function index(Request $request)
    {
        $filtros=$request->validate([
            'usuario'=>'nullable|string|max:100','accion'=>'nullable|string|max:50',
            'desde'=>'nullable|date_format:Y-m-d','hasta'=>'nullable|date_format:Y-m-d',
        ]);
        if(!empty($filtros['desde']) && !empty($filtros['hasta']) && $filtros['hasta'] < $filtros['desde']) {
            throw \Illuminate\Validation\ValidationException::withMessages(['hasta'=>'La fecha final no puede ser anterior a la inicial.']);
        }
        $consulta=Bitacora::with('usuario');
        if(!empty($filtros['usuario'])) $consulta->whereHas('usuario',fn($q)=>$q->where('nombreUsuario',trim($filtros['usuario'])));
        if(!empty($filtros['accion'])) $consulta->where('accion',$filtros['accion']);
        if(!empty($filtros['desde'])) $consulta->entreFechas('fechaHora',$filtros['desde'],null);
        if(!empty($filtros['hasta'])) $consulta->entreFechas('fechaHora',null,$filtros['hasta']);
        $bitacoras=$consulta->orderByDesc('fechaHora')->orderByDesc('id')->paginate(20)->withQueryString();
        $acciones=Bitacora::select('accion')->distinct()->orderBy('accion')->pluck('accion');
        $hayFiltros=collect($filtros)->contains(fn($v)=>$v !== null && $v !== '');
        return view('bitacoras.index',compact('bitacoras','acciones','hayFiltros'));
    }
    public function show(int $id)
    {
        $bitacora=Bitacora::with('usuario')->findOrFail($id);
        $formatear=function(?string $valor): string {
            if($valor === null || $valor === '') return 'Sin datos registrados';
            $datos=json_decode($valor,true);
            return json_last_error() === JSON_ERROR_NONE ? json_encode($datos,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) : $valor;
        };
        $anteriores=$formatear($bitacora->valoresAnteriores);$nuevos=$formatear($bitacora->valoresNuevos);
        return view('bitacoras.show',compact('bitacora','anteriores','nuevos'));
    }
}
