<?php

namespace App\Http\Controllers;

use App\Models\Rol;
use Illuminate\Http\Request;

class RolController extends Controller
{
    ///Lista los elementos de una tabla MOSTRAR
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return "hols";
    }

    ///guardar datos en la tabla CREAR
    public function store(Request $request)
    {
        return "store";
    }

    //muestra solo un elemento de la tabla
    public function show(Rol $roles)
    {
        return "show";
    }

    //Modificar un elemento MODIFICAR
    public function update(Request $request, Rol $roles)
    {
        return "update";
    }

    //permite eleiminar un elemento de la tabla ELIMINAR  CRUD
    public function destroy(Rol $roles)
    {
        return "delete";
    }
}
