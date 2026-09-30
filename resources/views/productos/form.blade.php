@extends('layouts.app')

@section('titulo', $producto->exists ? 'Editar producto' : 'Nuevo producto')

@section('contenido')
    <h1>
        {{ $producto->exists ? 'Editar producto' : 'Nuevo producto' }}
    </h1>

    @if ($categorias->isEmpty())
        <div class="aviso error" role="alert">
            Primero registra o activa una categoría para poder guardar productos.
        </div>
    @endif

    <div class="tarjeta formulario">
        <form
            method="POST"
            action="{{ $producto->exists
                ? route('admin.updateproducto', $producto->id)
                : route('admin.storedproducto') }}"
        >
            @csrf

            <div class="campo">
                <label for="nombre">Nombre del producto</label>

                <input
                    type="text"
                    id="nombre"
                    name="nombre"
                    value="{{ old('nombre', $producto->nombre) }}"
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
                    rows="3"
                    maxlength="255"
                >{{ old('descripcion', $producto->descripcion) }}</textarea>

                @error('descripcion')
                    <p class="error-campo" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="campo">
                <label for="precio">Precio de venta (Bs)</label>

                <input
                    type="number"
                    id="precio"
                    name="precio"
                    value="{{ old('precio', $producto->precio) }}"
                    min="0"
                    max="999999.99"
                    step="0.01"
                    required
                >

                @error('precio')
                    <p class="error-campo" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="campo">
                <label for="categoria_id">Categoría</label>

                <select id="categoria_id" name="categoria_id" required>
                    <option value="">Selecciona una categoría</option>

                    @foreach ($categorias as $categoria)
                        <option
                            value="{{ $categoria->id }}"
                            @selected(
                                (string) old('categoria_id', $producto->categoria_id)
                                === (string) $categoria->id
                            )
                        >
                            {{ $categoria->nombre }}
                            {{ $categoria->estado ? '' : '(Inactiva)' }}
                        </option>
                    @endforeach
                </select>

                @error('categoria_id')
                    <p class="error-campo" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="campo">
                <label for="proveedor_id">Proveedor (opcional)</label>

                <select id="proveedor_id" name="proveedor_id">
                    <option value="">Sin proveedor</option>

                    @foreach ($proveedores as $proveedor)
                        <option
                            value="{{ $proveedor->id }}"
                            @selected(
                                (string) old('proveedor_id', $producto->proveedor_id)
                                === (string) $proveedor->id
                            )
                        >
                            {{ $proveedor->nombre }}
                            {{ $proveedor->estado ? '' : '(Inactivo)' }}
                        </option>
                    @endforeach
                </select>

                @error('proveedor_id')
                    <p class="error-campo" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="campo">
                <label for="disponible">Disponibilidad en el catálogo</label>

                <select id="disponible" name="disponible" required>
                    <option
                        value="1"
                        @selected((int) old('disponible', $producto->disponible) === 1)
                    >
                        Habilitado
                    </option>

                    <option
                        value="0"
                        @selected((int) old('disponible', $producto->disponible) === 0)
                    >
                        No disponible
                    </option>
                </select>

                @error('disponible')
                    <p class="error-campo" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="acciones">
                <button
                    type="submit"
                    class="boton"
                    @disabled($categorias->isEmpty())
                >
                    {{ $producto->exists ? 'Guardar cambios' : 'Registrar producto' }}
                </button>

                <a
                    href="{{ route('admin.indexproducto') }}"
                    class="boton secundario"
                >
                    Cancelar
                </a>
            </div>
        </form>
    </div>
@endsection