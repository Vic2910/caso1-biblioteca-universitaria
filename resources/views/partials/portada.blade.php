{{--
    Imagen de portada compartida por listado y ficha (specs/002-consulta-catalogo,
    D-014; ampliada por specs/005-gestion-portadas, D-023/D-024 y
    contracts/ui-structure.md §3).
    Recibe: $libro (App\Models\Libro) con su `portada` y su `titulo`.
    Con portada, el `src` es la ruta controlada que exige sesión; el `onerror`
    cubre el 401 de un visitante y el archivo caído mostrando la predeterminada.
--}}
@if ($libro->portada === null)
    <img class="portada"
         src="{{ asset('images/libro-sin-portada.svg') }}"
         alt="Sin portada">
@else
    <img class="portada"
         src="{{ route('portadas.show', $libro) }}"
         onerror="this.onerror=null;this.src='{{ asset('images/libro-sin-portada.svg') }}';"
         alt="{{ $libro->titulo }}">
@endif
