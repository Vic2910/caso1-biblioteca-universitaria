@extends('layouts.app')

@section('title', 'Editar libro — Biblioteca Universitaria')

@section('content')
    <section class="formulario-gestion">
        <h2>Editar un libro</h2>

        @if (session('exito'))
            <div class="mensaje-exito" role="status">{{ session('exito') }}</div>
        @endif

        <form method="post" action="{{ route('libros.update', $libro) }}" enctype="multipart/form-data">
            @include('libros._form', ['categorias' => $categorias, 'libro' => $libro])
        </form>

        @if ($libro->portada !== null)
            {{--
                Formulario propio de borrado de portada (specs/005,
                ui-structure §1): no se anida dentro del de edición (HTML no
                lo permite); el botón «Eliminar portada» del campo `portada`
                lo activa con el atributo `form`. Sin la confirmación nativa
                no se envía ninguna petición (SC-004, user-messages.md §3).
            --}}
            <form method="post"
                  action="{{ route('libros.portadas.destroy', $libro) }}"
                  id="formulario-eliminar-portada"
                  onsubmit="return confirm('¿Seguro que desea eliminar la portada de este libro? La imagen dejará de mostrarse.')">
                @csrf
                @method('DELETE')
            </form>
        @endif
    </section>
@endsection
