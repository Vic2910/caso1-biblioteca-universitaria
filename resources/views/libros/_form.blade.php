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
           required>
    @error('isbn')
        <p class="error-campo" role="alert">{{ $message }}</p>
    @enderror
</div>

<div class="campo-formulario">
    <label for="titulo">Título</label>
    <input type="text"
           id="titulo"
           name="titulo"
           value="{{ old('titulo', $libro->titulo ?? '') }}"
           required>
    @error('titulo')
        <p class="error-campo" role="alert">{{ $message }}</p>
    @enderror
</div>

<div class="campo-formulario">
    <label for="autor">Autor</label>
    <input type="text"
           id="autor"
           name="autor"
           value="{{ old('autor', $libro->autor ?? '') }}"
           required>
    @error('autor')
        <p class="error-campo" role="alert">{{ $message }}</p>
    @enderror
</div>

<div class="campo-formulario">
    <label for="editorial">Editorial</label>
    <input type="text"
           id="editorial"
           name="editorial"
           value="{{ old('editorial', $libro->editorial ?? '') }}"
           required>
    @error('editorial')
        <p class="error-campo" role="alert">{{ $message }}</p>
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
           required>
    @error('anio_publicacion')
        <p class="error-campo" role="alert">{{ $message }}</p>
    @enderror
</div>

<div class="campo-formulario">
    <label for="ejemplares_totales">Número de ejemplares</label>
    <input type="number"
           id="ejemplares_totales"
           name="ejemplares_totales"
           min="1"
           value="{{ old('ejemplares_totales', $libro->ejemplares_totales ?? '') }}"
           required>
    @error('ejemplares_totales')
        <p class="error-campo" role="alert">{{ $message }}</p>
    @enderror
</div>

<div class="campo-formulario">
    <label for="categoria_id">Categoría</label>
    <select id="categoria_id" name="categoria_id" class="entrada-categoria" required>
        <option value="">Seleccione una categoría</option>
        @foreach ($categorias as $categoria)
            <option value="{{ $categoria->id }}"
                    @selected((int) old('categoria_id', $libro->categoria_id ?? 0) === $categoria->id)>
                {{ $categoria->nombre }}
            </option>
        @endforeach
    </select>
    @error('categoria_id')
        <p class="error-campo" role="alert">{{ $message }}</p>
    @enderror
</div>

<div class="campo-formulario">
    <label for="portada">Portada</label>
    @isset($libro)
        @include('partials.portada', ['portada' => $libro->portada, 'titulo' => $libro->titulo])
        <p class="ayuda-campo">El reemplazo de la portada estará disponible en una funcionalidad futura.</p>
    @else
        <input type="file"
               id="portada"
               name="portada"
               accept="image/jpeg,image/png,image/webp">
        <p class="ayuda-campo">Opcional: imagen JPG, PNG o WebP de hasta 2 MB.</p>
    @endisset
    @error('portada')
        <p class="error-campo" role="alert">{{ $message }}</p>
    @enderror
</div>

<div class="acciones-formulario">
    <button type="submit" class="boton">Guardar</button>
    <a class="boton boton-secundario"
       href="{{ isset($libro) && $libro !== null ? route('catalogo.show', $libro) : route('catalogo.index') }}">Cancelar</a>
</div>
