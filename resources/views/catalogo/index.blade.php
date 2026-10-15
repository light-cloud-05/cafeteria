@extends('layouts.app')
@section('titulo','Buscar producto')
@section('contenido')
<h1>Buscar producto</h1><form method="GET" id="buscar-catalogo"><div class="campo"><label for="consulta-producto">Nombre o código (ID)</label><input id="consulta-producto" type="search" name="q" maxlength="255" value="{{ $q }}" autocomplete="off" placeholder="Escribe, por ejemplo: Lim"></div><button class="boton">Buscar</button></form>
<p id="estado-busqueda" role="status" aria-live="polite"></p>
<div class="tarjeta tabla-contenedor"><table><thead><tr><th>Código</th><th>Producto</th><th>Descripción</th><th>Precio</th><th>Disponibilidad</th><th>Seleccionar</th></tr></thead><tbody>
@forelse($productos as $p)<tr><td>{{ $p->id }}</td><td>{{ $p->nombre }}</td><td>{{ $p->descripcion }}</td><td>Bs {{ $p->precio }}</td><td>{{ $p->vendible() ? 'Disponible' : 'Agotado o no disponible' }}</td><td><a href="{{ route('admin.catalogo',['producto'=>$p->id]) }}">Ver producto</a></td></tr>@empty<tr><td colspan="6">Producto no encontrado.</td></tr>@endforelse
</tbody></table></div>
<script>
(() => {
    const input=document.getElementById('consulta-producto'), estado=document.getElementById('estado-busqueda'), tabla=document.querySelector('table');
    tabla.dataset.busquedaRemota='1';
    let temporizador, solicitud, version=0;
    input.addEventListener('input', () => {
        clearTimeout(temporizador); solicitud?.abort(); const actual=++version;
        temporizador=setTimeout(async () => {
            solicitud=new AbortController(); estado.textContent='Buscando…';
            const url=new URL(document.getElementById('buscar-catalogo').action || location.href);
            url.search=''; url.searchParams.set('q',input.value);
            try {
                const respuesta=await fetch(url,{signal:solicitud.signal,headers:{'X-Requested-With':'XMLHttpRequest'}});
                if(!respuesta.ok || respuesta.redirected) throw new Error('No disponible');
                const pagina=new DOMParser().parseFromString(await respuesta.text(),'text/html');
                const cuerpo=pagina.querySelector('tbody'); if(!cuerpo) throw new Error('Sin resultados');
                if(actual!==version) return;
                tabla.querySelector('tbody').replaceWith(cuerpo);
                estado.textContent='Resultados actualizados.';
                history.replaceState(null,'',url);
            } catch(error) { if(error.name!=='AbortError' && actual===version) estado.textContent='No se pudo actualizar. Pulsa Buscar para volver a intentar.'; }
        },200);
    });
})();
</script>
@endsection
