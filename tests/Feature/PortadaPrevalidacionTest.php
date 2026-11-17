<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PortadaPrevalidacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_campo_de_portada_activa_la_pre_validacion_en_el_cliente(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/libros/crear')
            ->assertOk()
            ->assertSee('data-portada', false)
            ->assertSee('type="file"', false);
    }

    public function test_el_servidor_rechaza_una_portada_fuera_de_limites_con_error_en_el_campo(): void
    {
        $this->actingAs(User::factory()->create());

        $archivoGrande = UploadedFile::fake()->create('grande.jpg', 3000, 'image/jpeg');

        $this->post(route('libros.store'), ['portada' => $archivoGrande])
            ->assertSessionHasErrors('portada');
    }

    public function test_el_servidor_rechaza_un_formato_no_permitido(): void
    {
        $this->actingAs(User::factory()->create());

        $pdf = UploadedFile::fake()->create('portada.pdf', 10, 'application/pdf');

        $this->post(route('libros.store'), ['portada' => $pdf])
            ->assertSessionHasErrors('portada');
    }
}
