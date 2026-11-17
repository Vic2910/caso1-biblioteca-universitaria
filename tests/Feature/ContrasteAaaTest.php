<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ContrasteAaaTest extends TestCase
{
    private const MINIMO_AAA = 7.0;

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function paresDeTexto(): array
    {
        return [
            'texto sobre blanco' => ['--color-texto', '#ffffff'],
            'texto sobre fondo' => ['--color-texto', '--color-fondo'],
            'primario sobre blanco' => ['--color-primario', '#ffffff'],
            'primario oscuro sobre blanco' => ['--color-primario-oscuro', '#ffffff'],
            'secundario sobre blanco' => ['--color-secundario', '#ffffff'],
            'secundario sobre fondo' => ['--color-secundario', '--color-fondo'],
            'exito sobre blanco' => ['--color-exito', '#ffffff'],
            'peligro sobre blanco' => ['--color-peligro', '#ffffff'],
            'aviso sobre blanco' => ['--color-aviso', '#ffffff'],
            'acento sobre blanco' => ['--color-acento', '#ffffff'],
            'blanco sobre primario' => ['#ffffff', '--color-primario'],
            'blanco sobre primario oscuro' => ['#ffffff', '--color-primario-oscuro'],
            'blanco sobre peligro' => ['#ffffff', '--color-peligro'],
        ];
    }

    #[DataProvider('paresDeTexto')]
    public function test_el_texto_alcanza_el_contraste_aaa_de_siete_a_uno(string $texto, string $fondo): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $colorTexto = $this->resolverColor($texto, $css);
        $colorFondo = $this->resolverColor($fondo, $css);

        $this->assertGreaterThanOrEqual(
            self::MINIMO_AAA,
            $this->contraste($colorTexto, $colorFondo),
            "El par {$texto} sobre {$fondo} debe alcanzar 7:1 (FR-002)"
        );
    }

    private function resolverColor(string $referencia, string $css): string
    {
        if (str_starts_with($referencia, '#')) {
            return $referencia;
        }

        $nombre = preg_quote($referencia, '/');

        if (preg_match('/'.$nombre.':\s*(#[0-9a-fA-F]{6});/', $css, $coincidencias) !== 1) {
            $this->fail("No se encuentra el token {$referencia} en resources/css/app.css");
        }

        return $coincidencias[1];
    }

    private function contraste(string $primero, string $segundo): float
    {
        $luminanciaA = $this->luminancia($primero);
        $luminanciaB = $this->luminancia($segundo);

        return (max($luminanciaA, $luminanciaB) + 0.05) / (min($luminanciaA, $luminanciaB) + 0.05);
    }

    private function luminancia(string $hex): float
    {
        $canales = array_map(
            fn (string $parte): float => hexdec($parte) / 255,
            str_split(ltrim($hex, '#'), 2)
        );

        $lineales = array_map(
            fn (float $c): float => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4,
            $canales
        );

        return 0.2126 * $lineales[0] + 0.7152 * $lineales[1] + 0.0722 * $lineales[2];
    }
}
