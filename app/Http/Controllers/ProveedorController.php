<?php

namespace App\Http\Controllers;

use App\Models\Proveedor;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProveedorController extends Controller
{
    public function index()
    {
        $proveedores = Proveedor::orderBy('nombre')->get();

        return view('proveedores.index', compact('proveedores'));
    }

    public function create()
    {
        $proveedor = new Proveedor();
        $proveedor->estado = 1;

        return view('proveedores.form', compact('proveedor'));
    }

    public function store(Request $request)
    {
        $datos = $this->validarDatos($request);

        Proveedor::create($datos);

        return redirect()
            ->route('admin.indexproveedor')
            ->with('success', 'Proveedor registrado correctamente.');
    }

    public function show(int $id)
    {
        $proveedor = Proveedor::findOrFail($id);

        return redirect()->route('admin.editproveedor', $proveedor->id);
    }

    public function edit(int $id)
    {
        $proveedor = Proveedor::findOrFail($id);

        return view('proveedores.form', compact('proveedor'));
    }

    public function update(Request $request, int $id)
    {
        $proveedor = Proveedor::findOrFail($id);

        $datos = $this->validarDatos($request, $proveedor);

        $proveedor->update($datos);

        return redirect()
            ->route('admin.indexproveedor')
            ->with('success', 'Proveedor actualizado correctamente.');
    }

    public function destroy(int $id)
    {
        $proveedor = Proveedor::findOrFail($id);

        $proveedor->update(['estado' => 0]);

        return redirect()
            ->route('admin.indexproveedor')
            ->with('success', 'Proveedor desactivado correctamente.');
    }

    private function validarDatos(
        Request $request,
        ?Proveedor $proveedor = null
    ): array {
        $nombreUnico = Rule::unique('proveedores', 'nombre');

        if ($proveedor !== null) {
            $nombreUnico->ignore($proveedor);
        }

        return $request->validate([
            'nombre' => [
                'required',
                'string',
                'max:255',
                $nombreUnico,
            ],
            'telefono' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 ()-]{6,30}$/'],
            'estado' => ['required', 'boolean'],
        ], [
            'nombre.required' => 'Escribe el nombre del proveedor.',
            'nombre.unique' => 'Ya existe un proveedor con ese nombre.',
            'nombre.max' => 'El nombre admite hasta 255 caracteres.',
            'telefono.max' => 'El teléfono admite hasta 30 caracteres.',
            'telefono.regex' => 'Ingresa un teléfono válido con números, espacios, paréntesis o guiones.',
            'estado.required' => 'Selecciona el estado.',
            'estado.boolean' => 'El estado seleccionado no es válido.',
        ]);
    }
}
