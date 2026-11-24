@php($conciliaciones = ['inconsistente'=>'Datos inconsistentes','sin_cierres'=>'Sin cierres registrados','con_diferencias'=>'Con diferencias financieras','conciliado'=>'Conciliado','sin_diferencias_registradas'=>'Sin diferencias en los cierres registrados'])
<div class="tarjeta"><h2>Estado del período</h2><dl class="caja-datos">
<div><dt>Cobertura</dt><dd><span class="etiqueta {{ $resumen['estado'] === 'completo' ? 'activa' : 'inactiva' }}">{{ ucfirst($resumen['estado']) }}</span>@if($resumen['en_curso'] ?? false) — en curso @endif</dd></div>
<div><dt>Conciliación</dt><dd>{{ $conciliaciones[$resumen['conciliacion']] }}</dd></div>
<div><dt>Días terminados exigibles</dt><dd>{{ $resumen['dias_exigibles'] }}</dd></div>
<div><dt>Días con cierre válido</dt><dd>{{ $resumen['dias_cerrados'] }}</dd></div>
<div><dt>Días terminados sin cierre</dt><dd>{{ count($resumen['fechas_sin_cierre']) }}</dd></div>
<div><dt>Días abiertos / futuros</dt><dd>{{ $resumen['dias_abiertos'] }} / {{ $resumen['dias_futuros'] }}</dd></div>
<div><dt>Días rectificados</dt><dd>{{ $resumen['dias_rectificados'] }}</dd></div>
<div><dt>Días con diferencia</dt><dd>{{ $resumen['dias_con_diferencia'] }}</dd></div>
</dl></div>
@if($resumen['fechas_inconsistentes'])<div class="aviso error" role="alert">Totales no disponibles: hay cierres oficiales sin versión válida, importes incoherentes o cierres de días todavía no terminados. Fechas: {{ implode(', ', $resumen['fechas_inconsistentes']) }}. Solicita revisión; no se han reemplazado importes por cero.</div>@endif
<div class="tarjeta"><h2>Totales de cierres vigentes registrados</h2><p>Los días sin cierre no se estiman como ventas cero. Los faltantes y sobrantes se muestran separados, aunque la diferencia neta se compense.</p>
<dl class="caja-datos">@foreach(['total_ventas'=>'Total vendido','efectivo_esperado'=>'Efectivo esperado','qr_registrado'=>'QR registrado','tarjeta_registrado'=>'Tarjeta registrada','efectivo_contado'=>'Efectivo contado','diferencia'=>'Diferencia neta','faltantes'=>'Faltantes (negativos)','sobrantes'=>'Sobrantes (positivos)'] as $campo=>$etiqueta)<div><dt>{{ $etiqueta }}</dt><dd class="caja-importe">{{ \App\Support\ImporteConsolidadoVista::formatear($resumen['totales'][$campo] ?? null) }}</dd></div>@endforeach
<div><dt>Ventas incluidas en instantáneas vigentes</dt><dd>{{ $resumen['totales']['cantidad_ventas'] ?? 'No disponible' }}</dd></div>
</dl></div>
@if(!$resumen['dias_cerrados'])<div class="aviso" role="status">No hay cierres válidos registrados para este período.</div>@endif
<p>Fecha de corte: {{ $resumen['fecha_corte'] }} (Bolivia). Sin calendario de apertura configurado, se espera un cierre para cada día calendario terminado, incluso sin ventas.</p>
