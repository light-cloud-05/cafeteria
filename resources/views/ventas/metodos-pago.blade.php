@extends('layouts.app')
@section('titulo', 'Métodos de pago')
@section('contenido')
    <h1>Métodos de pago</h1>
    <p>Selecciona el método de pago al cobrar un pedido desde Ventas y cobros.</p>
    <div class="tarjeta tabla-contenedor">
        <table>
            <thead><tr><th>Método</th><th>Cómo se utiliza</th></tr></thead>
            <tbody>
                <tr><td>Efectivo</td><td>Ingresa el monto recibido para calcular el cambio.</td></tr>
                <tr><td>QR</td><td>Confirma la recepción del pago antes de registrar la venta.</td></tr>
                <tr><td>Mixto</td><td>Ingresa la parte pagada con QR y el monto recibido en efectivo.</td></tr>
            </tbody>
        </table>
    </div>
    <p><a class="boton" href="{{ route('admin.indexventa') }}">Ir a Ventas y cobros</a></p>
@endsection
