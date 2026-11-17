# Tasks: Consulta del catálogo y detalle de libros (002-consulta-catalogo)

**Input**: Design documents from `/specs/002-consulta-catalogo/`

**Prerequisites**: plan.md, spec.md, research.md (D-010…D-017), data-model.md, contracts/ (http-endpoints.md, ui-structure.md, user-messages.md), quickstart.md

**Tests**: Sí — la estrategia de pruebas está decidida explícitamente en el diseño (D-016, plan.md §Testing: ~5 suites PHPUnit). Las suites de cada historia se escriben ANTES de su implementación y deben fallar antes de implementar.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2)
- Include exact file paths in descriptions

## ⚠️ Prerequisito heredado (001-entorno-datos)

Este repositorio contiene actualmente solo la documentación de `specs/`. La aplicación Laravel 13, los modelos `Libro`/`Categoría`, las migraciones iniciales, el layout `resources/views/layouts/app.blade.php`, las factories, el seeder (≥ 15 libros, ≥ 3 categorías, todas las portadas `NULL`) y la configuración de PHPUnit sobre MySQL de prueba provienen de `001-entorno-datos`. Si `001` aún no está implementado, completar sus tareas primero: esta funcionalidad reutiliza su entorno sin duplicarlo (plan.md §Structure Decision).

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Verificar/crear el arranque del proyecto Laravel heredado de `001` y la rama de trabajo

- [X] T001 Crear la rama `feature/consulta-catalogo` y verificar que el entorno de `001-entorno-datos` funciona: `composer install`, `.env` con credenciales MySQL de prueba (fuera del repo, contrato `001 contracts/environment-variables.md`), `php artisan key:generate`, `php artisan migrate --seed` y `php artisan test` en verde antes de empezar (requisitos previos de `quickstart.md`)

**Checkpoint**: Entorno de `001` funcionando; se puede empezar la fundación

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Componentes compartidos por AMBAS historias de usuario; MUST be complete before ANY user story can be implemented

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [X] T002 Crear la migración de colación `database/migrations/*_alter_libros_orden_collation.php` (posterior a la migración de creación de `libros` de `001`) que fija la colación **`utf8mb4_0900_ai_ci`** sobre las columnas `libros.titulo` y `libros.autor` mediante `Schema::table(...)->change()`, y verificar con `SHOW FULL COLUMNS` (D-012, data-model.md §Cambios de esquema; nota de portabilidad MariaDB: `utf8mb4_uca1400_ai_ci` o `utf8mb4_unicode_520_ci`)
- [X] T003 [P] Crear la imagen predeterminada única del proyecto `public/images/libro-sin-portada.svg` (D-014: SVG sin dependencias ni licencias, escalable, peso mínimo)
- [X] T004 [P] Crear el parcial `resources/views/partials/portada.blade.php` (D-014, ui-structure.md §5): si `portada = NULL` → `src` = `asset('images/libro-sin-portada.svg')` con `alt` «Sin portada»; si `portada` tiene valor → `src` = `Storage::disk('public')->url($portada)` **más** atributo `onerror` que sustituya la fuente por la imagen predeterminada; `alt` «Portada de {título}»; todo el texto renderizado con `{{ }}` (escapado, Principio IV)
- [X] T005 [P] Crear la vista de error `resources/views/errors/404.blade.php` (D-013, user-messages.md §2): extiende `resources/views/layouts/app.blade.php`, muestra `{{ $exception->getMessage() }}` escapado con texto por defecto «Página no encontrada» si el mensaje está vacío, y un enlace «Volver al catálogo» hacia `route('catalogo.index')` (la ruta se registra en T011; la vista solo se renderiza al producirse un 404)
- [X] T006 [P] Añadir a la navegación de `resources/views/layouts/app.blade.php` el enlace «Catálogo» → `route('catalogo.index')` distinguiendo visualmente la página activa, con el CSS propio del proyecto (D-015, ui-structure.md §1; cabecera, navegación y pie idénticos en todas las vistas)

**Checkpoint**: Fundación lista — las dos historias pueden empezar (US1 debe ir antes que US2 por la ruta `catalogo.index`, ver Dependencies)

---

## Phase 3: User Story 1 - Consultar el catálogo (Priority: P1) 🎯 MVP

**Goal**: Listado paginado en `GET /catalogo` con 10 libros por página, orden determinista A–Z por título ignorando tildes y mayúsculas (desempate autor → ISBN), tarjetas completas y clicables con portada o imagen predeterminada, estado vacío y 404 para página fuera de rango

**Independent Test**: Con los datos de prueba de `001` cargados (≥ 15 libros), abrir `http://127.0.0.1:8000/catalogo` y navegar por todas las páginas hasta localizar libros de distintas categorías (escenarios V-001, V-002, V-003 parcial, V-006, V-007 de quickstart.md)

### Tests for User Story 1 (FIRST — must FAIL before implementation) ⚠️

> **NOTE: Write these tests FIRST, ensure they FAIL before implementation** (D-016: PHPUnit con `RefreshDatabase`, comando `php artisan test`; el caso vacío se prueba con la base migrada **sin** sembrar)

- [X] T007 [P] [US1] Crear `tests/Feature/CatalogoIndexTest.php`: listado 200, campos de la tarjeta en orden (portada/predeterminada, título, autor, año, categoría), tarjeta entera enlazando a `catalogo.show`, texto accesible con título y autor, estado vacío «No hay libros registrados aún» sin tarjetas ni controles de paginación (V-001, V-006; user-messages.md §1 y §3)
- [X] T008 [P] [US1] Crear `tests/Feature/CatalogoOrdenYPaginacionTest.php`: 10 libros por página, recorrido completo de todas las páginas hasta el último libro (SC-001), última página incompleta sin posiciones vacías, orden «algebra abstracta» < «Álgebra lineal» (ignora tilde y mayúscula), «Biología» < «Zoología», títulos iguales desempatados por autor A–Z y después por ISBN, orden idéntico entre consultas (V-001, V-002; data-model.md §Regla de ordenación)
- [X] T009 [P] [US1] Crear `tests/Feature/CatalogoErroresTest.php`: HTTP 404 con vista personalizada, mensaje «Página no válida» y enlace «Volver al catálogo» para `page=999`, `page=0`, `page=abc`, `page=-3`, `page=1.5`, y `page=2` con catálogo vacío (V-007; http-endpoints.md §Parámetros y §Respuesta 404)

### Implementation for User Story 1

- [X] T010 [US1] Implementar `CatalogoController@index` en `app/Http/Controllers/CatalogoController.php` (D-010, D-011, D-012, D-013, D-017): validar el parámetro crudo `page` (si está presente y no vacío debe cumplir `^\d+$` y ser ≥ 1; si no → `abort(404, 'Página no válida')`); consultar `Libro::with('categoria')->orderBy('titulo')->orderBy('autor')->orderBy('isbn')->paginate(10)->withQueryString()`; si `currentPage() > lastPage()` → `abort(404, 'Página no válida')`; con `total() === 0` y página 1 pasar el estado vacío al listado; sin SQL concatenado con entradas del usuario (Principio IV)
- [X] T011 [US1] Registrar en `routes/web.php` la ruta `GET /catalogo` → `CatalogoController@index` con nombre `catalogo.index`, sin middleware de autenticación (D-010, http-endpoints.md §Rutas); `/` sigue siendo la página de bienvenida de `001`
- [X] T012 [US1] Crear `resources/views/catalogo/index.blade.php` (extiende `layouts/app`, D-015): título de sección «Catálogo de libros»; si el total es 0, mensaje «No hay libros registrados aún» en la zona del listado sin tarjetas ni paginación; en caso contrario, tarjetas con —en este orden— portada vía `@include('partials.portada')`, título, autor, año de publicación y nombre de la categoría; **toda la tarjeta es un enlace clicable** a `route('catalogo.show', $libro)`; paginación con `->links('pagination.catalogo')`; textos escapados con `{{ }}` (ui-structure.md §2, user-messages.md §4)
- [X] T013 [P] [US1] Crear `resources/views/pagination/catalogo.blade.php` (D-011): vista propia de paginación con «Anterior», «Siguiente» y números de página (ventana con primera/última cuando el total es grande), sin Tailwind ni Bootstrap, usando solo el CSS propio del proyecto (Principio V)
- [X] T014 [P] [US1] Añadir el CSS propio de tarjetas, rejilla del listado y controles de paginación al stylesheet propio del proyecto heredado de `001` (p. ej. `public/css/app.css`, ajustar la ruta al definido en `001`), manteniendo el diseño profesional, coherente, adaptable y claro de los tres perfiles (Principio V; sin frameworks CSS, D-011)

**Checkpoint**: At this point, User Story 1 should be fully functional and testable independently (V-001, V-002, V-006, V-007)

---

## Phase 4: User Story 2 - Consultar la ficha de un libro (Priority: P1)

**Goal**: Ficha de detalle en `GET /catalogo/{id}` con todos los campos de `Libro` (portada, título, autor, editorial, año de publicación, ISBN, total de ejemplares, ejemplares disponibles) y su categoría; 404 personalizado para libro inexistente

**Independent Test**: Seleccionar un libro conocido en el listado y contrastar su ficha con los datos de prueba cargados; abrir `/catalogo/999999` y verificar el 404 (escenarios V-004, V-005 de quickstart.md)

### Tests for User Story 2 (FIRST — must FAIL before implementation) ⚠️

> **NOTE: Write these tests FIRST, ensure they FAIL before implementation** (D-016)

- [X] T015 [P] [US2] Crear `tests/Feature/CatalogoDetalleTest.php`: HTTP 200 con los ocho campos etiquetados en español (título, autor, editorial, año de publicación, ISBN, total de ejemplares, ejemplares disponibles, categoría) coincidentes con los datos de prueba (SC-002, V-004); portada predeterminada `libro-sin-portada.svg` cuando `portada = NULL` (V-003); enlace «Volver al catálogo»; HTTP 404 con vista personalizada, mensaje «Libro no encontrado» y enlace «Volver al catálogo» para un id inexistente (V-005, user-messages.md §2)

### Implementation for User Story 2

- [X] T016 [US2] Implementar `CatalogoController@show` en `app/Http/Controllers/CatalogoController.php` (D-010, D-013, D-017): `Libro::with('categoria')->find($id)`; si es `null` → `abort(404, 'Libro no encontrado')` (búsqueda explícita, no `findOrFail()`, para controlar el mensaje en español); pasar el libro a la vista; sin SQL concatenado (Principio IV)
- [X] T017 [US2] Registrar en `routes/web.php` la ruta `GET /catalogo/{id}` → `CatalogoController@show` con nombre `catalogo.show` (D-010, http-endpoints.md §Rutas; `{id}` es el id numérico de `libros`, entero ≥ 1)
- [X] T018 [US2] Crear `resources/views/catalogo/show.blade.php` (extiende `layouts/app`, D-015): título de sección «Ficha del libro»; portada vía el mismo parcial `@include('partials.portada')` del listado (misma imagen predeterminada, FR-003/SC-003); campos etiquetados en español y en este orden: Título (`titulo`), Autor (`autor`), Editorial (`editorial`), Año de publicación (`anio_publicacion`), ISBN (`isbn`), Total de ejemplares (`ejemplares_totales`), Ejemplares disponibles (`ejemplares_disponibles`), Categoría (`nombre` de la categoría); enlace «Volver al catálogo» a `route('catalogo.index')`; textos escapados con `{{ }}` (ui-structure.md §3, user-messages.md §4; solo lectura, sin lógica de préstamos — fuera de alcance: `004`)
- [X] T019 [US2] Crear `tests/Feature/CatalogoLayoutTest.php` (D-016): verificar que listado (`/catalogo`), detalle (`/catalogo/{id}`) y error 404 (`/catalogo/999999`) comparten cabecera, navegación (con enlace «Catálogo») y pie idénticos, reconocibles como una única aplicación (FR-004, SC-004, V-008)

**Checkpoint**: At this point, User Stories 1 AND 2 should both work independently

---

## Phase 5: Polish & Cross-Cutting Concerns

**Purpose**: Validación end-to-end y documentación transversales

- [X] T020 [P] Ejecutar la validación manual completa de `quickstart.md` (escenarios V-001 a V-008) contra la app con `php artisan serve` y registrar el resultado; verificar los criterios SC-001 a SC-004
- [X] T021 Ejecutar `php artisan test` con la suite completa en verde, incluidas las cinco suites de `002` (`CatalogoIndexTest`, `CatalogoDetalleTest`, `CatalogoOrdenYPaginacionTest`, `CatalogoErroresTest`, `CatalogoLayoutTest`) junto con las de `001` (V-009)
- [X] T022 [P] Actualizar el README (documentación oficial, G9) con la navegación del catálogo: rutas `GET /catalogo` y `GET /catalogo/{id}`, enlace «Catálogo» en la navegación común, y las capturas de listado y detalle
- [X] T023 Revisión de seguridad y limpieza (Principio IV): toda salida escapada con `{{ }}` (incluido `$exception` en `errors/404.blade.php`), solo consultas Eloquent con `with('categoria')`, sin SQL concatenado, sin métodos de escritura ni DELETE por GET, mensajes sin trazas SQL, credenciales ni rutas internas (contracts/user-messages.md §5)
- [X] T024 Completar la revisión de `specs/002-consulta-catalogo/checklists/requirements.md` confirmando que cada requisito de `spec.md` (FR-001…FR-005, SC-001…SC-004) tiene evidencia en tests o en la validación del quickstart

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — puede empezar inmediatamente (requiere `001-entorno-datos` implementado)
- **Foundational (Phase 2)**: Depends on Setup completion — BLOCKS all user stories
- **User Story 1 (Phase 3)**: Depends on Foundational (Phase 2)
- **User Story 2 (Phase 4)**: Depends on Foundational (Phase 2) + ruta `catalogo.index` de US1 (T011) para el enlace «Volver al catálogo» y la vista 404 → ejecutar US1 primero (ambas son P1)
- **Polish (Phase 5)**: Depends on US1 + US2

### User Story Dependencies

- **User Story 1 (P1)**: Can start after Foundational (Phase 2) — No dependencies on other stories (usa el parcial de portada T004 y la vista 404 T005 de la fundación)
- **User Story 2 (P1)**: Can start after Foundational (Phase 2) — integra con US1 vía `route('catalogo.index')` (T011); la vista de error 404 y el parcial de portada son compartidos de la fundación

### Within Each User Story

- Tests FIRST (must FAIL) → controlador → rutas → vistas → CSS
- Core implementation before integration (`CatalogoLayoutTest` al final de US2, T019)
- Story complete before moving to next priority

### Parallel Opportunities

- T003, T004, T005 y T006 (fundación) pueden ejecutarse en paralelo entre sí
- T007, T008 y T009 (tests de US1) en paralelo
- T013 y T014 (vista de paginación y CSS) en paralelo tras T010/T012
- T015 (test de US2) puede ejecutarse en paralelo con toda la implementación de US1
- T020 y T022 (validación manual y README) en paralelo
- Con capacidad suficiente: US1 y US2 en paralelo por desarrolladores distintos, resolviendo la dependencia de `catalogo.index` con T011 primero

---

## Parallel Example: User Story 1

```bash
# Launch all tests for User Story 1 together:
Task: "Crear tests/Feature/CatalogoIndexTest.php"
Task: "Crear tests/Feature/CatalogoOrdenYPaginacionTest.php"
Task: "Crear tests/Feature/CatalogoErroresTest.php"

# Launch pagination view + CSS together (after controller/routes/views):
Task: "Crear resources/views/pagination/catalogo.blade.php"
Task: "Añadir CSS propio de tarjetas y paginación al stylesheet del proyecto"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup (verificar entorno de `001`)
2. Complete Phase 2: Foundational (CRITICAL — bloquea ambas historias: colación, imagen predeterminada, parcial de portada, vista 404, enlace de navegación)
3. Complete Phase 3: User Story 1
4. **STOP and VALIDATE**: probar `GET /catalogo` independientemente (V-001, V-002, V-006, V-007 + suites de US1 en verde)
5. Deploy/demo if ready

### Incremental Delivery

1. Setup + Foundational → fundación lista (colación verificable con `SHOW FULL COLUMNS`)
2. Add User Story 1 → Test independently → Deploy/Demo (MVP: catálogo paginado completo)
3. Add User Story 2 → Test independently → Deploy/Demo (ficha de detalle + `CatalogoLayoutTest`)
4. Polish → validación quickstart + README + revisión de seguridad
5. Each story adds value without breaking previous stories

### Parallel Team Strategy

With multiple developers:

1. Team completes Setup + Foundational together (T002 con el resto en paralelo)
2. Once Foundational is done:
   - Developer A: User Story 1 (tests → controlador → vistas → CSS)
   - Developer B: User Story 2 (test T015 en paralelo; implementación al disponer de T011)
3. Stories complete and integrate independently (comparten parcial de portada y vista 404 de la fundación)

---

## Notes

- [P] tasks = different files, no dependencies
- [Story] label maps task to specific user story for traceability
- Tests FIRST: verificar que fallan antes de implementar (D-016)
- Commit after each task or logical group (Conventional Commits, G9)
- Stop at any checkpoint to validate story independently
- Búsqueda y filtro del catálogo quedan fuera de alcance (`006-busqueda-catalogo`); uploads de portadas fuera de alcance (`005-gestion-portadas`)
- Avoid: vague tasks, same-file conflicts, cross-story dependencies that break independence
