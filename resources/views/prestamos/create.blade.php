{{--
    Formulario de registro de préstamo (specs/004-gestion-prestamos,
    contracts/ui-structure §3). El orden de los campos es el del contrato;
    cada error se muestra inmediatamente debajo de su campo (FR-006) y
    `old()` conserva lo introducido, incluida la re-selección del libro en
    el <select>. Se muestran también los libros agotados: su rechazo es un
    mensaje explícito del servidor junto al campo (D-027).
--}}
@extends('layouts.app')

@section('title', 'Registrar préstamo — Biblioteca Universitaria')

@section('content')
    <div class="formulario-prestamos">
        <h2>Registrar préstamo</h2>

        <form method="POST" action="{{ route('prestamos.store') }}">
            @csrf

            <div class="campo-formulario">
                <label for="libro_id">Libro</label>
                <select id="libro_id"
                        name="libro_id"
                        required
                        @error('libro_id') aria-invalid="true" aria-describedby="error-libro_id" @enderror>
                    <option value="">Seleccione un libro</option>
                    @foreach ($libros as $libro)
                        <option value="{{ $libro->id }}" @selected(old('libro_id') == $libro->id)>
                            {{ $libro->titulo }} ({{ $libro->isbn }}) — disponibles: {{ $libro->ejemplares_disponibles }}
                        </option>
                    @endforeach
                </select>
                @error('libro_id')
                    <p class="error-campo" id="error-libro_id" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="campo-formulario">
                <label for="nombre_estudiante">Nombre del estudiante</label>
                <input type="text"
                       id="nombre_estudiante"
                       name="nombre_estudiante"
                       value="{{ old('nombre_estudiante') }}"
                       minlength="2"
                       maxlength="255"
                       required
                       @error('nombre_estudiante') aria-invalid="true" aria-describedby="error-nombre_estudiante" @enderror>
                @error('nombre_estudiante')
                    <p class="error-campo" id="error-nombre_estudiante" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="campo-formulario">
                <label for="carnet_estudiante">Carné</label>
                <input type="text"
                       id="carnet_estudiante"
                       name="carnet_estudiante"
                       value="{{ old('carnet_estudiante') }}"
                       maxlength="50"
                       required
                       @error('carnet_estudiante') aria-invalid="true" aria-describedby="error-carnet_estudiante" @enderror>
                @error('carnet_estudiante')
                    <p class="error-campo" id="error-carnet_estudiante" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="campo-formulario">
                <label for="fecha_prestamo">Fecha de préstamo</label>
                <input type="date"
                       id="fecha_prestamo"
                       name="fecha_prestamo"
                       value="{{ old('fecha_prestamo', $fechaPrestamo) }}"
                       required
                       @error('fecha_prestamo') aria-invalid="true" aria-describedby="error-fecha_prestamo" @enderror>
                @error('fecha_prestamo')
                    <p class="error-campo" id="error-fecha_prestamo" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="campo-formulario">
                <label for="fecha_devolucion_prevista">Fecha de devolución prevista</label>
                <input type="date"
                       id="fecha_devolucion_prevista"
                       name="fecha_devolucion_prevista"
                       value="{{ old('fecha_devolucion_prevista') }}"
                       required
                       @error('fecha_devolucion_prevista') aria-invalid="true" aria-describedby="error-fecha_devolucion_prevista" @enderror>
                @error('fecha_devolucion_prevista')
                    <p class="error-campo" id="error-fecha_devolucion_prevista" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="acciones-formulario">
                <button type="submit" class="boton">Registrar préstamo</button>
                <a href="{{ route('prestamos.index') }}" class="boton boton-secundario">Volver a préstamos</a>
            </div>
        </form>
    </div>
@endsection
