<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Recuperar contraseña | KARMA</title>

    <link rel="stylesheet" href="{{ asset('css/login.css') }}">
    <link rel="stylesheet" href="{{ asset('css/auth-modern.css') }}">
</head>
<body>
    <main class="login">
        <header class="marca marca-auth">
            <img class="logo-auth" src="{{ asset('images/karma.png') }}" alt="Cafetería Karma">
            <p class="marca-descripcion">Un café. Un momento. Todo en orden.</p>
        </header>

        <section class="tarjeta-login">
            <h1>Recuperar contraseña</h1>

            <p class="introduccion">
                Ingresa tu nombre de usuario. El administrador recibirá la solicitud y verificará tu identidad antes de facilitarte un enlace temporal para elegir una nueva contraseña.
            </p>

            @if (session('success'))
                <div class="aviso-exito" role="status">
                    @include('partials.icono', ['nombre' => 'check'])<span>{{ session('success') }}</span>
                </div>
            @endif

            <form method="POST" action="{{ route('password.email') }}">
                @csrf

                <div class="campo">
                    <label for="nombreUsuario">Usuario que necesita recuperar el acceso</label>

                    <div class="input-icono">@include('partials.icono', ['nombre' => 'usuarios'])<input
                        type="text"
                        id="nombreUsuario"
                        name="nombreUsuario"
                        value="{{ old('nombreUsuario', $nombreUsuario ?? '') }}"
                        maxlength="100"
                        autocomplete="username"
                        autocapitalize="none"
                        spellcheck="false"
                        required
                    ></div>

                    @error('nombreUsuario')
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
