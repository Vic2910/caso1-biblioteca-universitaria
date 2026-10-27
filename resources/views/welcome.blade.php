@extends('layouts.app')

@section('title', 'Biblioteca Universitaria — Inicio')

@section('content')
    <div class="aviso">
        <h2>Conexión a la base de datos verificada</h2>
        <p>
            La aplicación está conectada correctamente al motor de datos
            configurado en el archivo <code>.env</code>.
        </p>
        <p>
            Libros registrados: <strong>{{ $totalLibros }}</strong> &middot;
            Categorías: <strong>{{ $totalCategorias }}</strong> &middot;
            Préstamos: <strong>{{ $totalPrestamos }}</strong>
        </p>
    </div>
@endsection
