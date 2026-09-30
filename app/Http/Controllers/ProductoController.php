<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Proveedor;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductoController extends Controller
{
    public function index()
    {
        $productos = Producto::with(['categoria', 'proveedor'])
            ->orderBy('nombre')
            ->get();

        return view('productos.index', compact('productos'));
    }

    public function create()
    {
        $producto = new Producto();
        $producto->disponible = true;

        return $this->formulario($producto);
    }

    public function store(Request $request)
    {
        $datos = $this->validarDatos($request);

        Producto::create($datos);

        return redirect()
            ->route('admin.indexproducto')
            ->with('success', 'Producto registrado correctamente.');
    }

    public function show(int $id)
    {
        $producto = Producto::findOrFail($id);

        return redirect()->route('admin.editproducto', $producto->id);
    }

    public function edit(int $id)
    {
        $producto = Producto::findOrFail($id);

        return $this->formulario($producto);
    }

    public function update(Request $request, int $id)
    {
        $producto = Producto::findOrFail($id);

        $datos = $this->validarDatos($request, $producto);

        $producto->update($datos);

        return redirect()
            ->route('admin.indexproducto')
            ->with('success', 'Producto actualizado correctamente.');
    }

    public function destroy(int $id)
    {
        $producto = Producto::findOrFail($id);

        $producto->update(['disponible' => false]);

        return redirect()
            ->route('admin.indexproducto')
            ->with('success', 'Producto marcado como no disponible.');
    }

    private function formulario(Producto $producto)
    {
        // Incluye las opciones activas y la relación actual,
        // aunque esta última haya sido desactivada.
        $categorias = Categoria::where(function ($consulta) use ($producto) {
            $consulta->where('estado', 1);

            if ($producto->exists) {
                $consulta->orWhere('id', $producto->categoria_id);
            }
        })->orderBy('nombre')->get();

        $proveedores = Proveedor::where(function ($consulta) use ($producto) {
            $consulta->where('estado', 1);

            if ($producto->proveedor_id !== null) {
                $consulta->orWhere('id', $producto->proveedor_id);
            }
        })->orderBy('nombre')->get();

        return view(
            'productos.form',
            compact('producto', 'categorias', 'proveedores')
        );
    }

    private function validarDatos(
        Request $request,
        ?Producto $producto = null
    ): array {
        $nombreUnico = Rule::unique('productos', 'nombre');

        if ($producto !== null) {
            $nombreUnico->ignore($producto);
        }

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:255', $nombreUnico],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'precio' => [
                'required',
                'numeric',
                'min:0',
                'max:999999.99',
                'regex:/^\d+(\.\d{1,2})?$/',
            ],
            'disponible' => ['required', 'boolean'],
            'categoria_id' => ['required', 'integer', 'exists:categorias,id'],
            'proveedor_id' => ['nullable', 'integer', 'exists:proveedores,id'],
        ], [
            'nombre.required' => 'Escribe el nombre del producto.',
            'nombre.unique' => 'Ya existe un producto con ese nombre.',
            'nombre.max' => 'El nombre admite hasta 255 caracteres.',
            'descripcion.max' => 'La descripción admite hasta 255 caracteres.',
            'precio.required' => 'Ingresa el precio.',
            'precio.numeric' => 'El precio debe ser numérico.',
            'precio.min' => 'El precio no puede ser negativo.',
            'precio.max' => 'El precio no puede superar 999999.99.',
            'precio.regex' => 'Usa un precio con hasta dos decimales.',
            'disponible.required' => 'Selecciona la disponibilidad.',
            'disponible.boolean' => 'La disponibilidad no es válida.',
            'categoria_id.required' => 'Selecciona una categoría.',
            'categoria_id.integer' => 'La categoría no es válida.',
            'categoria_id.exists' => 'La categoría seleccionada no existe.',
            'proveedor_id.integer' => 'El proveedor no es válido.',
            'proveedor_id.exists' => 'El proveedor seleccionado no existe.',
        ]);

        $categoria = Categoria::findOrFail($datos['categoria_id']);

        if (! $categoria->estado) {
            $conservaCategoria = $producto !== null
                && (int) $producto->categoria_id === (int) $categoria->id;

            if (! $conservaCategoria || (bool) $datos['disponible']) {
                throw ValidationException::withMessages([
                    'categoria_id' =>
                        'Selecciona una categoría activa. Solo puedes conservar una categoría inactiva si el producto queda no disponible.',
                ]);
            }
        }

        $datos['proveedor_id'] = $datos['proveedor_id'] ?? null;

        if ($datos['proveedor_id'] !== null) {
            $proveedor = Proveedor::findOrFail($datos['proveedor_id']);

            $conservaProveedor = $producto !== null
                && (int) $producto->proveedor_id === (int) $proveedor->id;

            if (! $proveedor->estado && ! $conservaProveedor) {
                throw ValidationException::withMessages([
                    'proveedor_id' => 'Selecciona un proveedor activo.',
                ]);
            }
        }

        return $datos;
    }
}