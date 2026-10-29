{{--
    Imagen de portada compartida por listado y ficha (specs/002-consulta-catalogo, D-014).
    Recibe: $portada (string|null, ruta en Storage público) y $titulo (string).
--}}
@if ($portada === null)
    <img class="portada"
         src="{{ asset('images/libro-sin-portada.svg') }}"
         alt="Sin portada">
@else
    <img class="portada"
         src="{{ Storage::disk('public')->url($portada) }}"
         onerror="this.onerror=null;this.src='{{ asset('images/libro-sin-portada.svg') }}';"
         alt="Portada de {{ $titulo }}">
@endif
