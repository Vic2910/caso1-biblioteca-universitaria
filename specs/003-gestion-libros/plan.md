# Implementation Plan: Gestión de libros y categorías (003-gestion-libros)

**Branch**: `003-gestion-libros` | **Date**: 2026-10-26 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/003-gestion-libros/spec.md`

## Summary

Implementar el CRUD de la Fase 2 sobre las vistas de consulta de la Fase 1: crear (`POST /libros`), editar (`GET /libros/{id}/editar` + `PUT`) y eliminar (`DELETE /libros/{id}`) libros desde el listado y la ficha del catálogo, con Form Requests que validan en el servidor ISBN único de 13 dígitos, año 1900–año actual, campos obligatorios y longitudes, y portada opcional JPG/PNG/WebP ≤ 2 MB guardada en Storage público; `ejemplares_disponibles` nunca se captura y se calcula como total menos préstamos activos (fijado al total al crear; el total editable no puede bajar de los préstamos activos); categorías gestionadas desde vista propia (`GET /categorias`) con nombre único ignorando tildes, mayúsculas y espacios sobrantes (colación `utf8mb4_0900_ai_ci` + `trim`), eliminación bloqueada si tienen libros; eliminación de libro bloqueada si tiene préstamos, siempre con confirmación previa y mediante `DELETE`; todos los errores en español junto al campo con `old()` conservando lo introducido.

## Technical Context

**Language/Version**: PHP 8.3–8.5 (heredado de `001` D-001)

**Primary Dependencies**: Laravel 13, Composer 2.x, Blade + CSS propio (sin frameworks CSS, heredado D-011), PHPUnit (D-008/D-016)

**Storage**: MySQL 8.x local (heredado D-002); esquema solo con migraciones — `003` añade una migración de colación en `categorias.nombre` (D-020); portadas en Storage público (`app/public/portadas`, enlace simbólico) (D-021, constitución *Imágenes*)

**Testing**: PHPUnit con `RefreshDatabase` sobre base de datos de prueba MySQL; comando `php artisan test`; ~6 suites de característica (D-025)

**Target Platform**: Aplicación web local (`php artisan serve`, `http://127.0.0.1:8000`), sin autenticación (supuesto de la spec)

**Project Type**: Web application (monolito Laravel/Blade, un único repositorio)

**Performance Goals**: Sin requisitos de tráfico; decenas–cientos de filas; recuentos de préstamos activos por consulta parametrizada en el momento del guardado (D-023)

**Constraints**: Sin servicios de pago; mensajes en español junto al campo y `old()` (Principio II, FR-004); credenciales fuera del repositorio (D-007); eliminaciones solo por `DELETE` con confirmación (Principio IV, FR-005); reemplazo/borrado de portadas y préstamos fuera de alcance (`004`, `005`)

**Scale/Scope**: 9 rutas nuevas (6 de libros, 6 de categorías contando lectura), 2 controladores, 4 Form Requests, 1 migración, ~7 vistas nuevas/ajustadas, ~6 suites de test; alcance solo CRUD — sin registro de préstamos, sin búsqueda/filtro, sin panel

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| # | Gate (constitución v1.0) | Veredicto | Evidencia en el diseño |
|---|---|---|---|
| G1 | **I. Modelo de datos primero**: esquema con migraciones versionadas, modelos con `$fillable`, casts y relaciones | **PASS** | Único cambio: migración de colación `utf8mb4_0900_ai_ci` en `categorias.nombre` (D-020, [data-model.md](data-model.md) §Cambios de esquema); `$fillable` de `Libro`/`Categoria` revisado para aceptar solo campos de formulario (sin `ejemplares_disponibles`); relaciones heredadas de `001` |
| G2 | **II. Validación en el servidor** (Form Requests, `validated()`, errores en español con `old()`) | **PASS** | 4 Form Requests con reglas de FR-003/006/007/008 (D-019); traducción al español con `lang/es/validation.php` y atributos personalizados (D-024); redirección `back()->withErrors()->withInput()` |
| G3 | **III. Reglas de préstamo coherentes** | **PASS** | `ejemplares_disponibles` calculado en el servidor como total − préstamos activos, con lógica en el modelo `Libro` (D-023); el formulario no lo captura (clarificación Q1); el ajuste al prestar/devolver sigue siendo de `004` |
| G4 | **IV. Seguridad y operaciones explícitas** (`@csrf`, `{{ }}`, Eloquent, `DELETE` con confirmación) | **PASS** | Formularios con `@csrf` y `@method('DELETE'|'PUT')`; confirmación JavaScript previa al envío (D-022); Eloquent sin SQL concatenado — la unicidad de categoría usa el índice/colación y `Rule::unique` (D-020); portada validada como archivo, no como ruta (D-021) |
| G5 | **V. Aplicación local e interfaz clara** (Laravel, `php artisan serve`, layout Blade común, CSS propio) | **PASS** | Todas las vistas nuevas/extensidas extienden `layouts/app.blade.php` (D-015); formularios y tablas con CSS propio del proyecto |
| G6 | **Restricciones → Tecnologías**: PHP 8.3+, Laravel 13, Composer 2.x, tablas solo con migraciones | **PASS** | Heredado D-001/D-003; la colación se aplica con `Schema::table(...)->change()` en migración |
| G7 | **Restricciones → Seguridad**: Eloquent/Query Builder, validación en servidor, escapado de salida, credenciales fuera del repo | **PASS** | D-019/D-020/D-024; textos renderizados con `{{ }}`; sin cambios en `.env` |
| G8 | **Restricciones → Imágenes**: Storage con ruta en BD, JPG/PNG/WebP ≤ 2 MB, imagen predeterminada cuando no exista | **PASS** | Subida en creación con validación de constitución y ruta guardada en `libros.portada` (D-021); el parcial `partials/portada` de `002` (D-014) sigue mostrando la predeterminada; reemplazo/borrado → `005` |
| G9 | **Flujo de desarrollo**: rama `feature/`, Conventional Commits, README como documentación oficial | **PASS** | Rama `feature/gestion-libros` (spec); el README documenta CRUD, reglas de negocio y capturas de formularios |

**Gate result**: **0 violaciones** → no se requiere *Complexity Tracking*. Re-evaluado tras Phase 1 (ver abajo).

## Project Structure

### Documentation (this feature)

```text
specs/003-gestion-libros/
├── plan.md              # This file (/speckit.plan command output)
├── research.md          # Phase 0 output — decisiones D-018…D-025, sin NEEDS CLARIFICATION
├── data-model.md        # Phase 1 output — reglas de escritura de Libro/Categoría, disponibilidad, migración de colación
├── quickstart.md        # Phase 1 output — escenarios V-001…V-012
├── contracts/           # Phase 1 output
│   ├── http-endpoints.md      # FR-001/003/005/007/008/009: rutas CRUD, 200/302/404/errores
│   ├── ui-structure.md        # FR-001/004/006: botones de gestión, formularios, confirmación, vista de categorías
│   └── user-messages.md       # FR-004/008/009: textos en español de errores y avisos
├── checklists/requirements.md
└── tasks.md             # Phase 2 output (/speckit.tasks command - NOT created by /speckit.plan)
```

### Source Code (repository root)

```text
app/
├── Http/Controllers/
│   ├── CatalogoController.php    # (002) sin cambios de lógica; el listado y el detalle solo añaden controles en las vistas
│   ├── LibroController.php       # NEW: create, store, edit, update, destroy (D-018)
│   └── CategoriaController.php   # NEW: index, create, store, edit, update, destroy (D-018)
├── Http/Requests/
│   ├── LibroStoreRequest.php     # NEW: reglas de creación (D-019)
│   ├── LibroUpdateRequest.php    # NEW: reglas de edición (total ≥ préstamos activos, D-019/D-023)
│   ├── CategoriaStoreRequest.php # NEW: nombre único normalizado (D-020)
│   └── CategoriaUpdateRequest.php# NEW: unique ignorando el propio registro (D-020)
└── Models/
    ├── Libro.php                 # $fillable revisado (sin ejemplares_disponibles) + helper de disponibilidad (D-023)
    └── Categoria.php             # $fillable revisado; relación libros() ya existente

database/
└── migrations/
    └── *_alter_categorias_nombre_collation.php  # utf8mb4_0900_ai_ci en nombre (D-020)

lang/
└── es/
    └── validation.php            # NEW: mensajes de validación en español + atributos personalizados (D-024)

routes/web.php                    # + POST /libros, GET /libros/crear, GET|PUT /libros/{id}/editar, DELETE /libros/{id}; rutas categorias.* (D-018)

resources/views/
├── layouts/app.blade.php         # (001/002) + enlace «Categorías» en la navegación (D-018)
├── catalogo/
│   ├── index.blade.php           # (002) + botón «Nuevo libro» (D-018)
│   └── show.blade.php            # (002) + botones «Editar» y «Eliminar» con aviso si hay préstamos (D-022)
├── libros/
│   ├── _form.blade.php           # NEW: parcial compartido create/edit con errores junto al campo
│   ├── create.blade.php          # NEW: alta de libro con portada opcional
│   └── edit.blade.php            # NEW: edición (portada existente visible; reemplazo → 005)
├── categorias/
│   ├── index.blade.php           # NEW: listado de categorías con «Nueva categoría», «Editar» y «Eliminar»
│   ├── create.blade.php          # NEW: alta de categoría
│   └── edit.blade.php            # NEW: edición de nombre
└── partials/portada.blade.php    # (002) sin cambios; reutilizado en tarjetas, ficha y formularios

app/
└── public/portadas/              # directorio de Storage público para subidas (D-021); requiere php artisan storage:link

tests/Feature/
├── LibroCreacionTest.php         # alta válida e inválida: ISBN, año, obligatorios, longitudes (V-001, V-003, V-004)
├── LibroEdicionTest.php          # edición válida, total ≥ préstamos activos, disponibilidad recalculada (V-002, V-005)
├── LibroEliminacionTest.php      # confirmación DELETE, bloqueo con préstamos, 404 (V-006, V-007)
├── LibroPortadaTest.php          # JPG/PNG/WebP válidos; formato/tamaño inválidos (V-008)
├── CategoriaGestionTest.php      # crear/editar/eliminar; bloqueo con libros asociados (V-009, V-010)
├── CategoriaUnicidadTest.php     # duplicados ignorando tildes, mayúsculas y espacios (V-011)
└── GestionLayoutTest.php         # layout común en vistas y formularios; conservación de valores con old() (V-012)
```

**Structure Decision**: Monolito Laravel 13 de aplicación única (árbol estándar de Laravel), heredado de `001-entorno-datos`. `003` reutiliza las vistas de consulta de `002` —a las que añade controles de gestión— y crea los controladores, Form Requests, vistas de formulario y migración de colación propios del CRUD; no hay frontend/backend separados.

## Constitution Check (re-evaluación post-Phase 1)

| Gate | Re-veredicto | Nota |
|---|---|---|
| G1–G9 | **PASS** | Los artefactos de Phase 1 (research, data-model, contracts, quickstart) cubren cada gate sin nuevas violaciones; el único cambio de esquema (colación de `categorias.nombre`, D-020) usa migración, como exige el Principio I |
| Clarificaciones 2026-10-26 | Satisfechas | Disponibilidad no capturada (D-023, data-model §Disponibilidad); portada básica en alta con validación constitucional y sin reemplazo (D-021, contracts/ui-structure §4); unicidad de categoría normalizada (D-020, contracts/http-endpoints §Categorías); gestión ampliando vistas de la Fase 1 + vista propia de categorías (D-018, contracts/ui-structure §1); alcance limitado a CRUD, préstamos solo como restricción (D-022/D-023) |
| NEEDS CLARIFICATION restantes | **0** | Todos resueltos en [research.md](research.md) |

**Gate result**: **PASS — 0 violaciones, 0 aclaraciones pendientes.**

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

*Ninguna violación: tabla vacía por diseño.*
