@extends('layouts.app')

@section('title', 'Editar categoría — Biblioteca Universitaria')

@section('content')
    <section class="formulario-gestion">
        <h2>Editar categoría</h2>

        <form method="post" action="{{ route('categorias.update', $categoria) }}">
            @csrf
            @method('PUT')

            <div class="campo-formulario">
                <label for="nombre">Nombre</label>
                <input type="text"
                       id="nombre"
                       name="nombre"
                       maxlength="100"
                       value="{{ old('nombre', $categoria->nombre) }}"
                       required
                       @error('nombre') aria-invalid="true" aria-describedby="error-nombre" @enderror>
                @error('nombre')
                    <p class="error-campo" id="error-nombre" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="acciones-formulario">
                <button type="submit" class="boton">Guardar</button>
                <a class="boton boton-secundario" href="{{ route('categorias.index') }}">Cancelar</a>
            </div>
        </form>
    </section>
@endsection
