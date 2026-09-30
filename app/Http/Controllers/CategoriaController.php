<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoriaController extends Controller
{
    public function index()
    {
        $categorias = Categoria::orderBy('nombre')->get();

        return view('categorias.index', compact('categorias'));
    }

    public function create()
    {
        $categoria = new Categoria();
        $categoria->estado = true;

        return view('categorias.form', compact('categoria'));
    }

    public function store(Request $request)
    {
        $datos = $this->validarDatos($request);

        Categoria::create($datos);

        return redirect()
            ->route('admin.indexcategoria')
            ->with('success', 'Categoría registrada correctamente.');
    }

    public function show($id)
    {
        return redirect()->route('admin.editarcategoria', $id);
    }

    public function edit($id)
    {
        $categoria = Categoria::findOrFail($id);

        return view('categorias.form', compact('categoria'));
    }

    public function update(Request $request, $id)
    {
        $categoria = Categoria::findOrFail($id);

        $datos = $this->validarDatos($request, $categoria);

        $categoria->update($datos);

        return redirect()
            ->route('admin.indexcategoria')
            ->with('success', 'Categoría actualizada correctamente.');
    }

    public function destroy($id)
    {
        $categoria = Categoria::findOrFail($id);

        $categoria->update(['estado' => false]);

        return redirect()
            ->route('admin.indexcategoria')
            ->with('success', 'Categoría desactivada correctamente.');
    }

    private function validarDatos(
        Request $request,
        ?Categoria $categoria = null
    ): array {
        $nombreUnico = Rule::unique('categorias', 'nombre');

        if ($categoria !== null) {
            $nombreUnico->ignore($categoria);
        }

        return $request->validate([
            'nombre' => [
                'required',
                'string',
                'max:255',
                $nombreUnico,
            ],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'estado' => ['required', 'boolean'],
        ], [
            'nombre.required' => 'Escribe el nombre de la categoría.',
            'nombre.unique' => 'Ya existe una categoría con ese nombre.',
            'nombre.max' => 'El nombre admite hasta 255 caracteres.',
            'descripcion.max' => 'La descripción admite hasta 255 caracteres.',
            'estado.required' => 'Selecciona el estado.',
            'estado.boolean' => 'El estado seleccionado no es válido.',
        ]);
    }
}