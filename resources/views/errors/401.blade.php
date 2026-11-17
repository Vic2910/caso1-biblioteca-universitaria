{{--
    Acceso denegado a una imagen de portada (specs/005-gestion-portadas, D-023,
    contracts/user-messages.md §5). Extiende el layout común y no revela si la
    portada existía ni ningún detalle del archivo (Principio IV). El enlace de
    inicio de sesión solo se muestra si existe la ruta `login` (008, opcional).
--}}
@extends('layouts.app')

@section('title', 'Inicio de sesión requerido — Biblioteca Universitaria')

@section('content')
    <div class="aviso">
        <h2>Debe iniciar sesión para ver las imágenes de portadas.</h2>
        <p>
            @if (Route::has('login'))
                <a href="{{ route('login') }}">Iniciar sesión</a>
                &mdash;
            @endif
            <a href="{{ route('catalogo.index') }}">Volver al catálogo</a>
        </p>
    </div>
@endsection
