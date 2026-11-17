<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * V-001 y SC-005: toda ruta de negocio exige sesión y la operación no se
 * ejecuta cuando la petición llega sin autenticar (008, FR-009).
 */
class AuthAccesoTest extends TestCase
{
    use RefreshDatabase;

    protected bool $sesionPorDefecto = false;

    /**
     * @return list<string>
     */
    private function rutasProtegidas(): array
    {
        return [
            route('catalogo.index'),
            route('libros.index'),
            route('categorias.index'),
            route('prestamos.index'),
            route('panel.index'),
        ];
    }

    public function test_las_vistas_protegidas_redirigen_al_login_sin_sesion(): void
    {
        foreach ($this->rutasProtegidas() as $ruta) {
            $this->get($ruta)
                ->assertRedirect(route('login'))
                ->assertSessionHas('error', 'Inicie sesión para continuar.');
        }
    }

    public function test_un_formulario_enviado_sin_sesion_no_crea_registros(): void
    {
        $categoria = Categoria::factory()->create();

        $this->post('/libros', [
            'isbn' => '9788437604947',
            'titulo' => 'Libro sin sesión',
            'autor' => 'Autor',
            'editorial' => 'Editorial',
            'anio_publicacion' => 2000,
            'ejemplares_totales' => 1,
            'categoria_id' => $categoria->id,
        ])->assertRedirect(route('login'));

        $this->assertDatabaseCount('libros', 0);
    }

    public function test_las_vistas_se_procesan_con_sesion_iniciada(): void
    {
        $this->actingAs(User::factory()->create());

        foreach ($this->rutasProtegidas() as $ruta) {
            $this->get($ruta)->assertOk();
        }
    }
}
