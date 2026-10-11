@extends('layouts.app')

@section('titulo', 'Usuarios')

@section('contenido')
    <div class="barra">
        <h1>Usuarios</h1>

        <a href="{{ route('admin.crearusuario') }}" class="boton">
            Nuevo usuario
        </a>
    </div>

    <div class="tarjeta">
        <div class="tabla-contenedor">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Nombre completo</th>
                        <th scope="col">Usuario</th>
                        <th scope="col">Rol</th>
                        <th scope="col">Teléfono</th>
                        <th scope="col">Estado</th>
                        <th scope="col">Acciones</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($usuarios as $usuario)
                        <tr>
                            <td>
                                {{ $usuario->nombreCompleto }}

                                @if ((int) $usuario->id === (int) auth()->id())
                                    <br>
                                    <small>Tu cuenta</small>
                                @endif
                            </td>

                            <td>{{ $usuario->nombreUsuario }}</td>

                            <td>
                                {{ $usuario->rol?->nombre ?? 'Sin rol' }}

                                @if ($usuario->rol && ! $usuario->rol->estado)
                                    <br>
                                    <small>Rol inactivo</small>
                                @endif
                            </td>

                            <td>
                                {{ $usuario->telefono ?? 'Sin teléfono' }}
                            </td>

                            <td>
                                <span class="etiqueta {{ $usuario->estado ? 'activa' : 'inactiva' }}">
                                    {{ $usuario->estado ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>

                            <td>
                                <div class="acciones">
                                    <a
                                        href="{{ route('admin.editusuario', $usuario->id) }}"
                                        class="boton secundario"
                                    >
                                        Editar
                                    </a>

                                    @if (
                                        (int) $usuario->id !== (int) auth()->id()
                                    )
                                        <form
                                            method="POST"
                                            action="{{ route('admin.destroyusuario', $usuario->id) }}"
                                            onsubmit="return confirm('¿Deseas eliminar esta cuenta? Se conservará su historial.');"
                                        >
                                            @csrf
                                            @method('DELETE')

                                            <button type="submit" class="boton secundario">
                                                Eliminar
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                No hay usuarios registrados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection