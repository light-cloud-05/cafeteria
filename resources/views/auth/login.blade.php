<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión | Cafetería</title>
</head>
<body>
    <h1>Iniciar sesión</h1>

    @if (session('success'))
        <p role="status">{{ session('success') }}</p>
    @endif

    <form method="POST" action="{{ route('login.store') }}">
        @csrf

        <div>
            <label for="nombreUsuario">Usuario</label>

            <input
                type="text"
                id="nombreUsuario"
                name="nombreUsuario"
                value="{{ old('nombreUsuario') }}"
                maxlength="100"
                autocomplete="username"
                required
                autofocus
            >

            @error('nombreUsuario')
                <p role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password">Contraseña</label>

            <input
                type="password"
                id="password"
                name="password"
                autocomplete="current-password"
                required
            >

            @error('password')
                <p role="alert">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit">Ingresar</button>
    </form>
</body>
</html>