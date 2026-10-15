<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Iniciar sesión | KARMA Cafetería</title>

    <link rel="stylesheet" href="{{ asset('css/login.css') }}">
</head>
<body>
    <main class="login">
        <header class="marca">
            <div class="marca-icono" aria-hidden="true">
                <svg
                    viewBox="0 0 32 32"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path d="M7 12h16v8a6 6 0 0 1-6 6h-4a6 6 0 0 1-6-6z"/>
                    <path d="M23 13h2a4 4 0 0 1 0 8h-2"/>
                    <path d="M5 29h22"/>
                    <path d="M11 8V4M16 8V3M21 8V4"/>
                </svg>
            </div>

            <p class="marca-nombre">KARMA</p>
            <p class="marca-descripcion">Gestión de cafetería</p>
        </header>

        <section class="tarjeta-login" aria-labelledby="titulo-login">
            <h1 id="titulo-login">Iniciar sesión</h1>

            <p class="introduccion">
                Ingresa con tu cuenta para acceder al sistema.
            </p>

            @php
    $segundosBloqueo = max(
        0,
        (int) session('login_bloqueo_hasta', 0) - now()->timestamp
    );
@endphp

@if ($segundosBloqueo > 0)
    <div
        id="aviso-bloqueo"
        class="aviso error"
        data-segundos="{{ $segundosBloqueo }}"
    >
        <p>
            Se alcanzó el límite de intentos para la cuenta utilizada.
        </p>

        <p>
            Podrás intentarlo nuevamente en
            <strong id="contador-bloqueo">
                {{ sprintf(
                    '%02d:%02d',
                    intdiv($segundosBloqueo, 60),
                    $segundosBloqueo % 60
                ) }}
            </strong>.
        </p>

        <p>También puedes recuperar tu contraseña.</p>
    </div>
@endif

            @if (session('success'))
                <div class="aviso-exito" role="status">
                    {{ session('success') }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.store') }}">
                @csrf

                <div class="campo">
                    <label for="nombreUsuario">Usuario</label>

                    <input
                        type="text"
                        id="nombreUsuario"
                        name="nombreUsuario"
                        value="{{ old('nombreUsuario') }}"
                        placeholder="Escribe tu usuario"
                        maxlength="100"
                        autocomplete="username"
                        autocapitalize="none"
                        spellcheck="false"
                        aria-invalid="{{ $errors->has('nombreUsuario') ? 'true' : 'false' }}"
                        @error('nombreUsuario')
                            aria-describedby="error-usuario"
                        @enderror
                        required
                    >

                    @error('nombreUsuario')
                        <p id="error-usuario" class="error-campo" role="alert">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div class="campo">
                    <label for="password">Contraseña</label>

                    <div class="clave-con-control"><input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Escribe tu contraseña"
                        autocomplete="current-password"
                        aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                        @error('password')
                            aria-describedby="error-password"
                        @enderror
                        required
                    >
                    <button class="ver-clave" type="button" aria-controls="password" aria-pressed="false" aria-label="Mostrar contraseña"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg><span>Ver</span></button></div>

                    @error('password')
                        <p id="error-password" class="error-campo" role="alert">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <button type="submit" class="boton-ingresar">
                    Ingresar
                </button>
            </form>
            <p class="ayuda">
    <a href="{{ route('password.request') }}">
        ¿Olvidaste tu contraseña?
    </a>
</p>

            <p class="ayuda">
                Si necesitas ayuda para acceder, contacta
                al administrador de la cafetería.
            </p>
        </section>

        <footer class="pie">
            Acceso exclusivo para personal autorizado
        </footer>
    </main>
    <script>
    (() => {
        const aviso = document.getElementById('aviso-bloqueo');
        const contador = document.getElementById('contador-bloqueo');

        if (!aviso || !contador) {
            return;
        }

        const segundosIniciales = Number(aviso.dataset.segundos);
        const fin = Date.now() + segundosIniciales * 1000;

        let intervalo;

        function actualizarContador() {
            const restantes = Math.max(
                0,
                Math.ceil((fin - Date.now()) / 1000)
            );

            const minutos = String(
                Math.floor(restantes / 60)
            ).padStart(2, '0');

            const segundos = String(
                restantes % 60
            ).padStart(2, '0');

            contador.textContent = `${minutos}:${segundos}`;

            if (restantes === 0) {
                clearInterval(intervalo);

                aviso.textContent =
                    'El tiempo de espera terminó. Puedes intentar iniciar sesión nuevamente.';
            }
        }

        intervalo = setInterval(actualizarContador, 1000);
        actualizarContador();
    })();
</script>
<script>const clave=document.getElementById('password'),ver=document.querySelector('.ver-clave');ver.addEventListener('click',()=>{const visible=clave.type==='password';clave.type=visible?'text':'password';ver.querySelector('span').textContent=visible?'Ocultar':'Ver';ver.setAttribute('aria-label',visible?'Ocultar contraseña':'Mostrar contraseña');ver.setAttribute('aria-pressed',String(visible));});</script>
</body>
</html>
