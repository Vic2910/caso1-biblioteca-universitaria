<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Libro;
use App\Models\Prestamo;
use Illuminate\Database\Seeder;

/**
 * Datos de prueba de la Fase 1 (FR-004, SC-002, research D-005):
 * exactamente 3 categorías, al menos 15 libros y exactamente 3 préstamos
 * (activo, devuelto, vencido), preservando el invariante
 * ejemplares_disponibles = ejemplares_totales − préstamos activos.
 */
class CatalogoSeeder extends Seeder
{
    /**
     * Categorías sembradas (nombres únicos).
     *
     * @var list<string>
     */
    private const CATEGORIAS = [
        'Literatura universal',
        'Ciencias exactas',
        'Historia y sociedad',
    ];

    /**
     * Libros de prueba: título, autor, editorial, año, categoría (índice), ejemplares.
     *
     * @var list<array{titulo: string, autor: string, editorial: string, anio: int, categoria: int, ejemplares: int}>
     */
    private const LIBROS = [
        ['titulo' => 'Cien años de soledad', 'autor' => 'Gabriel García Márquez', 'editorial' => 'Planeta', 'anio' => 1967, 'categoria' => 0, 'ejemplares' => 4],
        ['titulo' => 'La casa de los espíritus', 'autor' => 'Isabel Allende', 'editorial' => 'Debolsillo', 'anio' => 1982, 'categoria' => 0, 'ejemplares' => 3],
        ['titulo' => 'Ficciones', 'autor' => 'Jorge Luis Borges', 'editorial' => 'Emecé Editores', 'anio' => 1944, 'categoria' => 0, 'ejemplares' => 2],
        ['titulo' => 'Rayuela', 'autor' => 'Julio Cortázar', 'editorial' => 'Alfaguara', 'anio' => 1963, 'categoria' => 0, 'ejemplares' => 3],
        ['titulo' => 'Pedro Páramo', 'autor' => 'Juan Rulfo', 'editorial' => 'Fondo de Cultura Económica', 'anio' => 1955, 'categoria' => 0, 'ejemplares' => 2],
        ['titulo' => 'El año del pensamiento mágico', 'autor' => 'Joan Didion', 'editorial' => 'Anagrama', 'anio' => 2005, 'categoria' => 0, 'ejemplares' => 2],
        ['titulo' => 'Introducción al cálculo diferencial', 'autor' => 'James Stewart', 'editorial' => 'Cengage Learning', 'anio' => 2015, 'categoria' => 1, 'ejemplares' => 5],
        ['titulo' => 'Álgebra lineal y sus aplicaciones', 'autor' => 'David Lay', 'editorial' => 'Pearson', 'anio' => 2012, 'categoria' => 1, 'ejemplares' => 4],
        ['titulo' => 'Física universitaria', 'autor' => 'Hugh Young', 'editorial' => 'Pearson', 'anio' => 2019, 'categoria' => 1, 'ejemplares' => 6],
        ['titulo' => 'Cálculo infinitesimal', 'autor' => 'Luis Lózon', 'editorial' => 'Centro de Estudios Ramón Areces', 'anio' => 2000, 'categoria' => 1, 'ejemplares' => 3],
        ['titulo' => 'Química general', 'autor' => 'Raymond Chang', 'editorial' => 'McGraw-Hill', 'anio' => 2013, 'categoria' => 1, 'ejemplares' => 4],
        ['titulo' => 'El siglo de las luces', 'autor' => 'Alejo Carpentier', 'editorial' => 'Alfaguara', 'anio' => 1962, 'categoria' => 2, 'ejemplares' => 2],
        ['titulo' => 'La guerra del fin del mundo', 'autor' => 'Mario Vargas Llosa', 'editorial' => 'Alfaguara', 'anio' => 1981, 'categoria' => 2, 'ejemplares' => 3],
        ['titulo' => 'Historia de dos ciudades', 'autor' => 'Charles Dickens', 'editorial' => 'Editorial Bruguera', 'anio' => 1859, 'categoria' => 2, 'ejemplares' => 2],
        ['titulo' => 'El nombre de la rosa', 'autor' => 'Umberto Eco', 'editorial' => 'Lumen', 'anio' => 1980, 'categoria' => 2, 'ejemplares' => 3],
        ['titulo' => 'Sapiens: de animales a dioses', 'autor' => 'Yuval Noah Harari', 'editorial' => 'Debolsillo', 'anio' => 2011, 'categoria' => 2, 'ejemplares' => 4],
    ];

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $categorias = $this->sembrarCategorias();
        $libros = $this->sembrarLibros($categorias);
        $this->sembrarPrestamos($libros);
    }

    /**
     * Crea las 3 categorías con nombres únicos.
     *
     * @return list<Categoria>
     */
    private function sembrarCategorias(): array
    {
        return collect(self::CATEGORIAS)
            ->map(fn (string $nombre): Categoria => Categoria::create(['nombre' => $nombre]))
            ->values()
            ->all();
    }

    /**
     * Crea los libros respetando el orden de claves foráneas.
     *
     * @param  list<Categoria>  $categorias
     * @return list<Libro>
     */
    private function sembrarLibros(array $categorias): array
    {
        $libros = [];

        foreach (self::LIBROS as $datos) {
            $libros[] = Libro::create([
                'isbn' => $this->isbnParaTitulo($datos['titulo']),
                'titulo' => $datos['titulo'],
                'autor' => $datos['autor'],
                'editorial' => $datos['editorial'],
                'anio_publicacion' => $datos['anio'],
                'ejemplares_totales' => $datos['ejemplares'],
                'ejemplares_disponibles' => $datos['ejemplares'],
                'categoria_id' => $categorias[$datos['categoria']]->id,
                'portada' => null,
            ]);
        }

        return $libros;
    }

    /**
     * Crea los 3 préstamos (activo, devuelto, vencido) y ajusta la
     * disponibilidad del libro con préstamo activo para cumplir el invariante.
     *
     * @param  list<Libro>  $libros
     */
    private function sembrarPrestamos(array $libros): void
    {
        // Préstamo activo: descuenta un ejemplar disponible de su libro.
        $libroActivo = $libros[0];
        Prestamo::create([
            'libro_id' => $libroActivo->id,
            'nombre_estudiante' => 'Ana Martínez López',
            'carnet_estudiante' => '202400123',
            'fecha_prestamo' => now()->subDays(3)->format('Y-m-d'),
            'fecha_devolucion_prevista' => now()->addDays(11)->format('Y-m-d'),
            'fecha_devolucion_real' => null,
            'estado' => Prestamo::ESTADO_ACTIVO,
        ]);
        $libroActivo->update([
            'ejemplares_disponibles' => $libroActivo->ejemplares_totales - 1,
        ]);

        // Préstamo devuelto: no afecta a la disponibilidad actual.
        Prestamo::create([
            'libro_id' => $libros[1]->id,
            'nombre_estudiante' => 'Carlos Rodríguez Pérez',
            'carnet_estudiante' => '202300456',
            'fecha_prestamo' => now()->subDays(30)->format('Y-m-d'),
            'fecha_devolucion_prevista' => now()->subDays(16)->format('Y-m-d'),
            'fecha_devolucion_real' => now()->subDays(17)->format('Y-m-d'),
            'estado' => Prestamo::ESTADO_DEVUELTO,
        ]);

        // Préstamo vencido: sin devolución real, prevista pasada.
        Prestamo::create([
            'libro_id' => $libros[2]->id,
            'nombre_estudiante' => 'Lucía Fernández Gómez',
            'carnet_estudiante' => '202200789',
            'fecha_prestamo' => now()->subDays(40)->format('Y-m-d'),
            'fecha_devolucion_prevista' => now()->subDays(26)->format('Y-m-d'),
            'fecha_devolucion_real' => null,
            'estado' => Prestamo::ESTADO_VENCIDO,
        ]);
    }

    /**
     * Genera un ISBN único de 13 dígitos determinista a partir del título.
     */
    private function isbnParaTitulo(string $titulo): string
    {
        return str_pad((string) (abs(crc32($titulo)) % 10000000000000), 13, '0', STR_PAD_LEFT);
    }
}
