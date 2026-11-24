@extends('layouts.app')
@section('titulo','Resultado de pago simulado')
@include('pagos._estilos')
@section('contenido')
@php($pendiente = $intento->estado === \App\Services\Pagos\EstadoPago::PENDIENTE)
@php($responsable = (int)$intento->usuario_id === (int)auth()->id())
@php($esQr = $intento->medio === 'QR_SIMULADO')
<div class="pago-demo-contenedor pago-checkout">
    <header class="pago-checkout-cabecera">
        <div><p class="pago-demo-ceja">KARMA <span>/</span> CHECKOUT ACADÉMICO</p>
        <h1>{{ $esQr ? 'Pago con QR simulado' : 'Pago con tarjeta simulada' }}</h1>
        <p class="pago-subtitulo">Un flujo de pago, preparado para tu demostración.</p></div>
        <span class="pago-entorno">@include('pagos._icono',['icono'=>'escudo']) Entorno de prueba</span>
    </header>
    @include('pagos._aviso')
    <ol class="pago-pasos" aria-label="Progreso del pago">
        <li class="completo"><span>@include('pagos._icono',['icono'=>'check'])</span><div>Pedido<small>Importe confirmado</small></div></li>
        <li class="{{ $pendiente ? 'actual' : 'completo' }}" @if($pendiente) aria-current="step" @endif><span>02</span><div>Método<small>{{ $esQr ? 'QR de demostración' : 'Tarjeta de prueba' }}</small></div></li>
        <li class="{{ $pendiente ? '' : 'actual' }}" @if(!$pendiente) aria-current="step" @endif><span>03</span><div>Resultado<small>{{ $pendiente ? 'Por resolver' : 'Estado guardado' }}</small></div></li>
    </ol>
    @if($errors->any())<div class="aviso error" role="alert">{{ $errors->first() }}</div>@endif
    @if(session('pago_escenario'))<div class="aviso" role="status">Escenario ficticio solicitado: {{ session('pago_escenario') }}. El estado guardado se muestra a continuación.</div>@endif
    <div class="pago-checkout-grid">
        <aside class="pago-resumen tarjeta" aria-labelledby="resumen-pago">
            <div class="pago-panel-titulo"><span class="pago-icono-fondo">@include('pagos._icono',['icono'=>'bolsa'])</span><h2 id="resumen-pago">Resumen del pedido</h2></div>
            <p class="pago-numero">Pedido #{{ $intento->pedido->numero_diario ?? $intento->pedido_id }}</p>
            <p class="pago-importe-etiqueta">Importe de demostración</p>
            <p class="pago-demo-importe"><span>Bs</span> {{ number_format($intento->monto,2) }} <small>{{ $intento->moneda }}</small></p>
            <div class="pago-metodo-resumen">@include('pagos._icono',['icono'=>$esQr ? 'qr' : 'tarjeta'])<div><small>Método seleccionado</small><strong>{{ $esQr ? 'QR simulado' : 'Tarjeta simulada' }}</strong></div></div>
            <div class="pago-resumen-estado"><span>Estado del intento</span><span class="pago-demo-estado" role="status" aria-live="polite" data-estado="{{ $intento->estado->value }}">{{ $intento->estado->value }}</span></div>
            <dl class="pago-demo-datos">
                <dt>Referencia del intento</dt><dd><code>{{ $intento->referencia }}</code></dd>
                <dt>Inicio · Bolivia</dt><dd>{{ $intento->created_at?->format('d/m/Y H:i:s') }}</dd>
                <dt>Vencimiento · Bolivia</dt><dd>{{ $intento->vence_en->format('d/m/Y H:i:s') }}</dd>
                @if($intento->resuelto_en)<dt>Resolución · Bolivia</dt><dd>{{ $intento->resuelto_en->format('d/m/Y H:i:s') }}</dd>@endif
                <dt>Método registrado</dt><dd>{{ $intento->medio }}</dd>
                <dt>Origen</dt><dd><span class="pago-origen">SIMULACION_LOCAL</span></dd>
            </dl>
            <div class="pago-resumen-pie">@include('pagos._icono',['icono'=>'escudo']) Sin datos bancarios. Sin movimiento de dinero.</div>
        </aside>
        <section class="tarjeta pago-panel" aria-labelledby="metodo-pago-titulo">
            <div class="pago-panel-titulo"><span class="pago-icono-fondo">@include('pagos._icono',['icono'=>$esQr ? 'qr' : 'tarjeta'])</span><div><p class="pago-panel-ceja">{{ $esQr ? 'QR DE DEMOSTRACIÓN' : 'CHECKOUT DE TARJETA' }}</p><h2 id="metodo-pago-titulo">{{ $esQr ? 'Representación de QR' : 'Tarjeta ficticia' }}</h2></div></div>
            @if($esQr)
                <div class="pago-qr-presentacion">
                    @include('pagos._qr-decorativo')
                    <p class="pago-qr-subtitulo">QR decorativo · No escaneable</p>
                    <p>Representación no escaneable. No es un QR bancario válido ni permite transferir dinero.</p>
                </div>
            @else
                <div class="pago-metodo-seleccionado"><div class="pago-metodo-simbolo">@include('pagos._icono',['icono'=>'tarjeta'])</div><div><strong>Tarjeta de demostración</strong><small>Escenarios ficticios · No requiere datos de tarjeta</small></div><span class="pago-seleccion-check">@include('pagos._icono',['icono'=>'check'])</span></div>
                <div class="pago-garantias"><div>@include('pagos._icono',['icono'=>'escudo'])<span>Sin datos reales</span></div><div>@include('pagos._icono',['icono'=>'reloj'])<span>Resultado local</span></div><div>@include('pagos._icono',['icono'=>'check'])<span>Intento registrado</span></div></div>
                <p class="pago-ayuda">3-D Secure ficticio representa una autenticación pendiente de demostración. Ningún banco autentica al usuario; no se solicitan códigos ni datos de tarjetas.</p>
            @endif
            <div class="pago-resultado-mensaje" data-estado="{{ $intento->estado->value }}">
                @include('pagos._icono',['icono'=>$intento->estado === \App\Services\Pagos\EstadoPago::APROBADO ? 'check' : ($pendiente ? 'reloj' : 'info')])
                <div><strong>{{ match($intento->estado) {\App\Services\Pagos\EstadoPago::APROBADO=>'Demostración aprobada',\App\Services\Pagos\EstadoPago::RECHAZADO=>'Demostración rechazada',\App\Services\Pagos\EstadoPago::EXPIRADO=>'Intento expirado',\App\Services\Pagos\EstadoPago::CANCELADO=>'Intento cancelado',default=>'Listo para simular el resultado'} }}</strong>
                @switch($intento->estado)
                    @case(\App\Services\Pagos\EstadoPago::APROBADO)<p>Simulación aprobada localmente. Se registró una única venta ficticia y el pedido está pagado.</p>@break
                    @case(\App\Services\Pagos\EstadoPago::RECHAZADO)<p>Simulación rechazada. Este intento no generó una venta. Puedes preparar otro intento.</p>@break
                    @case(\App\Services\Pagos\EstadoPago::EXPIRADO)<p>El intento expiró sin generar una venta. Puedes preparar otro intento.</p>@break
                    @case(\App\Services\Pagos\EstadoPago::CANCELADO)<p>El intento fue cancelado sin generar una venta. La reserva del pedido se conserva.</p>@break
                    @default<p>No se ha registrado una venta. Este intento bloquea otros cobros y cambios del pedido.</p>
                @endswitch</div>
            </div>
            @if($intento->codigo_resultado === 'EXPIRACION_DEMO')<p class="pago-ayuda">Vencimiento forzado por un escenario ficticio, sin cambiar el reloj ni la fecha original de vigencia.</p>@endif
            @if($pendiente && $responsable)
                <form method="POST" action="{{ route('admin.pagos.resolver',$intento->referencia) }}" class="formulario pago-escenario" data-pago-accion>
                    @csrf
                    <div class="campo"><label for="escenario">Escenario de demostración</label><p class="pago-campo-ayuda" id="ayuda-escenario">Elige qué resultado ficticio presentar.</p>
                    <select name="escenario" id="escenario" aria-describedby="ayuda-escenario" required>
                        @foreach($escenarios as $escenario)<option value="{{ $escenario->value }}">{{ $escenario->etiqueta() }}</option>@endforeach
                    </select></div>
                    <button type="submit" class="boton pago-boton-principal">Ejecutar escenario local @include('pagos._icono',['icono'=>'flecha'])</button>
                    <p class="pago-campo-ayuda">La expiración ficticia puede terminar el intento antes de su vencimiento previsto.</p>
                </form>
                <div class="acciones pago-demo-acciones">
                    <form method="POST" action="{{ route('admin.pagos.consultar',$intento->referencia) }}" data-pago-accion>@csrf<button class="boton secundario">Consultar resultado / vencimiento</button></form>
                    <form method="POST" action="{{ route('admin.pagos.cancelar',$intento->referencia) }}" data-pago-accion>@csrf<button class="boton secundario">Cancelar intento</button></form>
                </div>
            @elseif($pendiente)
                <p class="pago-ayuda">Solo el cajero responsable puede ejecutar escenarios o cancelar este intento.</p>
            @elseif($intento->estado !== \App\Services\Pagos\EstadoPago::APROBADO && $intento->pedido->estado === 'Registrado' && !$intento->pedido->venta()->exists())
                <a class="boton pago-boton-principal" href="{{ route('admin.pagos.crear',['id'=>$intento->pedido_id,'medio'=>$intento->medio,'reintento'=>$intento->referencia]) }}">Preparar nuevo intento @include('pagos._icono',['icono'=>'flecha'])</a>
            @endif
            <p class="pago-nota-final">El botón Actualizar de la página consulta el estado guardado sin modificar el intento.</p>
        </section>
    </div>
    <nav class="acciones pago-navegacion" aria-label="Navegación del pago">
        <a class="boton secundario" href="{{ route('admin.pedido.ver',$intento->pedido_id) }}">Volver al detalle del pedido</a>
        <a class="boton secundario" href="{{ route('admin.pagos.historial',['pedido'=>$intento->pedido_id]) }}">Historial de intentos</a>
        <a href="{{ route('admin.indexventa') }}">Ventas y cobros</a>
    </nav>
</div>
<script src="{{ asset('js-pagos-demo.js') }}" defer></script>
@endsection
