<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToastExitoTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_mensaje_de_exito_se_anuncia_como_toast_con_cierre_accesible(): void
    {
        $this->actingAs(User::factory()->create());

        $this->withSession(['success' => 'Libro guardado correctamente.'])
            ->get('/libros')
            ->assertOk()
            ->assertSee('data-toast', false)
            ->assertSee('role="status"', false)
            ->assertSee('Cerrar mensaje')
            ->assertSee('Libro guardado correctamente.');
    }

    public function test_los_errores_no_se_ocultan_automaticamente(): void
    {
        $this->actingAs(User::factory()->create());

        $this->withSession(['error' => 'No se pudo completar la operación.'])
            ->get('/libros')
            ->assertOk()
            ->assertSee('role="alert"', false)
            ->assertDontSee('data-toast', false);
    }

    public function test_sin_mensaje_no_aparece_el_toast(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/libros')->assertOk()->assertDontSee('data-toast', false);
    }

    public function test_el_script_de_toast_oculta_el_mensaje_a_los_seis_segundos(): void
    {
        $script = file_get_contents(resource_path('js/toast.js'));

        $this->assertStringContainsString('RETARDO_MS = 6000', $script);
        $this->assertStringContainsString('focusin', $script, 'Se pausa al recibir foco');
    }
}
