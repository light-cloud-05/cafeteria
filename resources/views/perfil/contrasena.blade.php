@extends('layouts.app')

@section('titulo', 'Cambiar mi contraseña')

@section('contenido')
    <div class="tarjeta formulario">
        <h2>Cambiar mi contraseña</h2>

        <p>
            Ingresa tu contraseña actual y elige una nueva
            de entre 8 y 64 caracteres.
        </p>

        <form
            method="POST"
            action="{{ route('perfil.contrasena.update') }}"
        >
            @csrf
            @method('PUT')

            <div class="campo">
                <label for="current_password">
                    Contraseña actual
                </label>

                <input
                    type="password"
                    id="current_password"
                    name="current_password"
                    autocomplete="current-password"
                    required
                >

                @error('current_password')
                    <p class="error-campo" role="alert">
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div class="campo">
                <label for="password">
                    Nueva contraseña
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    autocomplete="new-password"
                    minlength="8"
                    maxlength="64"
                    required
                >

                @error('password')
                    <p class="error-campo" role="alert">
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div class="campo">
                <label for="password_confirmation">
                    Confirmar nueva contraseña
                </label>

                <input
                    type="password"
                    id="password_confirmation"
                    name="password_confirmation"
                    autocomplete="new-password"
                    minlength="8"
                    maxlength="64"
                    required
                >
            </div>

            <div class="acciones">
                <button type="submit" class="boton">
                    Guardar contraseña
                </button>

                <a
                    href="{{ route('home') }}"
                    class="boton secundario"
                >
                    Volver al inicio
                </a>
            </div>
        </form>
    </div>
@endsection