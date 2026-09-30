<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Recuperar contraseña | KARMA</title>

    <link rel="stylesheet" href="{{ asset('css/login.css') }}">
</head>
<body>
    <main class="login">
        <header class="marca">
            <p class="marca-nombre">KARMA</p>
            <p class="marca-descripcion">Gestión de cafetería</p>
        </header>

        <section class="tarjeta-login">
            <h1>Recuperar contraseña</h1>

            <p class="introduccion">
                Ingresa el correo asociado a tu cuenta.
            </p>

            @if (session('success'))
                <div class="aviso-exito" role="status">
                    {{ session('success') }}
                </div>
            @endif

            <form method="POST" action="{{ route('password.email') }}">
                @csrf

                <div class="campo">
                    <label for="email">Correo electrónico</label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email') }}"
                        maxlength="255"
                        autocomplete="email"
                        required
                    >

                    @error('email')
                        <p class="error-campo" role="alert">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <button type="submit" class="boton-ingresar">
                    Solicitar recuperación
                </button>
            </form>

            <p class="ayuda">
                <a href="{{ route('login') }}">Volver al inicio de sesión</a>
            </p>
        </section>
    </main>
</body>
</html>