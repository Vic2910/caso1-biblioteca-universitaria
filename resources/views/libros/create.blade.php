@extends('layouts.app')

@section('title', 'Registrar libro — Biblioteca Universitaria')

@section('content')
    <section class="formulario-gestion">
        <h2>Registrar un libro</h2>

        <form method="post" action="{{ route('libros.store') }}" enctype="multipart/form-data">
            @include('libros._form', ['categorias' => $categorias])
        </form>
    </section>
@endsection
