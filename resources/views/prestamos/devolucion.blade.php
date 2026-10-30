{{--
    Formulario de devolución de préstamo (specs/004-gestion-prestamos,
    contracts/ui-structure §4). Resumen de solo lectura del préstamo y campo
    «Fecha de devolución real» con el error junto al campo y `old()`. Un
    préstamo ya devuelto muestra «Este préstamo ya fue devuelto.» junto a la
    acción y sin botón de envío; el POST directo también lo rechaza el
    servidor (M2, D-018).
--}}
@extends('layouts.app')

@section('title', 'Registrar devolución — Biblioteca Universitaria')

@section('content')
    <div class="formulario-prestamos">
        <h2>Registrar devolución</h2>

        <dl class="ficha-campos">
            <dt>Libro</dt>
            <dd>{{ $prestamo->libro->titulo }} ({{ $prestamo->libro->isbn }})</dd>

            <dt>Estudiante</dt>
            <dd>{{ $prestamo->nombre_estudiante }}</dd>

            <dt>Carné</dt>
            <dd>{{ $prestamo->carnet_estudiante }}</dd>

            <dt>Fecha de préstamo</dt>
            <dd>{{ $prestamo->fecha_prestamo->format('d/m/Y') }}</dd>

            <dt>Devolución prevista</dt>
            <dd>{{ $prestamo->fecha_devolucion_prevista->format('d/m/Y') }}</dd>

            <dt>Estado actual</dt>
            <dd>
                <span class="etiqueta-estado estado-{{ $prestamo->estado }}">
                    {{ ucfirst($prestamo->estado) }}
                </span>
            </dd>
        </dl>

        <form method="POST" action="{{ route('prestamos.devolver', $prestamo) }}">
            @csrf

            <div class="campo-formulario">
                <label for="fecha_devolucion_real">Fecha de devolución real</label>
                <input type="date"
                       id="fecha_devolucion_real"
                       name="fecha_devolucion_real"
                       value="{{ old('fecha_devolucion_real') }}"
                       required
                       @disabled($prestamo->devuelto())>
                @error('fecha_devolucion_real')
                    <p class="error-campo" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="acciones-formulario">
                @if ($prestamo->devuelto())
                    <p class="aviso-bloqueo" role="alert">
                        @error('devolucion')
                            {{ $message }}
                        @enderror
                        @unless ($errors->has('devolucion'))
                            Este préstamo ya fue devuelto.
                        @endunless
                    </p>
                @else
                    <button type="submit" class="boton">Registrar devolución</button>
                @endif

                <a href="{{ route('prestamos.index') }}" class="boton boton-secundario">Volver a préstamos</a>
            </div>

            @if (! $prestamo->devuelto() && $errors->has('devolucion'))
                <p class="error-campo" role="alert">{{ $errors->first('devolucion') }}</p>
            @endif
        </form>
    </div>

    <style>
        .formulario-prestamos h2 {
            margin-top: 0;
            color: var(--color-primario);
        }
    </style>
@endsection
