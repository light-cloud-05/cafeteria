@if($paginador->hasPages())
<div class="caja-paginacion"><nav aria-label="{{ $etiqueta }}">
    @if($paginador->onFirstPage())<span aria-disabled="true">Anterior</span>@else<a class="boton secundario" href="{{ $paginador->previousPageUrl() }}" rel="prev">Anterior</a>@endif
    <span aria-current="page">Página {{ $paginador->currentPage() }} de {{ $paginador->lastPage() }}</span>
    @if($paginador->hasMorePages())<a class="boton secundario" href="{{ $paginador->nextPageUrl() }}" rel="next">Siguiente</a>@else<span aria-disabled="true">Siguiente</span>@endif
</nav></div>
@endif
