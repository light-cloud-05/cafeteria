@extends('layouts.app')

@section('titulo', 'Proveedores')

@section('contenido')
    <div class="barra">
        <h1>Proveedores</h1>

        <a href="{{ route('admin.crearproveedor') }}" class="boton">
            Nuevo proveedor
        </a>
    </div>

    <div class="tarjeta">
        <div class="tabla-contenedor">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Nombre</th>
                        <th scope="col">Teléfono</th>
                        <th scope="col">Estado</th>
                        <th scope="col">Acciones</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($proveedores as $proveedor)
                        <tr>
                            <td>{{ $proveedor->nombre }}</td>

                            <td>
                                {{ $proveedor->telefono ?? 'Sin teléfono' }}
                            </td>

                            <td>
                                <span class="etiqueta {{ $proveedor->estado ? 'activa' : 'inactiva' }}">
                                    {{ $proveedor->estado ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>

                            <td>
                                <div class="acciones">
                                    <a
                                        href="{{ route('admin.editproveedor', $proveedor->id) }}"
                                        class="boton secundario"
                                    >
                                        Editar
                                    </a>

                                    @if ($proveedor->estado)
                                        <form
                                            method="POST"
                                            action="{{ route('admin.destroyproveedor', $proveedor->id) }}"
                                            onsubmit="return confirm('¿Deseas desactivar este proveedor?');"
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
                                Todavía no hay proveedores registrados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection