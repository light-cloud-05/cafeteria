@once
    @push('styles')<link rel="stylesheet" href="{{ asset('css/caja.css') }}">@endpush
@endonce
<nav class="acciones caja-navegacion" aria-label="Navegación de caja">
    <a class="boton secundario" href="{{ route('admin.caja.index') }}" @if(request()->routeIs('admin.caja.index')) aria-current="page" @endif>Listado de cierres</a>
    <a class="boton secundario" href="{{ route('admin.caja.previsualizacion') }}" @if(request()->routeIs('admin.caja.previsualizacion')) aria-current="page" @endif>Previsualización del día</a>
    <a class="boton secundario" href="{{ route('admin.caja.mensual') }}" @if(request()->routeIs('admin.caja.mensual')) aria-current="page" @endif>Consolidado mensual</a>
    <a class="boton secundario" href="{{ route('admin.caja.anual') }}" @if(request()->routeIs('admin.caja.anual')) aria-current="page" @endif>Consolidado anual</a>
    @if(auth()->user()->tienePermiso('consultar_caja') && auth()->user()->tienePermiso('confirmar_cierre_caja'))
        <a class="boton secundario" href="{{ route('admin.caja.confirmacion') }}">Formulario de confirmación</a>
    @endif
    @isset($cierre)
        @if($cierre)
            <a class="boton secundario" href="{{ route('admin.caja.show', $cierre->id) }}" @if(request()->routeIs('admin.caja.show')) aria-current="page" @endif>Detalle financiero</a>
            <a class="boton secundario" href="{{ route('admin.caja.show', $cierre->id) }}#historial-versiones">Historial de versiones</a>
            @if($cierre->fecha_operativa && auth()->user()->tienePermiso('consultar_caja') && auth()->user()->tienePermiso('rectificar_cierre_caja'))
                <a class="boton secundario" href="{{ route('admin.caja.rectificacion', $cierre->id) }}">Formulario de rectificación</a>
            @endif
        @endif
    @endisset
</nav>
