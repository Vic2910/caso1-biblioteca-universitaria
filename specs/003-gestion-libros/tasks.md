---

description: "Task list for 003-gestion-libros (Gestión de libros y categorías)"
---

# Tasks: Gestión de libros y categorías (003-gestion-libros)

**Input**: Design documents from `/specs/003-gestion-libros/`

**Prerequisites**: plan.md (required), spec.md (required for user stories), research.md, data-model.md, contracts/, quickstart.md

**Prerequisite features**: This feature builds on `001-entorno-datos` (schema, models, factories, seeders, layout, connection handling — its `tasks.md` exists) and `002-consulta-catalogo` (catalog listing/detail views, `errors/404.blade.php`, `partials/portada.blade.php`, collation pattern). T001 verifies they are implemented and green before anything else.

**Tests**: Tests ARE requested — `plan.md` §Source Code names the seven feature test files explicitly and `research.md` D-025 fixes the suite strategy; `quickstart.md` requires `php artisan test` green. Test tasks are therefore included per user story.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

## Path Conventions

- **Single project**: `app/`, `database/`, `resources/`, `routes/`, `lang/`, `tests/` at repository root (monolito Laravel 13, per `plan.md` §Source Code)
- All paths below are relative to the repository root unless absolute

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Verify the inherited base and prepare the working environment per `plan.md` and `research.md` D-018…D-025

- [ ] T001 Verify prerequisite features are implemented and green: run `php artisan test` (all `001`/`002` suites pass), `php artisan migrate:fresh --seed` (≥15 libros, ≥3 categorías, 3 préstamos), and confirm the inherited files exist — `resources/views/layouts/app.blade.php`, `resources/views/catalogo/index.blade.php`, `resources/views/catalogo/show.blade.php`, `resources/views/partials/portada.blade.php`, `resources/views/errors/404.blade.php`, `app/Models/Libro.php`, `app/Models/Categoria.php`, `app/Models/Prestamo.php` (plan.md §Source Code; if any is missing, implement `001-entorno-datos`/`002-consulta-catalogo` first)
- [ ] T002 [P] Create the working branch `feature/gestion-libros` from the branch containing `001`+`002` (spec.md §Feature Branch, plan.md G9 «Flujo de desarrollo»)
- [ ] T003 [P] Document `php artisan storage:link` in `README.md` (sección de instalación) as the command that serves uploaded covers from `app/public/portadas` via the public disk (D-021, constitution *Imágenes*; needed by US1 T013)

**Checkpoint**: Base verified, branch ready — foundational work can begin

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core infrastructure that MUST be complete before ANY user story can be implemented

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [ ] T004 Create `lang/es/validation.php` with the official Laravel Spanish translation plus `attributes` in Spanish (ISBN, título, autor, editorial, año de publicación, número de ejemplares, categoría, portada, nombre) and `custom` messages for: ISBN `regex` («El ISBN debe tener exactamente 13 dígitos, sin guiones ni espacios.»), ISBN `unique` («El ISBN ya está registrado en otro libro.»), portada `mimes` («La portada debe ser un archivo JPG, PNG o WebP.»), portada `max` («La portada no debe superar los 2 MB.») — verbatim `contracts/user-messages.md` §1-2 (D-024, FR-004)
- [ ] T005 [P] Configure the Spanish locale so validation messages render in Spanish: set `'locale' => 'es'` in `config/app.php` (or `App::setLocale('es')` at boot in `bootstrap/app.php`) (D-024)
- [ ] T006 [P] Extend `resources/views/layouts/app.blade.php` with a flash-message block that renders `session('success')` as a success notice and the global error bag / `session('error')` as an error notice, in Spanish, escaped with `{{ }}` — every management redirect of `contracts/http-endpoints.md` and `contracts/user-messages.md` §3-4 relies on it (Principio IV, D-022)

**Checkpoint**: Foundation ready — user story implementation can now begin in parallel

---

## Phase 3: User Story 1 - Registrar y editar libros (Priority: P1) 🎯 MVP

**Goal**: The user can register a new book (with optional cover) from the catalog listing and edit its data, with server-side validation (unique 13-digit ISBN, year 1900–current, required fields, lengths, JPG/PNG/WebP ≤ 2 MB cover) showing Spanish errors next to each field and preserving entered values; `ejemplares_disponibles` is never captured and is calculated by the server.

**Independent Test**: Register a valid book, open its detail, edit it and verify the detail shows exactly what was sent; force validation errors and check Spanish messages next to fields with `old()` values intact (quickstart V-001…V-005, V-008).

### Tests for User Story 1 (requested by plan.md §Source Code and research D-025) ⚠️

> **NOTE: Write these tests FIRST, ensure they FAIL before implementation**

- [ ] T007 [P] [US1] Create `tests/Feature/LibroCreacionTest.php` covering quickstart V-001/V-003/V-004 and D-025: valid creation redirects to the book detail with «Libro registrado correctamente.» and `ejemplares_disponibles = ejemplares_totales`; rejected cases with Spanish error next to the field — duplicated ISBN, ISBN not matching `regex:/^[0-9]{13}$/`, year 1899 and current year + 1 (1900 and current year accepted), missing required fields (ISBN, título, autor, editorial, año, categoría, número de ejemplares), titulo/autor shorter than 2 chars, nonexistent `categoria_id`; the created book detail is reachable (FR-003, FR-006, SC-002)
- [ ] T008 [P] [US1] Create `tests/Feature/LibroEdicionTest.php` covering quickstart V-002/V-005 and D-025: valid edit updates the detail (SC-005); editing without sending a file keeps the existing `portada` value untouched; `ejemplares_totales` below the book's active loans is rejected next to the field with «El número de ejemplares no puede ser inferior a los préstamos activos ({n}).» and nothing changes; a valid higher total recalculates `ejemplares_disponibles = ejemplares_totales − préstamos activos`; the edit HTML contains no `ejemplares_disponibles` field (FR-004, FR-007, SC-006)
- [ ] T009 [P] [US1] Create `tests/Feature/LibroPortadaTest.php` covering quickstart V-008 and D-025: JPG, PNG and WebP files ≤ 2 MB are stored via the public disk in `portadas/`, their path saved in `libros.portada` and the image shown in the detail; a `.txt` file and an image > 2 MB are rejected with the Spanish error next to the portada field and no book is created while the other entered values are preserved (FR-006, SC-008)

### Implementation for User Story 1

- [ ] T010 [US1] Revise `app/Models/Libro.php`: set `$fillable = ['isbn','titulo','autor','editorial','anio_publicacion','ejemplares_totales','categoria_id','portada']` — **without** `ejemplares_disponibles` — add `prestamosActivos(): int` (`prestamos()->where('estado', 'activo')->count()`) and a `recalcularDisponibilidad()` invoked from the `saving` event so that `ejemplares_disponibles = ejemplares_totales − préstamos activos` on every write path (data-model.md §Disponibilidad, D-023, FR-007, Principio III)
- [ ] T011 [P] [US1] Create `app/Http/Requests/LibroStoreRequest.php` with rules (D-019, contracts/http-endpoints §Cuerpos de formulario): `isbn` `required|string|regex:/^[0-9]{13}$|unique:libros,isbn`; `titulo`, `autor` `required|string|min:2|max:255`; `editorial` `required|string|max:255`; `anio_publicacion` `required|integer|min:1900|max:<now()->year>`; `ejemplares_totales` `required|integer|min:1`; `categoria_id` `required|integer|exists:categorias,id`; `portada` `nullable|file|mimes:jpg,jpeg,png,webp|max:2048`; NO rule or field for `ejemplares_disponibles` (clarification Q1)
- [ ] T012 [P] [US1] Create `app/Http/Requests/LibroUpdateRequest.php` with the same rules as T011 except: `isbn` uses `Rule::unique('libros','isbn')->ignore($this->route('libro')->id)`; `portada` keeps `nullable|file|mimes:jpg,jpeg,png,webp|max:2048` but the controller only touches it when a file is sent (D-021); `ejemplares_totales` adds the dynamic lower bound `min:<max(1, préstamos activos del libro)>` computed from `$this->route('libro')->prestamos()->where('estado','activo')->count()` with the custom Spanish message «El número de ejemplares no puede ser inferior a los préstamos activos ({n}).» (D-019/D-023, FR-007)
- [ ] T013 [US1] Create `app/Http/Controllers/LibroController.php` with `create`, `store`, `edit`, `update` (destroy arrives in US2): the controller uses `$request->validated()` and never `$request->all()` (Principio II); `store` persists the validated data, and when `portada` was uploaded saves `$request->file('portada')->store('portadas', 'public')` into `libros.portada` (D-021), then redirects to the book detail (`libros.show` of `002`) with flash «Libro registrado correctamente.»; `edit`/`update` resolve the book or respond **404** with «Libro no encontrado» (mechanism D-013, `resources/views/errors/404.blade.php`); `update` skips the `portada` key when no file was sent (existing cover preserved) and redirects with «Libro actualizado correctamente.»; on validation failure Laravel redirects back with errors + `old()` (FR-004) (depends on T010, T011, T012)
- [ ] T014 [US1] Register the `libros.*` write routes in `routes/web.php`: `GET /libros/crear` → `libros.create`, `POST /libros` → `libros.store`, `GET /libros/{id}/editar` → `libros.edit`, `PUT /libros/{id}` → `libros.update` (D-018; do NOT add the DELETE route yet — that is US2; the query routes of `002` stay untouched) (depends on T013)
- [ ] T015 [P] [US1] Create `resources/views/libros/_form.blade.php` shared partial with the field order of contracts/ui-structure §4 — ISBN (`type="text" inputmode="numeric"` to preserve leading zeros), título, autor, editorial, año de publicación, número de ejemplares, categoría selector listing existing categorías, portada — plus `@csrf` (and `@method('PUT')` when editing), Spanish labels, an `@error` block **immediately below each field** (FR-004/SC-004), `old()` values, and «Guardar»/«Cancelar» buttons; NO `ejemplares_disponibles` field anywhere; the portada input (`type="file" accept="image/jpeg,image/png,image/webp"`, optional) renders only in the create context
- [ ] T016 [P] [US1] Create `resources/views/libros/create.blade.php` extending `resources/views/layouts/app.blade.php`, form `method="post" action="{{ route('libros.store') }}"` with `enctype="multipart/form-data"` including the `libros/_form` partial (D-018, Principio V)
- [ ] T017 [P] [US1] Create `resources/views/libros/edit.blade.php` extending `resources/views/layouts/app.blade.php`, form `method="post" action="{{ route('libros.update', $libro) }}"` with `@method('PUT')` and `enctype="multipart/form-data"` including the `libros/_form` partial; shows the current cover via `partials/portada` with the help text that file replacement arrives with `005-gestion-portadas` and NO file input in this phase (D-021, contracts/ui-structure §4)
- [ ] T018 [US1] Extend `resources/views/catalogo/index.blade.php` with a «Nuevo libro» button pointing to `route('libros.create')`, preserving everything from `002` and showing the button also in the empty state (so the first book can be created) (contracts/ui-structure §2, FR-001)
- [ ] T019 [US1] Extend `resources/views/catalogo/show.blade.php` with a management zone containing an «Editar» button pointing to `route('libros.edit', $libro)`; preserve the full detail view of `002` including `partials/portada` (the «Eliminar» form arrives with US2) (contracts/ui-structure §3, FR-001)
- [ ] T020 [US1] Validate User Story 1 end to end per `quickstart.md`: run `php artisan test --filter=LibroCreacionTest`, `--filter=LibroEdicionTest`, `--filter=LibroPortadaTest` green; execute manual scenarios V-001…V-005 and V-008 with `php artisan serve`; verify the create/edit HTML contains no `ejemplares_disponibles` input and that creating a book with total N leaves disponibles = N (SC-006)

**Checkpoint**: At this point, User Story 1 should be fully functional and testable independently (MVP)

---

## Phase 4: User Story 2 - Eliminar un libro (Priority: P2)

**Goal**: The user can delete a book from its detail view with a previous JavaScript confirmation and a `DELETE` request; deletion is blocked with a Spanish message when the book has registered loans, and cancelling the confirmation sends no request at all.

**Independent Test**: Request and confirm the deletion of a book without associated loans and check it disappears from the listing; cancel the confirmation and verify nothing changes; attempt to delete a book with loans and verify it is blocked with the Spanish notice (quickstart V-006/V-007).

### Tests for User Story 2 (requested by plan.md §Source Code and research D-025) ⚠️

> **NOTE: Write this test FIRST, ensure it FAILS before implementation**

- [ ] T021 [P] [US2] Create `tests/Feature/LibroEliminacionTest.php` covering quickstart V-006/V-007 and D-025: `DELETE /libros/{id}` on a book without loans removes it and redirects to the catalog with «Libro eliminado correctamente.»; a book with registered loans is NOT deleted and the redirect carries the Spanish error «El libro tiene préstamos registrados y no puede eliminarse.»; the detail view contains a delete form with `@csrf`, `@method('DELETE')` and an `onsubmit` `confirm()` whose text matches «¿Eliminar el libro «{título}»? Esta acción no se puede deshacer.»; a `GET` request to the delete route returns **HTTP 405** and deletes nothing (SC-003); a nonexistent book in `destroy` returns **404** (FR-005, FR-009)

### Implementation for User Story 2

- [ ] T022 [US2] Add `destroy` to `app/Http/Controllers/LibroController.php` implementing the three layers of D-022: (1) pre-check `$libro->prestamos()->exists()` → do NOT attempt deletion, redirect to the catalog with error «El libro tiene préstamos registrados y no puede eliminarse.»; (2) catch `QueryException` (race with the `RESTRICT` FK) → redirect with «No se pudo eliminar el registro. Inténtelo de nuevo.»; (3) on success `$libro->delete()` and redirect to the catalog with flash «Libro eliminado correctamente.»; nonexistent book → 404 «Libro no encontrado» (FR-005, FR-009, contracts/http-endpoints §Eliminaciones) (depends on T021, T013)
- [ ] T023 [US2] Register `DELETE /libros/{id}` → `libros.destroy` in `routes/web.php` — registered **only** as DELETE so any GET to the URL yields HTTP 405 and GET never deletes (FR-005, SC-003, D-018) (depends on T022)
- [ ] T024 [US2] Extend `resources/views/catalogo/show.blade.php` with the delete form: `<form method="post" action="{{ route('libros.destroy', $libro) }}">` with `@csrf`, `@method('DELETE')`, button «Eliminar» and `onsubmit="return confirm('¿Eliminar el libro «{{ $libro->titulo }}»? Esta acción no se puede deshacer.')"``; when the book has loans, show next to the button the Spanish notice «Este libro tiene préstamos registrados y no puede eliminarse.» (information *before* confirming) (D-022, contracts/ui-structure §3, FR-009)
- [ ] T025 [US2] Validate User Story 2 per quickstart V-006/V-007: `php artisan test --filter=LibroEliminacionTest` green; manually delete a seeded book without loans (accepting the confirmation), cancel another confirmation (no request sent), attempt to delete the seeded book with the active loan (blocked with Spanish message); `GET /libros/1/delete` returns 405 and deletes nothing

**Checkpoint**: At this point, User Stories 1 AND 2 should both work independently

---

## Phase 5: User Story 3 - Gestionar categorías (Priority: P3)

**Goal**: The user can create, edit and delete categories from their own listing view; deletion is blocked with a Spanish message when the category has associated books; the category name is unique ignoring accents, case and surplus spaces (DB collation + `trim`), with the Spanish error next to the field on create and edit.

**Independent Test**: Create a category, verify it in the listing and in the book form selector, edit it, and check a deleted category (without books) disappears from the selectors; try «matematicas » while «Matemáticas» exists and verify rejection (quickstart V-009…V-011).

### Tests for User Story 3 (requested by plan.md §Source Code and research D-025) ⚠️

> **NOTE: Write these tests FIRST, ensure they FAIL before implementation**

- [ ] T026 [P] [US3] Create `tests/Feature/CategoriaGestionTest.php` covering quickstart V-009/V-010 and D-025: creating a valid category redirects to the listing with «Categoría creada correctamente.» and it appears in `GET /categorias` and in the book form selector; editing renames it everywhere; deleting a category without books (confirming) removes it and it disappears from the selectors with «Categoría eliminada correctamente.»; deleting a category with associated books is blocked and keeps it with the Spanish error «La categoría tiene libros asociados y no puede eliminarse.»; a `GET` to the delete route returns 405; nonexistent category in edit/update/destroy → 404 «Categoría no encontrada» (FR-008, SC-007)
- [ ] T027 [P] [US3] Create `tests/Feature/CategoriaUnicidadTest.php` covering quickstart V-011 and D-025: with «Matemáticas» existing, creating «matematicas », «MATEMATICAS» and «Matematicas» is rejected every time with «Ese nombre de categoría ya existe.» next to the field and no category is created; editing *another* category to any of those variants is also rejected, while editing «Matemáticas» keeping its own name succeeds (`ignore` del propio registro); the stored name is the trimmed original form («Matemáticas»); empty name after `trim` → «El campo Nombre es obligatorio.» (FR-008, SC-007)

### Implementation for User Story 3

- [ ] T028 [US3] Create migration `database/migrations/*_alter_categorias_nombre_collation.php`: `Schema::table('categorias', fn ($table) => ...)` applying collation `utf8mb4_0900_ai_ci` to `nombre` via `->change()`, so the existing `categorias_nombre_unique` index compares ignoring case and accents even under concurrency («Matemáticas» vs «matematicas» collide at DB level); timestamp must be **after** the `001`/`002` migrations; note in a comment the MariaDB/older-MySQL variants `utf8mb4_uca1400_ai_ci` (10.11+) or `utf8mb4_unicode_520_ci` (D-020, data-model.md §Cambios de esquema; this is the ONLY schema change of the feature)
- [ ] T029 [P] [US3] Create `app/Http/Requests/CategoriaStoreRequest.php` with `prepareForValidation()` applying `trim()` to `nombre` (surplus spaces the NO PAD collation cannot catch) and rules `required|string|max:100` plus `Rule::unique('categorias','nombre')` — message «Ese nombre de categoría ya existe.» (D-020, FR-008)
- [ ] T030 [P] [US3] Create `app/Http/Requests/CategoriaUpdateRequest.php` with the same `trim()` preparation and rules, with `Rule::unique('categorias','nombre')->ignore($this->route('categoria')->id)` so editing a category keeping its own name succeeds (D-020, FR-008)
- [ ] T031 [US3] Create `app/Http/Controllers/CategoriaController.php` with `index` (listing showing each category's associated book count as reference), `create`, `store`, `edit`, `update` and `destroy`: all writes via `$request->validated()`; nonexistent category → 404 «Categoría no encontrada»; `destroy` replicates the three layers of D-022 — pre-check `$categoria->libros()->exists()` → keep the category and redirect to the listing with «La categoría tiene libros asociados y no puede eliminarse.»; catch `QueryException` → «No se pudo eliminar el registro. Inténtelo de nuevo.»; success → flash «Categoría creada/actualizada/eliminada correctamente.» per operation (contracts/user-messages §3-4, FR-008) (depends on T029, T030)
- [ ] T032 [US3] Register the `categorias.*` routes in `routes/web.php` under prefix `/categorias` with the REST shape of D-018/contracts/http-endpoints §Rutas de categorías: `GET /categorias` (index), `GET /categorias/crear` (create), `POST /categorias` (store), `GET /categorias/{id}/editar` (edit), `PUT /categorias/{id}` (update), `DELETE /categorias/{id}` (destroy, DELETE only) (depends on T031)
- [ ] T033 [P] [US3] Create `resources/views/categorias/index.blade.php` extending the shared layout: table of categories (nombre, cantidad de libros asociados as reference) with per-row «Editar» link and «Eliminar» form (`@csrf`, `@method('DELETE')`, `onsubmit` `confirm('¿Eliminar la categoría «{{ $categoria->nombre }}»? Esta acción no se puede deshacer.')`), header button «Nueva categoría» → `route('categorias.create')`, and flash/error rendering (contracts/ui-structure §5, D-022)
- [ ] T034 [P] [US3] Create `resources/views/categorias/create.blade.php` extending the shared layout: form to `route('categorias.store')` with a single «Nombre» field (≤ 100 chars), Spanish label, `@error` below the field, `old()` preservation and «Guardar»/«Cancelar» (back to the listing) buttons (contracts/ui-structure §5)
- [ ] T035 [P] [US3] Create `resources/views/categorias/edit.blade.php` extending the shared layout: form to `route('categorias.update', $categoria)` with `@method('PUT')`, same single «Nombre» field with `@error` and `old()` pre-filled with the current value, and «Guardar»/«Cancelar» buttons (contracts/ui-structure §5)
- [ ] T036 [US3] Extend the navigation of `resources/views/layouts/app.blade.php` with the «Categorías» link → `route('categorias.index')` next to «Catálogo» (D-018, contracts/ui-structure §1; «Nuevo libro» does NOT go in the navigation)
- [ ] T037 [US3] Validate User Story 3 end to end per `quickstart.md`: run `php artisan migrate` (collation migration applies cleanly) and `php artisan test --filter=CategoriaGestionTest --filter=CategoriaUnicidadTest` green; execute manual scenarios V-009…V-011; verify a newly created category appears in the book form selector and a deleted one disappears from it

**Checkpoint**: All user stories should now be independently functional

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Improvements that affect multiple user stories

- [ ] T038 [P] Create `tests/Feature/GestionLayoutTest.php` covering quickstart V-012 and D-025: every management view (`/libros/crear`, `/libros/{id}/editar`, `/categorias`, `/categorias/crear`, `/categorias/{id}/editar`, plus the extended catalog listing/detail) renders inside `layouts/app.blade.php` with the same header/navigation/footer; all forms contain `@csrf` and the edit/delete forms contain `@method`; forcing a validation error re-renders with each message next to its field and `old()` values intact (SC-004)
- [ ] T039 [P] Execute every scenario V-001 to V-012 of `specs/003-gestion-libros/quickstart.md` plus the «Comprobaciones transversales» (GET to delete routes → 405; no `ejemplares_disponibles` input in any form; `@csrf`/`@method` present; a title containing `<script>` is escaped with `{{ }}`; `/libros/99999/editar` and `/categorias/99999/editar` render the Spanish 404 with the shared layout) and record the results (SC-001…SC-008 coverage)
- [ ] T040 [P] Update `README.md` in Spanish documenting the CRUD: book creation/editing rules (unique 13-digit ISBN, year 1900–current, derived availability, optional cover with `storage:link`), category management and normalized uniqueness, deletion blocks and confirmations, plus screenshots of the forms (plan.md G9, constitution «Flujo de desarrollo» — README as official documentation)
- [ ] T041 [P] Final security and consistency sweep across `app/`, `resources/`, `routes/` and `lang/`: no `$request->all()` in controllers, no concatenated SQL, all dynamic output escaped with `{{ }}` (including titles/names interpolated in `confirm()` texts and notices), every delete form has `@csrf` + `@method('DELETE')`, all visible messages in Spanish without technical details (Principio II/IV, contracts/user-messages §6)
- [ ] T042 Run the full `php artisan test` one last time, clean up code, and commit all remaining changes with Conventional Commits on `feature/gestion-libros` (constitution «Flujo de desarrollo», plan.md G9)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — verifies the inherited `001`/`002` base (T001 blocks everything else)
- **Foundational (Phase 2)**: Depends on Setup completion — BLOCKS all user stories (Spanish validation messages and flash rendering are prerequisites of every story)
- **User Story 1 (Phase 3)**: Depends on Foundational (T004–T006) — delivers the MVP
- **User Story 2 (Phase 4)**: Depends on Foundational and on US1's `LibroController`/routes/views (T013, T019) — it extends the same controller and the same detail view
- **User Story 3 (Phase 5)**: Depends on Foundational only; its selector verification uses the book form of US1 (T015), which can be built in parallel
- **Polish (Phase 6)**: Depends on completion of all desired user stories (T038 needs every management view; T039/T040/T042 need the full feature)

### User Story Dependencies

- **User Story 1 (P1)**: Can start after Foundational (Phase 2) — no dependencies on other stories; delivers the MVP
- **User Story 2 (P2)**: Requires US1's controller and detail view (T013, T019); independently testable once those land — its test (T021) can be written as soon as Foundational is done
- **User Story 3 (P3)**: Can start after Foundational in parallel with US1/US2 (different controllers, routes and views; only the final selector check T037 references US1's book form)

### Within Each User Story

- Tests (T007–T009, T021, T026–T027) MUST be written and FAIL before implementation
- Models before requests; requests before controllers; controllers before routes; routes before views; views before validation runs
- US1: T010 → (T011 ∥ T012) → T013 → T014 → (T015 ∥ T016 ∥ T017) → T018 → T019 → T020
- US2: T022 → T023 → T024 → T025
- US3: T028 → (T029 ∥ T030) → T031 → T032 → (T033 ∥ T034 ∥ T035) → T036 → T037

### Parallel Opportunities

- Setup: T002 ∥ T003 (after T001)
- Foundational: T005 ∥ T006 (with T004, different files)
- US1 tests: T007 ∥ T008 ∥ T009
- US1 requests: T011 ∥ T012
- US1 views: T015 ∥ T016 ∥ T017 (after T014)
- US2 test can be written while US1 implementation proceeds (different files)
- US3 tests: T026 ∥ T027; US3 requests: T029 ∥ T030; US3 views: T033 ∥ T034 ∥ T035 (after T032)
- US3 as a whole can run in parallel with US1/US2 (no shared files until the final selector check)
- Polish: T038 ∥ T039 ∥ T040 ∥ T041

---

## Parallel Example: User Story 1

```bash
# Launch all tests for User Story 1 together (tests requested):
Task: "Create tests/Feature/LibroCreacionTest.php covering V-001/V-003/V-004"
Task: "Create tests/Feature/LibroEdicionTest.php covering V-002/V-005"
Task: "Create tests/Feature/LibroPortadaTest.php covering V-008"

# Launch both Form Requests together:
Task: "Create app/Http/Requests/LibroStoreRequest.php"
Task: "Create app/Http/Requests/LibroUpdateRequest.php"

# Then the views together:
Task: "Create resources/views/libros/_form.blade.php"
Task: "Create resources/views/libros/create.blade.php"
Task: "Create resources/views/libros/edit.blade.php"
```

## Parallel Example: User Story 2

```bash
# Test first (different file from the controller):
Task: "Create tests/Feature/LibroEliminacionTest.php"

# Then controller destroy + view extension are different files but the view
# depends on the route name, so run them after T022/T023:
Task: "Add destroy to app/Http/Controllers/LibroController.php"
Task: "Extend resources/views/catalogo/show.blade.php with the delete form"
```

## Parallel Example: User Story 3

```bash
# Launch both tests together:
Task: "Create tests/Feature/CategoriaGestionTest.php"
Task: "Create tests/Feature/CategoriaUnicidadTest.php"

# Launch both Form Requests together:
Task: "Create app/Http/Requests/CategoriaStoreRequest.php"
Task: "Create app/Http/Requests/CategoriaUpdateRequest.php"

# Then the three views together:
Task: "Create resources/views/categorias/index.blade.php"
Task: "Create resources/views/categorias/create.blade.php"
Task: "Create resources/views/categorias/edit.blade.php"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup (T001 verifies `001`+`002` are in place)
2. Complete Phase 2: Foundational (CRITICAL — Spanish messages + flash block)
3. Complete Phase 3: User Story 1 (tests first, then model → requests → controller → routes → views → catalog buttons → validation)
4. **STOP and VALIDATE**: quickstart V-001…V-005, V-008 + `php artisan test` for the three US1 suites
5. Demo: `php artisan serve` → register a book with cover from the catalog listing, open its detail, edit it

### Incremental Delivery

1. Complete Setup + Foundational → foundation ready
2. Add User Story 1 → test independently → demo (**MVP!**: books can be registered and edited)
3. Add User Story 2 → test independently → demo (books can be deleted safely)
4. Add User Story 3 → test independently → demo (categories manageable with normalized uniqueness)
5. Each story adds value without breaking previous stories

### Parallel Team Strategy

With multiple developers:

1. Team completes Setup + Foundational together (T001 gates everyone)
2. Once Foundational is done:
   - Developer A: User Story 1 (tests, model, requests, controller, views)
   - Developer B: User Story 2 test (T021) + User Story 3 (migration, requests, controller, views — no shared files with US1)
3. US2 hooks its `destroy` onto US1's controller as soon as T013/T019 land
4. Stories complete and integrate independently

---

## Notes

- [P] tasks = different files, no dependencies
- [Story] label maps task to specific user story for traceability
- Each user story is independently completable and testable (US2 extends US1's controller/view by design of FR-001; US3's selector check reuses US1's form)
- Verify tests fail before implementing
- Commit after each task or logical group with Conventional Commits (branch `feature/gestion-libros`)
- Stop at any checkpoint to validate the story independently
- Avoid: vague tasks, same-file conflicts, cross-story dependencies that break independence
- Out of scope (do NOT implement here): loan registration/control (`004`), cover file replacement/deletion (`005`), catalog search/filter (`006`), dashboard (`007`); loans only appear as read-only restrictions in FR-007/FR-009
