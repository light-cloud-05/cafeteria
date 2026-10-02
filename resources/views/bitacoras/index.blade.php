@extends('layouts.app')

@section('titulo', 'Bitácora del sistema')

@section('contenido')
    <div class="tarjeta">
        <h2>Bitácora del sistema</h2>

        <p>Consulta las acciones registradas por el sistema.</p>

        @if ($errors->any())
            <div class="aviso error" role="alert">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form
            method="GET"
            action="{{ route('admin.indexbitacora') }}"
        >
            <div class="campo">
                <label for="usuario">
                    Nombre de usuario exacto
                </label>

                <input
                    type="text"
                    id="usuario"
                    name="usuario"
                    value="{{ request('usuario') }}"
                    placeholder="Ejemplo: cmendoza"
                    maxlength="100"
                >
            </div>

            <div class="campo">
                <label for="accion">Acción</label>

                <select id="accion" name="accion">
                    <option value="">Todas las acciones</option>

                    @foreach ($acciones as $accion)
                        <option
                            value="{{ $accion }}"
                            @selected(request('accion') === $accion)
                        >
                            {{ str_replace('_', ' ', $accion) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="acciones">
                <button type="submit" class="boton">
                    Filtrar
                </button>

                <a
                    href="{{ route('admin.indexbitacora') }}"
                    class="boton secundario"
                >
                    Limpiar filtros
                </a>
            </div>
        </form>

        <p>Total de registros encontrados: {{ $bitacoras->total() }}</p>

        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Fecha y hora del servidor</th>
                        <th>Usuario</th>
                        <th>Acción</th>
                        <th>Tabla</th>
                        <th>Registro afectado</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($bitacoras as $bitacora)
                        <tr>
                            <td>{{ $bitacora->id }}</td>

                            <td>
                                {{ $bitacora->fechaHora?->format('d/m/Y H:i:s') }}
                            </td>

                            <td>
                                {{ $bitacora->usuario?->nombreUsuario ?? 'No disponible' }}
                            </td>

                            <td>
                                {{ str_replace('_', ' ', $bitacora->accion) }}
                            </td>

                            <td>{{ $bitacora->tablaAfectada }}</td>

                            <td>{{ $bitacora->idRegistroAfectado }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                No hay registros para mostrar.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="acciones">
            @if ($bitacoras->previousPageUrl())
                <a
                    href="{{ $bitacoras->previousPageUrl() }}"
                    class="boton secundario"
                >
                    Anterior
                </a>
            @endif

            <span>
                Página {{ $bitacoras->currentPage() }}
                de {{ $bitacoras->lastPage() }}
            </span>

            @if ($bitacoras->nextPageUrl())
                <a
                    href="{{ $bitacoras->nextPageUrl() }}"
                    class="boton"
                >
                    Siguiente
                </a>
            @endif
        </div>
    </div>
@endsection