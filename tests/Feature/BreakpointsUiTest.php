<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BreakpointsUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_hoja_declara_los_tres_puntos_de_ruptura_de_aceptacion(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('--breakpoint-md: 768px', $css);
        $this->assertStringContainsString('--breakpoint-lg: 1024px', $css);
        $this->assertMatchesRegularExpression('/@media \(min-width: 768px\)/', $css);
        $this->assertMatchesRegularExpression('/@media \(min-width: 1024px\)/', $css);
    }

    public function test_la_hamburguesa_queda_oculta_en_escritorio_y_la_horizontal_en_movil(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertMatchesRegularExpression('/nav\.site-nav ul\.menu-horizontal\s*\{\s*display:\s*none/s', $css, 'Móvil: sin navegación horizontal');
        $this->assertMatchesRegularExpression('/@media \(min-width: 768px\)\s*\{.*nav\.site-nav \.menu-hamburguesa\s*\{\s*display:\s*none/s', $css, 'Escritorio: sin hamburguesa');
    }

    public function test_el_markup_contiene_ambas_navegaciones_para_los_tres_tamanos(): void
    {
        $this->actingAs(User::factory()->create());

        $html = $this->get('/catalogo')->assertOk()->getContent();

        $this->assertStringContainsString('class="menu-horizontal"', $html);
        $this->assertStringContainsString('class="menu-hamburguesa"', $html);
    }

    public function test_la_rejilla_de_accesos_pasa_de_una_a_tres_columnas(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('grid-template-columns: 1fr;', $css, '360 px: una columna');
        $this->assertStringContainsString('repeat(3, minmax(0, 1fr))', $css, '≥ 1024 px: tres columnas');
    }
}
