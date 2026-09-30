<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class CategoriaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Obtenemos todas las categorías de la base de datos
        $categorias = Categoria::all();

        // Retornamos la vista de Blade ubicada en resources/views/categorias/index.blade.php
        return view('categorias.index', compact('categorias'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('categorias.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255|unique:categorias,nombre',
            'descripcion' => 'nullable|string|max:500',
            'estado' => 'required|boolean'
        ]);

        Categoria::create($request->all());

        return redirect()->route('admin.indexcategoria')
            ->with('success', 'Categoría creada con éxito');
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $categoria = Categoria::findOrFail($id);

        return view('categorias.show', compact('categoria'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $categoria = Categoria::findOrFail($id);

        return view('categorias.edit', compact('categoria'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $categoria = Categoria::findOrFail($id);

        $request->validate([
            'nombre' => 'sometimes|required|string|max:255|unique:categorias,nombre,' . $id . ',id',
            'descripcion' => 'nullable|string|max:500',
            'estado' => 'sometimes|required|boolean'
        ]);

        $categoria->update($request->all());

        return redirect()->route('admin.indexcategoria')
            ->with('success', 'Categoría actualizada con éxito');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $categoria = Categoria::findOrFail($id);
        $categoria->delete();

        return redirect()->route('admin.indexcategoria')
            ->with('success', 'Categoría eliminada con éxito');
    }
}