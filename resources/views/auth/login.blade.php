@extends('layouts.app')

@section('title', 'Iniciar sesión — Biblioteca Universitaria')

@section('content')
    <section class="acceso">
        <h2>Iniciar sesión</h2>

        <p>Introduzca su correo y su contraseña para acceder a la biblioteca.</p>

        <form method="post" action="{{ route('login.store') }}">
            @csrf

            @error('credenciales')
                <div class="mensaje-error" role="alert" id="error-credenciales">
                    <p>{{ $message }}</p>
                </div>
            @enderror

            <div class="campo-formulario">
                <label for="email">Correo</label>
                <input type="email"
                       id="email"
                       name="email"
                       value="{{ old('email') }}"
                       autocomplete="username"
                       required
                       @error('email') aria-invalid="true" aria-describedby="error-email" @enderror
                       @if ($errors->has('credenciales')) aria-invalid="true" aria-describedby="error-credenciales" @endif>
                @error('email')
                    <p class="error-campo" id="error-email" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="campo-formulario">
                <label for="password">Contraseña</label>
                <input type="password"
                       id="password"
                       name="password"
                       autocomplete="current-password"
                       required
                       @error('password') aria-invalid="true" aria-describedby="error-password" @enderror
                       @if ($errors->has('credenciales')) aria-invalid="true" aria-describedby="error-credenciales" @endif>
                @error('password')
                    <p class="error-campo" id="error-password" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="acciones-formulario">
                <button type="submit" class="boton">Entrar</button>
            </div>
        </form>
    </section>
@endsection
