@extends('layouts.app')

@section('title', 'Página no encontrada — Biblioteca Universitaria')

@section('content')
    <div class="aviso">
        <h2>{{ $exception->getMessage() !== '' ? $exception->getMessage() : 'Página no encontrada' }}</h2>
        <p>
            <a href="{{ route('catalogo.index') }}">Volver al catálogo</a>
            @if ($exception->getMessage() === 'Préstamo no encontrado')
                &mdash; <a href="{{ route('prestamos.index') }}">Volver a préstamos</a>
            @endif
        </p>
    </div>
@endsection
