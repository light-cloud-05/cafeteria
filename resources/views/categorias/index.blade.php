@extends('layouts.app')

@section('titulo', 'Categorías')

@section('contenido')
    <div class="barra">
        <h1>Categorías</h1>

        <a href="{{ route('admin.crearcategoria') }}" class="boton">
            Nueva categoría
        </a>
    </div>

    <div class="tarjeta">
        <div class="tabla-contenedor">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Nombre</th>
                        <th scope="col">Descripción</th>
                        <th scope="col">Estado</th>
                        <th scope="col">Acciones</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($categorias as $categoria)
                        <tr>
                            <td>{{ $categoria->nombre }}</td>

                            <td>
                                {{ $categoria->descripcion ?: 'Sin descripción' }}
                            </td>

                            <td>
                                <span class="etiqueta {{ $categoria->estado ? 'activa' : 'inactiva' }}">
                                    {{ $categoria->estado ? 'Activa' : 'Inactiva' }}
                                </span>
                            </td>

                            <td>
                                <div class="acciones">
                                    <a
                                        href="{{ route('admin.editarcategoria', $categoria->id) }}"
                                        class="boton secundario"
                                    >
                                        Editar
                                    </a>

                                    @if ($categoria->estado)
                                        <form
                                            method="POST"
                                            action="{{ route('admin.destroycategoria', $categoria->id) }}"
                                            onsubmit="return confirm('¿Deseas desactivar esta categoría?');"
                                        >
                                            @csrf
                                            @method('DELETE')

                                            <button type="submit" class="boton secundario">
                                                Desactivar
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                Todavía no hay categorías registradas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection