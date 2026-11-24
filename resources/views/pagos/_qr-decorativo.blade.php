{{-- Mosaico sin datos, URL, formato QR ni corrección de errores.
     Los tres localizadores están abiertos deliberadamente: no forman un QR válido. --}}
<div class="pago-qr-marco">
<svg class="pago-qr-mosaico" viewBox="0 0 37 37" role="img" aria-label="QR decorativo de demostración, no escaneable ni válido para pagar">
<rect width="37" height="37" rx="2" fill="white"/>
<g fill="#28332f">
@for($filaQr=0; $filaQr<29; $filaQr++)
    @for($colQr=0; $colQr<29; $colQr++)
        @if(!(($filaQr<9 && $colQr<9) || ($filaQr<9 && $colQr>19) || ($filaQr>19 && $colQr<9) || ($filaQr>9 && $filaQr<19 && $colQr>7 && $colQr<21)) && (($filaQr*11+$colQr*7+$filaQr*$colQr)%13)<6)
        <rect x="{{ $colQr+4 }}" y="{{ $filaQr+4 }}" width="1" height="1" rx=".12"/>
        @endif
    @endfor
@endfor
</g>
@foreach([[4,4],[26,4],[4,26]] as [$qx,$qy])
<g transform="translate({{ $qx }} {{ $qy }})"><path d="M6 1H1v5h5V4" fill="none" stroke="#28332f" stroke-width="1.8" stroke-linejoin="round"/><rect x="2.5" y="2.5" width="2" height="2" rx=".3" fill="#a66b3e"/></g>
@endforeach
<rect x="11" y="14" width="15" height="8" rx="1.2" fill="#faf5eb" stroke="#d6b58a" stroke-width=".3"/>
<text x="18.5" y="17.6" text-anchor="middle" fill="#724a2e" font-family="Arial,sans-serif" font-size="2.6" font-weight="700">DEMO</text>
<text x="18.5" y="20" text-anchor="middle" fill="#724a2e" font-family="Arial,sans-serif" font-size="1.35" font-weight="700">NO ESCANEABLE</text>
</svg>
</div>
