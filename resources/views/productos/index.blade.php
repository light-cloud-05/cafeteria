@extends('layouts.app')

@section('titulo', 'Productos')

@section('contenido')
    <div class="barra">
        <h1>Productos</h1>

        <a href="{{ route('admin.crearproducto') }}" class="boton">
            Nuevo producto
        </a>
    </div>

    <div class="tarjeta">
        <div class="tabla-contenedor">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Producto</th>
                        <th scope="col">Categoría</th>
                        <th scope="col">Proveedor</th>
                        <th scope="col">Precio</th>
                        <th scope="col">Disponibilidad</th>
                        <th scope="col">Acciones</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($productos as $producto)
                        <tr>
                            <td>
                                <strong>{{ $producto->nombre }}</strong>

                                @if ($producto->descripcion)
                                    <p>{{ $producto->descripcion }}</p>
                                @endif
                            </td>

                            <td>
                                {{ $producto->categoria?->nombre ?? 'Sin categoría' }}

                                @if ($producto->categoria && ! $producto->categoria->estado)
                                    <br>
                                    <small>Categoría inactiva</small>
                                @endif
                            </td>

                            <td>
                                {{ $producto->proveedor?->nombre ?? 'Sin proveedor' }}

                                @if ($producto->proveedor && ! $producto->proveedor->estado)
                                    <br>
                                    <small>Proveedor inactivo</small>
                                @endif
                            </td>

                            <td>
                                Bs {{ number_format((float) $producto->precio, 2) }}
                            </td>

                            <td>
                                <span class="etiqueta {{ $producto->disponible ? 'activa' : 'inactiva' }}">
                                    {{ $producto->disponible ? 'Habilitado' : 'No disponible' }}
                                </span>
                            </td>

                            <td>
                                <div class="acciones">
                                    <a
                                        href="{{ route('admin.editproducto', $producto->id) }}"
                                        class="boton secundario"
                                    >
                                        Editar
                                    </a>

                                    @if ($producto->disponible)
                                        <form
                                            method="POST"
                                            action="{{ route('admin.destroyproducto', $producto->id) }}"
                                            onsubmit="return confirm('¿Marcar este producto como no disponible?');"
                                        >
                                            @csrf
                                            @method('DELETE')

                                            <button type="submit" class="boton secundario">
                                                Deshabilitar
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                Todavía no hay productos registrados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection