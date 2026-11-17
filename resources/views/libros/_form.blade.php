{{--
    Formulario compartido de libro: alta y edición (specs/003-gestion-libros,
    contracts/ui-structure §4). Recibe $categorias para el selector y, en
    edición, $libro con el libro a editar. El orden de los campos es el del
    contrato; cada error se muestra inmediatamente debajo de su campo (FR-004)
    y `old()` conserva lo introducido. Nunca hay campo de ejemplares
    disponibles: lo calcula el servidor (D-023, clarificación Q1).
--}}
@csrf
@isset($libro)
    @method('PUT')
@endisset

<div class="campo-formulario">
    <label for="isbn">ISBN</label>
    <input type="text"
           inputmode="numeric"
           id="isbn"
           name="isbn"
           value="{{ old('isbn', $libro->isbn ?? '') }}"
           required
           @error('isbn') aria-invalid="true" aria-describedby="error-isbn" @enderror>
    @error('isbn')
        <p class="error-campo" id="error-isbn" role="alert">{{ $message }}</p>
    @enderror
</div>

<div class="campo-formulario">
    <label for="titulo">Título</label>
    <input type="text"
           id="titulo"
           name="titulo"
           value="{{ old('titulo', $libro->titulo ?? '') }}"
           required
           @error('titulo') aria-invalid="true" aria-describedby="error-titulo" @enderror>
    @error('titulo')
        <p class="error-campo" id="error-titulo" role="alert">{{ $message }}</p>
    @enderror
</div>

<div class="campo-formulario">
    <label for="autor">Autor</label>
    <input type="text"
           id="autor"
           name="autor"
           value="{{ old('autor', $libro->autor ?? '') }}"
           required
           @error('autor') aria-invalid="true" aria-describedby="error-autor" @enderror>
    @error('autor')
        <p class="error-campo" id="error-autor" role="alert">{{ $message }}</p>
    @enderror
</div>

<div class="campo-formulario">
    <label for="editorial">Editorial</label>
    <input type="text"
           id="editorial"
           name="editorial"
           value="{{ old('editorial', $libro->editorial ?? '') }}"
           required
           @error('editorial') aria-invalid="true" aria-describedby="error-editorial" @enderror>
    @error('editorial')
        <p class="error-campo" id="error-editorial" role="alert">{{ $message }}</p>
    @enderror
</div>

<div class="campo-formulario">
    <label for="anio_publicacion">Año de publicación</label>
    <input type="number"
           id="anio_publicacion"
           name="anio_publicacion"
           min="1900"
           max="{{ now()->year }}"
           value="{{ old('anio_publicacion', $libro->anio_publicacion ?? '') }}"
           required
           @error('anio_publicacion') aria-invalid="true" aria-describedby="error-anio_publicacion" @enderror>
    @error('anio_publicacion')
        <p class="error-campo" id="error-anio_publicacion" role="alert">{{ $message }}</p>
    @enderror
</div>

<div class="campo-formulario">
    <label for="ejemplares_totales">Número de ejemplares</label>
    <input type="number"
           id="ejemplares_totales"
           name="ejemplares_totales"
           min="1"
           value="{{ old('ejemplares_totales', $libro->ejemplares_totales ?? '') }}"
           required
           @error('ejemplares_totales') aria-invalid="true" aria-describedby="error-ejemplares_totales" @enderror>
    @error('ejemplares_totales')
        <p class="error-campo" id="error-ejemplares_totales" role="alert">{{ $message }}</p>
    @enderror
</div>

<div class="campo-formulario">
    <label for="categoria_id">Categoría</label>
    <select id="categoria_id"
            name="categoria_id"
            class="entrada-categoria"
            required
            @error('categoria_id') aria-invalid="true" aria-describedby="error-categoria_id" @enderror>
        <option value="">Seleccione una categoría</option>
        @foreach ($categorias as $categoria)
            <option value="{{ $categoria->id }}"
                    @selected((int) old('categoria_id', $libro->categoria_id ?? 0) === $categoria->id)>
                {{ $categoria->nombre }}
            </option>
        @endforeach
    </select>
    @error('categoria_id')
        <p class="error-campo" id="error-categoria_id" role="alert">{{ $message }}</p>
    @enderror
</div>

<div class="campo-formulario">
    <label for="portada">Portada</label>
    @isset($libro)
        {{--
            Vista previa de la portada actual (005, D-024): la imagen se
            sirve por la ruta con sesión y, debajo, el input restringido a
            los formatos admitidos con su leyenda de contrato
            (user-messages.md §4). El control de borrado (US2) se añade
            tras el mensaje de validación de este mismo campo.
        --}}
        @include('partials.portada', ['libro' => $libro])
    @endisset
    <input type="file"
           data-portada
           id="portada"
           name="portada"
           accept="image/jpeg,image/png,image/webp"
           @error('portada') aria-invalid="true" aria-describedby="error-portada" @enderror>
    @error('portada')
        <p class="error-campo" id="error-portada" role="alert">{{ $message }}</p>
    @enderror
    @isset($libro)
        <p class="ayuda-campo">JPG, PNG o WebP, hasta 2 MB. Al guardar un archivo nuevo se reemplaza la portada actual.</p>
    @else
        <p class="ayuda-campo">Opcional: imagen JPG, PNG o WebP de hasta 2 MB.</p>
    @endisset
    @isset($libro)
        @if ($libro->portada !== null)
            {{--
                Botón «Eliminar portada» (005, ui-structure §1): enlaza con
                el formulario propio `formulario-eliminar-portada` —
                renderizado en `edit.blade.php`— mediante el atributo `form`,
                porque dentro del formulario de edición no caben
                formularios anidados. Solo visible con portada registrada y
                con confirmación nativa previa al envío del DELETE (SC-004).
            --}}
            <button type="submit" class="boton boton-peligro" form="formulario-eliminar-portada">Eliminar portada</button>
        @endif
    @endisset
</div>

<div class="acciones-formulario">
    <button type="submit" class="boton">Guardar</button>
    <a class="boton boton-secundario"
       href="{{ isset($libro) && $libro !== null ? route('catalogo.show', $libro) : route('catalogo.index') }}">Cancelar</a>
</div>
