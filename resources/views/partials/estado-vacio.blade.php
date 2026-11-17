{{--
    Estado vacío compartido (008, D-046): explica siempre por qué no hay
    datos y ofrece la acción correspondiente en los listados gestionables.
    Recibe: $mensaje, y opcionalmente $detalle, $accion y $url.
--}}
<div class="estado-vacio" role="status">
    <p>{{ $mensaje }}</p>
    @isset($detalle)
        <p class="estado-vacio-detalle">{{ $detalle }}</p>
    @endisset
    @isset($accion, $url)
        <a class="boton" href="{{ $url }}">{{ $accion }}</a>
    @endisset
</div>
