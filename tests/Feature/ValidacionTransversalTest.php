<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Libro;
use App\Models\Prestamo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * V-005 y SC-002: el servidor rechaza todos los campos inválidos a la vez,
 * muestra cada mensaje en español justo después de su campo con `aria-*`, y
 * conserva los valores introducidos (FR-003/FR-004, D-041).
 */
class ValidacionTransversalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    /**
     * Comprueba el orden etiqueta → campo → error y la asociación ARIA.
     */
    private function assertErrorAsociadoAlCampo(string $html, string $campo, string $mensaje): void
    {
        $nombre = preg_quote($campo, '/');
        $texto = preg_quote($mensaje, '/');

        $this->assertMatchesRegularExpression(
            '/<(?:input|select|textarea)\b(?=[^>]*\bid="'.$nombre.'")(?=[^>]*\baria-invalid="true")(?=[^>]*\baria-describedby="error-'.$nombre.'")[^>]*>(?:.*?<\/(?:select|textarea)>)?\s*<p\b[^>]*\bid="error-'.$nombre.'"[^>]*>'.$texto.'/s',
            $html,
            'El error de '.$campo.' debe ir inmediatamente después del campo y estar asociado con aria.'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function datosValidosLibro(Libro $libro): array
    {
        return [
            'isbn' => $libro->isbn,
            'titulo' => $libro->titulo,
            'autor' => $libro->autor,
            'editorial' => $libro->editorial,
            'anio_publicacion' => $libro->anio_publicacion,
            'ejemplares_totales' => $libro->ejemplares_totales,
            'categoria_id' => $libro->categoria_id,
        ];
    }

    public function test_el_alta_de_libro_muestra_todos_los_errores_y_conserva_los_valores(): void
    {
        $anio = now()->year;

        $respuesta = $this->from(route('libros.create'))->post('/libros', [
            'isbn' => '123456789012',
            'titulo' => '',
            'autor' => '',
            'editorial' => 'Editorial conservada',
            'anio_publicacion' => 1800,
            'ejemplares_totales' => 0,
            'categoria_id' => '',
        ]);

        $respuesta->assertRedirect(route('libros.create'));
        $respuesta->assertSessionHasErrors([
            'isbn' => 'El ISBN debe tener exactamente 13 dígitos, sin guiones ni espacios.',
            'titulo' => 'El campo Título es obligatorio.',
            'autor' => 'El campo Autor es obligatorio.',
            'anio_publicacion' => 'El año de publicación debe estar entre 1900 y '.$anio.'.',
            'ejemplares_totales' => 'El campo Número de ejemplares debe ser un número entero mayor o igual que 1.',
            'categoria_id' => 'Debe seleccionar una categoría válida.',
        ]);
        $respuesta->assertSessionHasInput([
            'isbn' => '123456789012',
            'editorial' => 'Editorial conservada',
            'anio_publicacion' => 1800,
        ]);

        $this->assertDatabaseCount('libros', 0);

        $html = (string) $this->get(route('libros.create'))->assertOk()->getContent();

        $this->assertErrorAsociadoAlCampo($html, 'isbn', 'El ISBN debe tener exactamente 13 dígitos, sin guiones ni espacios.');
        $this->assertErrorAsociadoAlCampo($html, 'titulo', 'El campo Título es obligatorio.');
        $this->assertErrorAsociadoAlCampo($html, 'autor', 'El campo Autor es obligatorio.');
        $this->assertErrorAsociadoAlCampo($html, 'anio_publicacion', 'El año de publicación debe estar entre 1900 y '.$anio.'.');
        $this->assertErrorAsociadoAlCampo($html, 'ejemplares_totales', 'El campo Número de ejemplares debe ser un número entero mayor o igual que 1.');
        $this->assertErrorAsociadoAlCampo($html, 'categoria_id', 'Debe seleccionar una categoría válida.');
        $this->assertStringContainsString('value="Editorial conservada"', $html);
    }

    public function test_corregir_un_solo_campo_conserva_los_demas_valores(): void
    {
        $this->from(route('libros.create'))->post('/libros', [
            'isbn' => '123456789012',
            'titulo' => 'Título conservado',
            'autor' => '',
            'editorial' => 'Editorial conservada',
            'anio_publicacion' => 1800,
            'ejemplares_totales' => 2,
            'categoria_id' => Categoria::factory()->create()->id,
        ])->assertSessionHasErrors(['isbn', 'autor', 'anio_publicacion']);

        $this->from(route('libros.create'))->post('/libros', [
            'isbn' => '9788437604947',
            'titulo' => 'Título conservado',
            'autor' => '',
            'editorial' => 'Editorial conservada',
            'anio_publicacion' => 1800,
            'ejemplares_totales' => 2,
            'categoria_id' => Categoria::query()->first()->id,
        ])->assertSessionHasErrors(['autor', 'anio_publicacion'])
            ->assertSessionHasInput('titulo', 'Título conservado')
            ->assertSessionHasInput('editorial', 'Editorial conservada')
            ->assertSessionHasInput('ejemplares_totales', 2);

        $this->assertDatabaseCount('libros', 0);
    }

    public function test_la_edicion_de_libro_muestra_los_errores_y_conserva_los_valores(): void
    {
        $libro = Libro::factory()->create();
        $anio = now()->year;

        $respuesta = $this->from(route('libros.edit', $libro))->put(route('libros.update', $libro), [
            'isbn' => '123456789012',
            'titulo' => $libro->titulo,
            'autor' => $libro->autor,
            'editorial' => $libro->editorial,
            'anio_publicacion' => 1800,
            'ejemplares_totales' => $libro->ejemplares_totales,
            'categoria_id' => $libro->categoria_id,
        ]);

        $respuesta->assertRedirect(route('libros.edit', $libro));
        $respuesta->assertSessionHasErrors([
            'isbn' => 'El ISBN debe tener exactamente 13 dígitos, sin guiones ni espacios.',
            'anio_publicacion' => 'El año de publicación debe estar entre 1900 y '.$anio.'.',
        ]);
        $respuesta->assertSessionHasInput('titulo', $libro->titulo);

        $html = (string) $this->get(route('libros.edit', $libro))->assertOk()->getContent();

        $this->assertErrorAsociadoAlCampo($html, 'isbn', 'El ISBN debe tener exactamente 13 dígitos, sin guiones ni espacios.');
        $this->assertErrorAsociadoAlCampo($html, 'anio_publicacion', 'El año de publicación debe estar entre 1900 y '.$anio.'.');
    }

    public function test_la_categoria_vacia_y_demasiado_larga_muestra_el_error_junto_al_campo(): void
    {
        $respuesta = $this->from(route('categorias.create'))->post('/categorias', ['nombre' => '']);

        $respuesta->assertRedirect(route('categorias.create'));
        $respuesta->assertSessionHasErrors(['nombre' => 'El campo Nombre es obligatorio.']);

        $nombreLargo = str_repeat('a', 101);

        $respuesta = $this->from(route('categorias.create'))->post('/categorias', ['nombre' => $nombreLargo]);

        $respuesta->assertSessionHasErrors(['nombre' => 'El nombre no puede superar los 100 caracteres.']);
        $respuesta->assertSessionHasInput('nombre', $nombreLargo);
        $this->assertDatabaseCount('categorias', 0);

        $html = (string) $this->get(route('categorias.create'))->assertOk()->getContent();

        $this->assertErrorAsociadoAlCampo($html, 'nombre', 'El nombre no puede superar los 100 caracteres.');
    }

    public function test_el_registro_de_prestamo_muestra_todos_los_errores_y_conserva_el_libro(): void
    {
        $libro = Libro::factory()->create();

        $respuesta = $this->from(route('prestamos.create'))->post('/prestamos', [
            'libro_id' => $libro->id,
            'nombre_estudiante' => '',
            'carnet_estudiante' => '',
            'fecha_prestamo' => '2026-01-10',
            'fecha_devolucion_prevista' => '2026-01-01',
        ]);

        $respuesta->assertRedirect(route('prestamos.create'));
        $respuesta->assertSessionHasErrors([
            'nombre_estudiante' => 'El campo nombre del estudiante es obligatorio.',
            'carnet_estudiante' => 'El campo carné es obligatorio.',
            'fecha_devolucion_prevista' => 'La devolución prevista debe ser posterior a la fecha de préstamo.',
        ]);
        $respuesta->assertSessionHasInput('libro_id', $libro->id);
        $respuesta->assertSessionHasInput('fecha_prestamo', '2026-01-10');

        $this->assertDatabaseCount('prestamos', 0);

        $html = (string) $this->get(route('prestamos.create'))->assertOk()->getContent();

        $this->assertErrorAsociadoAlCampo($html, 'nombre_estudiante', 'El campo nombre del estudiante es obligatorio.');
        $this->assertErrorAsociadoAlCampo($html, 'carnet_estudiante', 'El campo carné es obligatorio.');
        $this->assertErrorAsociadoAlCampo($html, 'fecha_devolucion_prevista', 'La devolución prevista debe ser posterior a la fecha de préstamo.');
        $this->assertStringContainsString('value="'.$libro->id.'" selected', $html);
    }

    public function test_la_devolucion_rechaza_la_fecha_vacia_y_la_anterior_al_prestamo(): void
    {
        $prestamo = Prestamo::factory()->activo()->create(['fecha_prestamo' => '2026-01-10']);

        $respuesta = $this->from(route('prestamos.form-devolucion', $prestamo))
            ->post(route('prestamos.devolver', $prestamo), ['fecha_devolucion_real' => '']);

        $respuesta->assertSessionHasErrors([
            'fecha_devolucion_real' => 'La fecha de devolución real es obligatoria.',
        ]);

        $respuesta = $this->from(route('prestamos.form-devolucion', $prestamo))
            ->post(route('prestamos.devolver', $prestamo), ['fecha_devolucion_real' => '2026-01-01']);

        $respuesta->assertSessionHasErrors([
            'fecha_devolucion_real' => 'La fecha de devolución real no puede ser anterior a la fecha de préstamo.',
        ]);
        $respuesta->assertSessionHasInput('fecha_devolucion_real', '2026-01-01');

        $this->assertDatabaseHas('prestamos', ['id' => $prestamo->id, 'fecha_devolucion_real' => null]);

        $html = (string) $this->get(route('prestamos.form-devolucion', $prestamo))->assertOk()->getContent();

        $this->assertErrorAsociadoAlCampo($html, 'fecha_devolucion_real', 'La fecha de devolución real no puede ser anterior a la fecha de préstamo.');
    }

    public function test_la_portada_rechaza_un_archivo_no_imagen_y_conserva_el_resto_de_los_campos(): void
    {
        $libro = Libro::factory()->create();
        $datos = $this->datosValidosLibro($libro);
        $datos['portada'] = UploadedFile::fake()->create('portada.txt', 10, 'text/plain');

        $respuesta = $this->from(route('libros.edit', $libro))
            ->put(route('libros.update', $libro), $datos);

        $respuesta->assertRedirect(route('libros.edit', $libro));
        $respuesta->assertSessionHasErrors([
            'portada' => 'La portada debe ser una imagen JPG, PNG o WebP.',
        ]);
        $respuesta->assertSessionHasInput('titulo', $libro->titulo);

        $this->assertDatabaseHas('libros', ['id' => $libro->id, 'portada' => null]);

        $html = (string) $this->get(route('libros.edit', $libro))->assertOk()->getContent();

        $this->assertErrorAsociadoAlCampo($html, 'portada', 'La portada debe ser una imagen JPG, PNG o WebP.');
    }

    public function test_la_busqueda_recorta_el_texto_y_conserva_el_valor_en_el_campo(): void
    {
        $respuesta = $this->get(route('catalogo.index', ['q' => '  Cervantes  ', 'categoria' => '999']));

        $respuesta->assertOk();
        $respuesta->assertSee('value="Cervantes"', false);
        $respuesta->assertSee('id="q"', false);
        $respuesta->assertSee('id="categoria"', false);
    }
}
