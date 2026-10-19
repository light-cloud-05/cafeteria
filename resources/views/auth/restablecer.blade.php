<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Nueva contraseña | KARMA</title>

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

                    <div class="input-icono">@include('partials.icono', ['nombre' => 'mail'])<input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ $email }}"
                        readonly
                        required
                    ></div>
                </div>

                <div class="campo">
                    <label for="password">Nueva contraseña</label>

                    <div class="clave-con-control">
                    <div class="input-icono">@include('partials.icono', ['nombre' => 'lock'])<input
                        type="password"
                        id="password"
                        name="password"
                        minlength="8"
                        maxlength="64"
                        autocomplete="new-password"
                        required
                    ></div>
                        <button class="ver-clave" type="button" aria-controls="password" aria-pressed="false" aria-label="Mostrar nueva contraseña" data-descripcion="nueva contraseña">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg><span>Ver</span>
                        </button>
                    </div>
                </div>

                <div class="campo">
                    <label for="password_confirmation">
                        Confirmar contraseña
                    </label>

                    <div class="clave-con-control">
                    <div class="input-icono">@include('partials.icono', ['nombre' => 'lock'])<input
                        type="password"
                        id="password_confirmation"
                        name="password_confirmation"
                        minlength="8"
                        maxlength="64"
                        autocomplete="new-password"
                        required
                    ></div>
                        <button class="ver-clave" type="button" aria-controls="password_confirmation" aria-pressed="false" aria-label="Mostrar confirmación de contraseña" data-descripcion="confirmación de contraseña">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg><span>Ver</span>
                        </button>
                    </div>
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
<script>
    document.querySelectorAll('.ver-clave').forEach((boton) => {
        boton.addEventListener('click', () => {
            const campo = document.getElementById(boton.getAttribute('aria-controls'));
            const visible = campo.type === 'password';
            campo.type = visible ? 'text' : 'password';
            boton.querySelector('span').textContent = visible ? 'Ocultar' : 'Ver';
            boton.setAttribute('aria-pressed', String(visible));
            boton.setAttribute('aria-label', (visible ? 'Ocultar ' : 'Mostrar ') + boton.dataset.descripcion);
        });
    });
</script>
</body>
</html> 