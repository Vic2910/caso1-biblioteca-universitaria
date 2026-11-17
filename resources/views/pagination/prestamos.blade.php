{{--
    Vista propia de la paginación del listado de préstamos
    (specs/004-gestion-prestamos, D-024). Sin frameworks CSS: usa solo las
    clases propias del proyecto.
--}}
@if ($paginator->hasPages())
    <nav class="paginacion" aria-label="Paginación de préstamos">
        <ul>
            @if ($paginator->onFirstPage())
                <li><span class="pagina-inactiva">Anterior</span></li>
            @else
                <li><a href="{{ $paginator->previousPageUrl() }}" rel="prev">Anterior</a></li>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li><span class="pagina-omision">{{ $element }}</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $pagina => $url)
                        @if ($pagina == $paginator->currentPage())
                            <li><span class="pagina-activa" aria-current="page">{{ $pagina }}</span></li>
                        @else
                            <li><a href="{{ $url }}">{{ $pagina }}</a></li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <li><a href="{{ $paginator->nextPageUrl() }}" rel="next">Siguiente</a></li>
            @else
                <li><span class="pagina-inactiva">Siguiente</span></li>
            @endif
        </ul>
    </nav>
@endif
