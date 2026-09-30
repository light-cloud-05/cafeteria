@extends('layouts.app')

@section('titulo', 'Inicio')

@section('contenido')
    <h1>Inicio</h1>

    <div class="tarjeta">
        <h2>Bienvenido/a, {{ auth()->user()->nombreCompleto }}</h2>

        <p>
            Desde este panel podrás acceder a las funciones
            correspondientes a tu rol.
        </p>

        @if (auth()->user()->tienePermiso('gestion_categorias'))
            <p>Organiza las categorías del catálogo de la cafetería.</p>

            <a href="{{ route('admin.indexcategoria') }}" class="boton">
                Gestionar categorías
            </a>
        @else
            <p>Las pantallas de tu área se incorporarán próximamente.</p>
        @endif
    </div>
@endsection
