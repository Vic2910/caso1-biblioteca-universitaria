@extends('layouts.app')

@section('title', 'Editar libro — Biblioteca Universitaria')

@section('content')
    <section class="formulario-gestion">
        <h2>Editar un libro</h2>

        <form method="post" action="{{ route('libros.update', $libro) }}" enctype="multipart/form-data">
            @include('libros._form', ['categorias' => $categorias, 'libro' => $libro])
        </form>
    </section>
@endsection
