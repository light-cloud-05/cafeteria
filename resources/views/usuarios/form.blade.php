@extends('layouts.app')

@section('titulo', $usuario->exists ? 'Editar usuario' : 'Nuevo usuario')

@section('contenido')
    @php
        $esMiCuenta = $usuario->exists
            && (int) $usuario->id === (int) auth()->id();
    @endphp

    <h1>
        {{ $usuario->exists ? 'Editar usuario' : 'Nuevo usuario' }}
    </h1>

    @if ($roles->isEmpty())
        <div class="aviso error" role="alert">
            No hay roles activos disponibles para asignar.
        </div>
    @endif

    <div class="tarjeta formulario">
        <form
            method="POST"
            action="{{ $usuario->exists
                ? route('admin.updateusuario', $usuario->id)
                : route('admin.storedusuario') }}"
        >
            @csrf

            <div class="campo">
                <label for="nombreCompleto">Nombre completo</label>

                <input
                    type="text"
                    id="nombreCompleto"
                    name="nombreCompleto"
                    value="{{ old('nombreCompleto', $usuario->nombreCompleto) }}"
                    maxlength="255"
                    required
                >

                @error('nombreCompleto')
                    <p class="error-campo" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="campo">
                <label for="nombreUsuario">Nombre de usuario</label>

                <input
                    type="text"
                    id="nombreUsuario"
                    name="nombreUsuario"
                    value="{{ old('nombreUsuario', $usuario->nombreUsuario) }}"
                    maxlength="100"
                    pattern="[a-zA-Z0-9._-]+"
                    autocomplete="off"
                    spellcheck="false"
                    required
                >

                <small>
                    Sin espacios ni acentos. Ejemplo: maria.perez
                </small>

                @error('nombreUsuario')
                    <p class="error-campo" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="campo">
            <label for="email">Correo electrónico de recuperación</label>

            <input
                type="email"
                id="email"
                name="email"
                value="{{ old('email', $usuario->email) }}"
                maxlength="255"
                required
            >

            @error('email')
                <p class="error-campo" role="alert">{{ $message }}</p>
            @enderror
            </div>

            <div class="campo">
                <label for="telefono">Teléfono (opcional)</label>

                <input
                    type="tel"
                    id="telefono"
                    name="telefono"
                    value="{{ old('telefono', $usuario->telefono) }}"
                    maxlength="30"
                >

                @error('telefono')
                    <p class="error-campo" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="campo">
                <label for="rol_id">Rol</label>

                @if ($esMiCuenta)
                    <input
                        type="hidden"
                        name="rol_id"
                        value="{{ $usuario->rol_id }}"
                    >

                    <input
                        type="text"
                        id="rol_id"
                        value="{{ $usuario->rol?->nombre }}"
                        readonly
                    >

                    <small>No puedes cambiar tu propio rol.</small>
                @else
                    <select id="rol_id" name="rol_id" required>
                        <option value="">Selecciona un rol activo</option>

                        @foreach ($roles as $rol)
                            <option
                                value="{{ $rol->rol_id }}"
                                @selected(
                                    (string) old('rol_id', $usuario->rol_id)
                                    === (string) $rol->rol_id
                                )
                            >
                                {{ $rol->nombre }}
                            </option>
                        @endforeach
                    </select>
                @endif

                @error('rol_id')
                    <p class="error-campo" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="campo">
                <label for="estado">Estado de la cuenta</label>

                @if ($esMiCuenta)
                    <input type="hidden" name="estado" value="1">

                    <input
                        type="text"
                        id="estado"
                        value="Activo"
                        readonly
                    >

                    <small>No puedes desactivar tu propia cuenta.</small>
                @else
                    <select id="estado" name="estado" required>
                        <option
                            value="1"
                            @selected((int) old('estado', $usuario->estado) === 1)
                        >
                            Activo
                        </option>

                        <option
                            value="0"
                            @selected((int) old('estado', $usuario->estado) === 0)
                        >
                            Inactivo
                        </option>
                    </select>
                @endif

                @error('estado')
                    <p class="error-campo" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="campo">
                <label for="password">
                    {{ $usuario->exists ? 'Nueva contraseña (opcional)' : 'Contraseña' }}
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    minlength="8"
                    maxlength="64"
                    autocomplete="new-password"
                    @required(! $usuario->exists)
                >

                <small>
                    @if ($usuario->exists)
                        Deja ambos campos de contraseña vacíos para conservar la actual.
                    @else
                        Usa al menos 8 caracteres.
                    @endif
                </small>

                @error('password')
                    <p class="error-campo" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="campo">
                <label for="password_confirmation">Confirmar contraseña</label>

                <input
                    type="password"
                    id="password_confirmation"
                    name="password_confirmation"
                    minlength="8"
                    maxlength="64"
                    autocomplete="new-password"
                    @required(! $usuario->exists)
                >
            </div>

            <div class="acciones">
                <button
                    type="submit"
                    class="boton"
                    @disabled($roles->isEmpty())
                >
                    {{ $usuario->exists ? 'Guardar cambios' : 'Registrar usuario' }}
                </button>

                <a
                    href="{{ route('admin.indexusuario') }}"
                    class="boton secundario"
                >
                    Cancelar
                </a>
            </div>
        </form>
    </div>
@endsection