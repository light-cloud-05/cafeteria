<?php
namespace App\Http\Controllers;
use App\Models\Producto;
use Illuminate\Http\Request;
class CatalogoController extends Controller
{
    public function index(Request $request)
    {
        $datos = $request->validate(['q'=>'nullable|string|max:255','producto'=>'nullable|integer|exists:productos,id']);
        $q = trim($datos['q'] ?? '');
        $productos = Producto::with(['categoria','stock'])->when($q !== '', function($consulta) use($q) {
            $prefijo = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
            $consulta->where(fn($f)=>$f->where('nombre','like',$prefijo.'%')->orWhere('id',$q));
        })->when($request->filled('producto'),fn($c)=>$c->whereKey($datos['producto']))->orderBy('nombre')->get();
        return view('catalogo.index',compact('productos','q'));
    }
}
