<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Libro;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cubre los escenarios V-001 (paginación) y V-002 (orden determinista)
 * de specs/002-consulta-catalogo/quickstart.md (FR-005, SC-001).
 */
class CatalogoOrdenYPaginacionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Extrae las tarjetas del listado desde el HTML de la respuesta.
     *
     * @return list<array{href: string, titulo: string}>
     */
    private function tarjetasDe(string $ruta): array
    {
        $respuesta = $this->get($ruta)->assertOk();

        $documento = new DOMDocument;
        $documento->loadHTML($respuesta->getContent(), LIBXML_NOERROR);
        $xpath = new DOMXPath($documento);

        $tarjetas = [];
        foreach ($xpath->query('//a[contains(@class, "tarjeta-libro")]') as $nodo) {
            $titulo = $xpath->query('.//h3', $nodo)->item(0);

            $tarjetas[] = [
                'href' => $nodo->getAttribute('href'),
                'titulo' => $titulo !== null ? trim($titulo->textContent) : '',
            ];
        }

        return $tarjetas;
    }

    public function test_se_muestran_diez_libros_por_pagina_y_la_ultima_es_incompleta(): void
    {
        Libro::factory()->count(25)->create();

        $this->assertCount(10, $this->tarjetasDe('/catalogo'));
        $this->assertCount(10, $this->tarjetasDe('/catalogo?page=2'));
        $this->assertCount(5, $this->tarjetasDe('/catalogo?page=3'), 'La última página no debe tener posiciones vacías');
    }

    public function test_el_recorrido_completo_visita_todos_los_libros_en_orden_alphabetico(): void
    {
        // SC-001: 15 libros creados en orden no alfabético.
        $titulosCreados = [
            'Zoología general',
            'Biología celular',
            'Álgebra lineal',
            'algebra abstracta',
            'Matemáticas discretas',
            'Historia antigua',
            'Física cuántica',
            'Química orgánica',
            'Geología dinámica',
            'Ética aplicada',
            'Lógica computacional',
            'Estadística descriptiva',
            'Botánica tropical',
            'Cálculo numérico',
            'Antropología urbana',
        ];

        foreach ($titulosCreados as $titulo) {
            Libro::factory()->create(['titulo' => $titulo]);
        }

        $esperado = [
            'algebra abstracta',
            'Álgebra lineal',
            'Antropología urbana',
            'Biología celular',
            'Botánica tropical',
            'Cálculo numérico',
            'Estadística descriptiva',
            'Ética aplicada',
            'Física cuántica',
            'Geología dinámica',
            'Historia antigua',
            'Lógica computacional',
            'Matemáticas discretas',
            'Química orgánica',
            'Zoología general',
        ];

        $recorrido = array_merge(
            array_column($this->tarjetasDe('/catalogo'), 'titulo'),
            array_column($this->tarjetasDe('/catalogo?page=2'), 'titulo')
        );

        $this->assertCount(15, $recorrido, 'El recorrido completo debe visitar los 15 libros (SC-001)');
        $this->assertSame($esperado, $recorrido, 'Todos los libros deben visitarse en orden alfabético determinista');
    }

    public function test_el_orden_ignora_tildes_y_mayusculas(): void
    {
        Libro::factory()->create(['titulo' => 'Zoología']);
        Libro::factory()->create(['titulo' => 'Biología']);
        Libro::factory()->create(['titulo' => 'Álgebra lineal']);
        Libro::factory()->create(['titulo' => 'algebra abstracta']);

        $orden = array_column($this->tarjetasDe('/catalogo'), 'titulo');

        $this->assertSame(
            ['algebra abstracta', 'Álgebra lineal', 'Biología', 'Zoología'],
            $orden,
            'El orden debe ignorar tildes y mayúsculas'
        );
    }

    public function test_los_titulos_iguales_se_desempatan_por_autor_y_luego_por_isbn(): void
    {
        $segundoPorIsbn = Libro::factory()->create([
            'titulo' => 'Introducción',
            'autor' => 'Alonso',
            'isbn' => '9999999999992',
        ]);
        $primeroPorAutor = Libro::factory()->create([
            'titulo' => 'Introducción',
            'autor' => 'Alonso',
            'isbn' => '1111111111119',
        ]);
        $ultimoPorAutor = Libro::factory()->create([
            'titulo' => 'Introducción',
            'autor' => 'Zúñiga',
            'isbn' => '0000000000005',
        ]);

        $recorrido = array_column($this->tarjetasDe('/catalogo'), 'href');

        $this->assertSame(
            [
                route('catalogo.show', $primeroPorAutor),
                route('catalogo.show', $segundoPorIsbn),
                route('catalogo.show', $ultimoPorAutor),
            ],
            $recorrido,
            'El desempate debe ser autor A–Z y después ISBN ascendente'
        );
    }

    public function test_el_orden_es_identico_entre_consultas(): void
    {
        Libro::factory()->count(12)->create();

        $primeraConsulta = array_column($this->tarjetasDe('/catalogo'), 'href');
        $segundaConsulta = array_column($this->tarjetasDe('/catalogo'), 'href');

        $this->assertSame($primeraConsulta, $segundaConsulta, 'El orden debe ser determinista entre consultas');
    }
}
