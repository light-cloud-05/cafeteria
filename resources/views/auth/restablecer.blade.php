<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Nueva contraseña | KARMA</title>

    <link rel="stylesheet" href="{{ asset('css/login.css') }}">
</head>
<body>
    <main class="login">
        <header class="marca">
            <p class="marca-nombre">KARMA</p>
            <p class="marca-descripcion">Gestión de cafetería</p>
        </header>

        <section class="tarjeta-login">
            <h1>Nueva contraseña</h1>

            <p class="introduccion">
                Escribe y confirma tu nueva contraseña.
            </p>

            @if ($errors->any())
                <div class="error-campo" role="alert">
                    <ul>
                        @foreach ($errors->all() as $mensaje)
                            <li>{{ $mensaje }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('password.update') }}">
                @csrf

                <input type="hidden" name="token" value="{{ $token }}">

                <div class="campo">
                    <label for="email">Correo electrónico</label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ $email }}"
                        readonly
                        required
                    >
                </div>

                <div class="campo">
                    <label for="password">Nueva contraseña</label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        minlength="8"
                        maxlength="64"
                        autocomplete="new-password"
                        required
                    >
                </div>

                <div class="campo">
                    <label for="password_confirmation">
                        Confirmar contraseña
                    </label>

                    <input
                        type="password"
                        id="password_confirmation"
                        name="password_confirmation"
                        minlength="8"
                        maxlength="64"
                        autocomplete="new-password"
                        required
                    >
                </div>

                <button type="submit" class="boton-ingresar">
                    Guardar nueva contraseña
                </button>
            </form>

            <p class="ayuda">
                <a href="{{ route('password.request') }}">
                    Solicitar otro enlace
                </a>
            </p>
        </section>
    </main>
</body>
</html> 