@extends('layouts.app')

@section('titulo', $categoria->exists ? 'Editar categoría' : 'Nueva categoría')

@section('contenido')
    <h1>
        {{ $categoria->exists ? 'Editar categoría' : 'Nueva categoría' }}
    </h1>

    <div class="tarjeta formulario">
        <form
            method="POST"
            action="{{ $categoria->exists
                ? route('admin.updatecategoria', $categoria->id)
                : route('admin.storedcategoria') }}"
        >
            @csrf

            <div class="campo">
                <label for="nombre">Nombre</label>

                <input
                    type="text"
                    id="nombre"
                    name="nombre"
                    value="{{ old('nombre', $categoria->nombre) }}"
                    maxlength="255"
                    required
                >

                @error('nombre')
                    <p class="error-campo" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="campo">
                <label for="descripcion">Descripción (opcional)</label>

                <textarea
                    id="descripcion"
                    name="descripcion"
                    rows="4"
                    maxlength="255"
                >{{ old('descripcion', $categoria->descripcion) }}</textarea>

                @error('descripcion')
                    <p class="error-campo" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="campo">
                <label for="estado">Estado</label>

                <select id="estado" name="estado" required>
                    <option
                        value="1"
                        @selected((string) old('estado', $categoria->estado) === '1')
                    >
                        Activa
                    </option>

                    <option
                        value="0"
                        @selected((string) old('estado', $categoria->estado) === '0')
                    >
                        Inactiva
                    </option>
                </select>

                @error('estado')
                    <p class="error-campo" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="acciones">
                <button type="submit" class="boton">
                    {{ $categoria->exists ? 'Guardar cambios' : 'Registrar categoría' }}
                </button>

                <a
                    href="{{ route('admin.indexcategoria') }}"
                    class="boton secundario"
                >
                    Cancelar
                </a>
            </div>
        </form>
    </div>
@endsection