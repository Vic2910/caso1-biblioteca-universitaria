@extends('layouts.app')

@section('title', 'Página no encontrada — Biblioteca Universitaria')

@section('content')
    <div class="aviso">
        <h2>{{ $exception->getMessage() !== '' ? $exception->getMessage() : 'Página no encontrada' }}</h2>
        <p>
            <a href="{{ route('catalogo.index') }}">Volver al catálogo</a>
        </p>
    </div>
@endsection
