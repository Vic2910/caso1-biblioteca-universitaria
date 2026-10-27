@extends('layouts.app')

@section('title', 'Error de conexión — Biblioteca Universitaria')

@section('content')
    <div class="aviso" role="alert">
        <h2>No se pudo conectar con la base de datos</h2>
        <p>
            La aplicación no se pudo conectar con la base de datos; para
            continuar, revise la configuración de conexión en el archivo
            <code>.env</code> (host, puerto, nombre de base de datos y
            credenciales) y vuelva a intentarlo.
        </p>
    </div>
@endsection
