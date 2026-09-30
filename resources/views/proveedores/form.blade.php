@extends('layouts.app')

@section('titulo', $proveedor->exists ? 'Editar proveedor' : 'Nuevo proveedor')

@section('contenido')
    <h1>
        {{ $proveedor->exists ? 'Editar proveedor' : 'Nuevo proveedor' }}
    </h1>

    <div class="tarjeta formulario">
        <form
            method="POST"
            action="{{ $proveedor->exists
                ? route('admin.updateproveedor', $proveedor->id)
                : route('admin.storedproveedor') }}"
        >
            @csrf

            <div class="campo">
                <label for="nombre">Nombre o razón social</label>

                <input
                    type="text"
                    id="nombre"
                    name="nombre"
                    value="{{ old('nombre', $proveedor->nombre) }}"
                    maxlength="255"
                    required
                >

                @error('nombre')
                    <p class="error-campo" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="campo">
                <label for="telefono">Teléfono (opcional)</label>

                <input
                    type="tel"
                    id="telefono"
                    name="telefono"
                    value="{{ old('telefono', $proveedor->telefono) }}"
                    maxlength="30"
                    autocomplete="tel"
                >

                @error('telefono')
                    <p class="error-campo" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="campo">
                <label for="estado">Estado</label>

                <select id="estado" name="estado" required>
                    <option
                        value="1"
                        @selected((int) old('estado', $proveedor->estado) === 1)
                    >
                        Activo
                    </option>

                    <option
                        value="0"
                        @selected((int) old('estado', $proveedor->estado) === 0)
                    >
                        Inactivo
                    </option>
                </select>

                @error('estado')
                    <p class="error-campo" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="acciones">
                <button type="submit" class="boton">
                    {{ $proveedor->exists ? 'Guardar cambios' : 'Registrar proveedor' }}
                </button>

                <a
                    href="{{ route('admin.indexproveedor') }}"
                    class="boton secundario"
                >
                    Cancelar
                </a>
            </div>
        </form>
    </div>
@endsection