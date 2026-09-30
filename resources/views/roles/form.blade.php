@extends('layouts.app')

@section('titulo', 'Editar rol')

@section('contenido')
    @php
        $esMiRol = (int) auth()->user()->rol_id === (int) $rol->rol_id;

        // Si falló la validación, conservamos exactamente las casillas enviadas,
        // incluso cuando no se seleccionó ninguna.
        $seleccionados = session()->hasOldInput()
            ? old('permisos', [])
            : $rol->permisos->pluck('id')->all();

        $seleccionados = array_map('strval', (array) $seleccionados);
    @endphp

    <h1>Permisos de {{ $rol->nombre }}</h1>

    <p>
        Marca las funciones que podrán utilizar los usuarios de este rol.
    </p>

    @if ($errors->any())
        <div class="aviso error" role="alert">
            <ul>
                @foreach ($errors->all() as $mensaje)
                    <li>{{ $mensaje }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="tarjeta">
        <form
            method="POST"
            action="{{ route('admin.updaterol', $rol->rol_id) }}"
        >
            @csrf

            <div class="campo formulario">
                <label for="estado">Estado del rol</label>

                @if ($esMiRol)
                    <input type="hidden" name="estado" value="1">

                    <input
                        type="text"
                        id="estado"
                        value="Activo"
                        readonly
                    >

                    <small>No puedes desactivar tu propio rol.</small>
                @else
                    <select id="estado" name="estado" required>
                        <option
                            value="1"
                            @selected((int) old('estado', $rol->estado) === 1)
                        >
                            Activo
                        </option>

                        <option
                            value="0"
                            @selected((int) old('estado', $rol->estado) === 0)
                        >
                            Inactivo
                        </option>
                    </select>
                @endif
            </div>

            <fieldset class="grupo-permisos">
                <legend>Permisos disponibles</legend>

                <div class="lista-permisos">
                    @foreach ($permisos as $permiso)
                        @php
                            $protegido = $esMiRol && in_array(
                                $permiso->nombre,
                                ['gestion_roles', 'gestion_usuarios'],
                                true
                            );
                        @endphp

                        <label class="opcion-permiso">
                            @if ($protegido)
                                <input
                                    type="hidden"
                                    name="permisos[]"
                                    value="{{ $permiso->id }}"
                                >

                                <input
                                    type="checkbox"
                                    checked
                                    disabled
                                >
                            @else
                                <input
                                    type="checkbox"
                                    name="permisos[]"
                                    value="{{ $permiso->id }}"
                                    @checked(in_array(
                                        (string) $permiso->id,
                                        $seleccionados,
                                        true
                                    ))
                                >
                            @endif

                            <span>
                                {{ $permiso->descripcion ?: $permiso->nombre }}

                                @if ($protegido)
                                    <small>
                                        Necesario para administrar el sistema.
                                    </small>
                                @endif
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <div class="acciones">
                <button type="submit" class="boton">
                    Guardar cambios
                </button>

                <a
                    href="{{ route('admin.indexrol') }}"
                    class="boton secundario"
                >
                    Cancelar
                </a>
            </div>
        </form>
    </div>
@endsection