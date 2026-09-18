<?php

namespace App\Http\Controllers;

use App\Models\Rol;
use Illuminate\Http\Request;

class ROLESController extends Controller
{
    ///Lista los elementos de una tabla MOSTRAR
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    ///guardar datos en la tabla CREAR
    public function store(Request $request)
    {
        //
    }

    //muestra solo un elemento de la tabla
    /**
     * Display the specified resource.
     */
    public function show(Rol $rOLES)
    {
        //
    }

    //Modificar un elemento MODIFICAR
    public function update(Request $request, Rol $rOLES)
    {
        //
    }

    //permite eleiminar un elemento de la tabla ELIMINAR  CRUD
    public function destroy(Rol $rOLES)
    {
        //
    }
}
