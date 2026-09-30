@extends('layouts.app')

@section('titulo', 'Roles y permisos')

@section('contenido')
    <h1>Roles y permisos</h1>

    <p>
        Configura las funciones disponibles para cada rol.
        Los cambios afectan a todos sus usuarios.
    </p>

    <div class="tarjeta">
        <div class="tabla-contenedor">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Rol</th>
                        <th scope="col">Usuarios asignados</th>
                        <th scope="col">Permisos</th>
                        <th scope="col">Estado</th>
                        <th scope="col">Acciones</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($roles as $rol)
                        <tr>
                            <td>{{ $rol->nombre }}</td>

                            <td>{{ $rol->usuarios_count }}</td>

                            <td>{{ $rol->permisos_count }}</td>

                            <td>
                                <span class="etiqueta {{ $rol->estado ? 'activa' : 'inactiva' }}">
                                    {{ $rol->estado ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>

                            <td>
                                <a
                                    href="{{ route('admin.editrol', $rol->rol_id) }}"
                                    class="boton secundario"
                                >
                                    Editar permisos
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">No hay roles registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection