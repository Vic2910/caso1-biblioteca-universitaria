<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Libro;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Contrato de la barra de búsqueda en HTML: formulario GET, campo `q`,
 * desplegable de categorías, botón de envío oculto, enlace «Limpiar»,
 * referencia al script de debounce y colación de `editorial`
 * (specs/006-busqueda-catalogo, D-030/D-032, contracts/ui-structure.md;
 * escenarios V-007 y V-010).
 */
class BusquedaInterfazTest extends TestCase
{
    use RefreshDatabase;

    private const PLACEHOLDER = 'Buscar por título, autor, editorial o ISBN';

    /**
     * Crea un documento XPath del HTML de la respuesta para las comprobaciones
     * estructurales de esta suite.
     */
    private function xpathDe(string $html): DOMXPath
    {
        $documento = new DOMDocument;
        $documento->loadHTML($html, LIBXML_NOERROR);

        return new DOMXPath($documento);
    }

    /**
     * @return list<array{valor: string, texto: string}>
     */
    private function opcionesDeCategoria(string $html): array
    {
        $xpath = $this->xpathDe($html);

        $opciones = [];
        foreach ($xpath->query('//form[@id="busqueda"]//select[@name="categoria"]/option') as $nodo) {
            assert($nodo instanceof DOMElement);

            $opciones[] = [
                'valor' => $nodo->getAttribute('value'),
                'texto' => trim($nodo->textContent),
            ];
        }

        return $opciones;
    }

    public function test_el_listado_incluye_un_formulario_get_hacia_el_catalogo_sin_pagina(): void
    {
        Libro::factory()->create();

        $html = $this->get('/catalogo')->assertOk()->getContent();
        $xpath = $this->xpathDe($html);

        $formularios = $xpath->query('//form[@id="busqueda"]');
        $this->assertSame(1, $formularios->length, 'Debe existir exactamente un formulario de búsqueda con id="busqueda"');

        $formulario = $formularios->item(0);
        assert($formulario instanceof DOMElement);

        $this->assertSame('get', strtolower($formulario->getAttribute('method')), 'El formulario debe enviar GET');
        $this->assertSame(
            route('catalogo.index'),
            $formulario->getAttribute('action'),
            'El formulario debe apuntar a la ruta existente del catálogo (D-028)'
        );
        $this->assertSame(
            0,
            $xpath->query('.//input[@name="page"]', $formulario)->length,
            'El formulario no debe enviar page: cada criterio reinicia la paginación (D-034)'
        );
    }

    public function test_el_campo_de_busqueda_tiene_etiqueta_placeholder_y_valor_escapado(): void
    {
        Libro::factory()->create();

        $texto = 'a<b & "c"';
        $html = $this->get('/catalogo?q='.rawurlencode($texto))->assertOk()->getContent();
        $xpath = $this->xpathDe($html);

        $etiquetas = $xpath->query('//label[@for="q"]');
        $this->assertSame(1, $etiquetas->length, 'El campo debe tener etiqueta asociada');
        $this->assertSame('Buscar', trim($etiquetas->item(0)->textContent), 'La etiqueta debe ser «Buscar» (user-messages §3)');

        $campos = $xpath->query('//form[@id="busqueda"]//input[@name="q"]');
        $this->assertSame(1, $campos->length, 'Debe existir un único campo de texto q');

        $campo = $campos->item(0);
        assert($campo instanceof DOMElement);

        $this->assertSame(self::PLACEHOLDER, $campo->getAttribute('placeholder'));
        $this->assertSame(
            $texto,
            $campo->getAttribute('value'),
            'El valor rebotado debe conservar el texto original (el DOM lo decodifica tras escaparlo)'
        );

        $this->assertStringContainsString('value="a&lt;b &amp; &quot;c&quot;"', $html, 'El HTML debe llevar el valor escapado (Principio IV)');
        $this->assertStringNotContainsString('value="a<b', $html, 'El texto del usuario jamás debe renderizarse sin escapar');
    }

    public function test_el_desplegable_lista_las_categorias_de_la_base_ordenadas_alfabeticamente(): void
    {
        // Creadas deliberadamente fuera de orden alfabético. El libro se da
        // de alta en una categoría ya creada para que la base no reciba una
        // cuarta categoría que falsearía el listado exacto de opciones.
        $zoologia = Categoria::factory()->create(['nombre' => 'Zoología']);
        $arte = Categoria::factory()->create(['nombre' => 'Arte y diseño']);
        $matematicas = Categoria::factory()->create(['nombre' => 'Matemáticas']);
        Libro::factory()->create(['categoria_id' => $matematicas->id]);

        $html = $this->get('/catalogo')->assertOk()->getContent();
        $opciones = $this->opcionesDeCategoria($html);

        $this->assertSame(
            ['', (string) $arte->id, (string) $matematicas->id, (string) $zoologia->id],
            array_column($opciones, 'valor'),
            'Una opción por categoría de la base, valor = id, ordenadas por nombre'
        );
        $this->assertSame(
            ['Todas las categorías', 'Arte y diseño', 'Matemáticas', 'Zoología'],
            array_column($opciones, 'texto'),
            'La primera opción debe ser «Todas las categorías» con valor vacío'
        );
    }

    public function test_la_categoria_activa_aparece_marcada_en_el_desplegable(): void
    {
        $activa = Categoria::factory()->create(['nombre' => 'Ciencias exactas']);
        Categoria::factory()->create(['nombre' => 'Historia']);
        Libro::factory()->create(['categoria_id' => $activa->id]);

        $html = $this->get('/catalogo?categoria='.$activa->id)->assertOk()->getContent();
        $xpath = $this->xpathDe($html);

        $marcadas = $xpath->query('//form[@id="busqueda"]//select[@name="categoria"]/option[@selected]');
        $this->assertSame(1, $marcadas->length, 'Debe marcarse exactamente una opción');

        $marcada = $marcadas->item(0);
        assert($marcada instanceof DOMElement);

        $this->assertSame(
            (string) $activa->id,
            $marcada->getAttribute('value'),
            'La categoría aplicada debe verse marcada desde el servidor (FR-004)'
        );
    }

    public function test_el_boton_de_envio_existe_oculto_y_sin_atributo_hidden(): void
    {
        Libro::factory()->create();

        $html = $this->get('/catalogo')->assertOk()->getContent();
        $xpath = $this->xpathDe($html);

        $botones = $xpath->query('//form[@id="busqueda"]//button[@type="submit"]');
        $this->assertSame(1, $botones->length, 'Debe existir un botón de envío para que «Enter» envíe el formulario');

        $boton = $botones->item(0);
        assert($boton instanceof DOMElement);

        $this->assertFalse(
            $boton->hasAttribute('hidden'),
            'No debe usar el atributo hidden: se oculta con CSS para conservar la semántica accesible (T014)'
        );
        $clases = preg_split('/\s+/', trim($boton->getAttribute('class'))) ?: [];
        $this->assertTrue(
            in_array('envio-oculto', $clases, true),
            'El botón debe llevar la clase que lo oculta visualmente'
        );
        $this->assertStringContainsString(
            '.envio-oculto',
            $html,
            'La regla CSS que oculta el botón debe definirse en la propia vista'
        );
    }

    public function test_el_enlace_limpiar_apunta_al_catalogo_sin_parametros(): void
    {
        Libro::factory()->create();

        $html = $this->get('/catalogo?q=zzzznoexiste')->assertOk()->getContent();
        $xpath = $this->xpathDe($html);

        $enlaces = $xpath->query('//a[normalize-space(text())="Limpiar"]');
        $this->assertSame(1, $enlaces->length, 'Con criterio activo debe mostrarse el enlace «Limpiar»');

        $enlace = $enlaces->item(0);
        assert($enlace instanceof DOMElement);

        $this->assertSame(
            route('catalogo.index'),
            $enlace->getAttribute('href'),
            '«Limpiar» debe apuntar a catalogo.index sin parámetros (FR-006)'
        );
    }

    public function test_el_script_de_debounce_se_referencia_en_el_listado(): void
    {
        Libro::factory()->create();

        $html = $this->get('/catalogo')->assertOk()->getContent();
        $xpath = $this->xpathDe($html);

        $this->assertStringContainsString('js/busqueda-catalogo.js', $html);

        $scripts = $xpath->query('//script[contains(@src, "busqueda-catalogo.js")]');
        $this->assertSame(1, $scripts->length, 'El script debe referenciarse con una etiqueta script en la página');
    }

    public function test_la_colacion_de_editorial_es_insensible_a_mayusculas_y_acentos(): void
    {
        $columna = DB::selectOne("SHOW FULL COLUMNS FROM libros WHERE Field = 'editorial'");

        $this->assertNotNull($columna, 'La columna editorial debe existir');
        $this->assertContains(
            $columna->Collation,
            ['utf8mb4_0900_ai_ci', 'utf8mb4_uca1400_ai_ci', 'utf8mb4_unicode_520_ci', 'utf8mb4_unicode_ci'],
            'Debe fijarse una colación insensible a mayúsculas y acentos (D-030; en MariaDB, la variante utf8mb4_unicode_520_ci)'
        );
    }
}
