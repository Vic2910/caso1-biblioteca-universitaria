# Quickstart: validación de 003-gestion-libros (Gestión de libros y categorías)

**Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md) | **Contracts**: [contracts/http-endpoints.md](contracts/http-endpoints.md), [contracts/ui-structure.md](contracts/ui-structure.md), [contracts/user-messages.md](contracts/user-messages.md)

Guía de validación ejecutable de extremo a extremo. No contiene implementación: los detalles de reglas y textos viven en los contratos y en [data-model.md](data-model.md).

## Prerrequisitos

- PHP 8.3+, Composer 2.x, MySQL 8 local (D-001/D-002).
- Base de desarrollo y base de prueba (`biblioteca_test`) creadas según `.env`/`phpunit.xml` de `001`.

## Puesta en marcha

```bash
composer install
cp .env.example .env            # completar credenciales locales (no se versionan)
php artisan key:generate
php artisan migrate:fresh --seed # esquema completo + datos de 001 (≥15 libros, ≥3 categorías, 3 préstamos)
php artisan storage:link        # enlace simbólico para servir portadas subidas (app/public/portadas)
php artisan serve               # http://127.0.0.1:8000
```

## Pruebas automatizadas

```bash
php artisan test
# o por suite:
php artisan test --filter=LibroCreacionTest
php artisan test --filter=LibroEdicionTest
php artisan test --filter=LibroEliminacionTest
php artisan test --filter=LibroPortadaTest
php artisan test --filter=CategoriaGestionTest
php artisan test --filter=CategoriaUnicidadTest
php artisan test --filter=GestionLayoutTest
```

## Escenarios de validación manual (navegador)

Base: datos sembrados por `migrate:fresh --seed` (15+ libros, 3 categorías, un libro con préstamo activo).

| # | Escenario (historia) | Pasos | Resultado esperado |
|---|---|---|---|
| V-001 | **Alta válida** (US1) | `GET /catalogo` → «Nuevo libro» → completar campos válidos con categoría existente → «Guardar» | `302` a la ficha del libro; «Libro registrado correctamente.»; disponibles = total |
| V-002 | **Edición válida** (US1) | Ficha de un libro → «Editar» → cambiar título y año → «Guardar» | Ficha con los datos actualizados (SC-005) |
| V-003 | **ISBN inválido o duplicado** (US1, SC-002) | En el alta: ISBN «123», «abcdefghijklm», y luego el ISBN de otro libro existente | Error en español junto a ISBN en cada intento; no se crea el libro; los demás campos conservan lo escrito |
| V-004 | **Año fuera de rango y campos obligatorios** (US1, SC-004) | Año 1899 y año actual+1; después, enviar el formulario vacío | Errores junto a cada campo; nada se guarda |
| V-005 | **Total incoherente al editar** (FR-007, SC-006) | Editar un libro con préstamos activos intentando bajar el total por debajo de ellos | Error junto a «Número de ejemplares»; el libro no cambia; después subir el total válido → disponibles recalculado |
| V-006 | **Eliminación con confirmación** (US2, SC-003) | Ficha de un libro **sin** préstamos → «Eliminar» → aceptar el `confirm()` | Se envía `DELETE`; «Libro eliminado correctamente.»; desaparece del listado |
| V-007 | **Eliminación cancelada o bloqueada** (US2, SC-003) | (a) Cancelar el `confirm()`. (b) Intentar eliminar un libro **con** préstamos (aceptando) | (a) No se envía petición; nada cambia. (b) Nada se elimina; aviso en español; el libro permanece en el catálogo |
| V-008 | **Portada válida e inválida** (US1, SC-008) | (a) Subir un JPG ≤ 2 MB. (b) Subir un `.txt` y luego un PNG > 2 MB | (a) Guarda y la ficha muestra la imagen. (b) Error junto a la portada; no se guarda; resto de valores conservados |
| V-009 | **CRUD de categorías** (US3, SC-007) | `GET /categorias` → «Nueva categoría» → crear → editar su nombre → comprobar aparición en el selector de libros | Categoría creada/editada visible en listado y selector |
| V-010 | **Eliminación de categoría** (US3) | (a) Eliminar una categoría sin libros (aceptar). (b) Intentar eliminar una categoría con libros | (a) Se elimina y desaparece de los selectores. (b) Bloqueada con mensaje en español |
| V-011 | **Unicidad normalizada** (US3, SC-007) | Con «Matemáticas» existente: crear «matematicas », «MATEMATICAS», «Matematicas» | Los tres rechazados con «Ese nombre de categoría ya existe.» junto al campo; también al editar otra categoría |
| V-012 | **Layout y conservación de datos** (SC-004) | Recorrer listado, ficha, alta, edición y vista de categorías; forzar un error y observar el formulario | Cabecera/navegación/pie idénticos en todas; mensajes junto a cada campo; valores introducidos intactos (`old()`) |

## Comprobaciones transversales

- **Nunca eliminar por GET** (SC-003): `GET /libros/1/delete` o cualquier `GET` a las rutas de borrado → HTTP 405; nada se elimina.
- **Disponibilidad derivada** (SC-006): el HTML de alta/edición **no contiene** un campo `ejemplares_disponibles`; crear un libro con total N deja disponibles = N.
- **Seguridad** (Principio IV): los formularios incluyen `@csrf`; los de borrado/edición incluyen `@method`; la salida de nombres de libros/categorías se escapa (probar con un título que contenga `<script>`).
- **404 en español**: abrir `/libros/99999/editar` y `/categorias/99999/editar` → vista `errors/404.blade.php` con layout común.

## Criterios de aceptación cubiertos

SC-001…SC-008 de la especificación se corresponden con V-001…V-012 más las suites automatizadas de [research.md](research.md) D-025; el alcance se limita al CRUD — los préstamos solo intervienen como restricción (FR-007/FR-009), y búsqueda/filtro (`006`), panel (`007`) y reemplazo de portadas (`005`) quedan fuera.
