---

description: "Task list for 008-interfaz-validacion-seguridad (Interfaz, validación y seguridad transversales)"
---

# Tasks: Interfaz, validación y seguridad transversales (008-interfaz-validacion-seguridad)

**Input**: Design documents from `/specs/008-interfaz-validacion-seguridad/`

**Prerequisites**: plan.md (required), spec.md (required for user stories), research.md, data-model.md, contracts/, quickstart.md

**Tests**: Tests ARE requested — `plan.md` §Source Code names the nine transversal suites explicitly (`AuthAccesoTest`, `AuthLoginTest`, `SesionCaducadaTest`, `CsrfProteccionTest`, `EscapadoSalidaTest`, `EliminacionDeleteTest`, `ValidacionTransversalTest`, `AccesibilidadUiTest`, `EstadosVaciosTest`) and `quickstart.md` V-012 requires `php artisan test` green. Test tasks are therefore included per user story.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2)
- Include exact file paths in descriptions

## Path Conventions

- **Single project**: `app/`, `database/`, `resources/`, `routes/`, `public/`, `lang/`, `tests/` at repository root (monolito Laravel 13, per `plan.md` §Source Code)
- All paths below are relative to the repository root unless absolute
- This feature is **transversal**: it does not create content views of its own except the login view; it audits and homogenizes the views/forms delivered by `002`–`007`

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Establish the working baseline for the transversal audit per `plan.md` and `research.md` D-036/D-047

- [ ] T001 Create and switch to branch `feature/interfaz-seguridad` (plan.md G9), make sure the Laravel application of `001`–`007` is present and installed (`composer install`, `.env` per `specs/001-entorno-datos/contracts/environment-variables.md`, `php artisan migrate --seed`), and record the green baseline of `php artisan test` (suites de 001–007) in `specs/008-interfaz-validacion-seguridad/checklists/requirements.md` — every later phase must keep it green (D-047: ninguna regresión)
- [ ] T002 [P] Audit the transversal inventory of `specs/008-interfaz-validacion-seguridad/data-model.md` §Inventario (los 7 formularios: login, crear/editar libro, crear/editar categoría, registrar préstamo, devolver préstamo, subir portada, búsqueda) and of `specs/008-interfaz-validacion-seguridad/contracts/ui-structure.md` (label/`old()`/error junto al campo/`@csrf`/`@method('DELETE')`/`{!! !!}`) across `resources/views/`, `routes/web.php` and `app/Http/Controllers/`, recording each gap in `specs/008-interfaz-validacion-seguridad/checklists/requirements.md`

**Checkpoint**: Branch ready, regression baseline recorded, gap inventory complete — foundational work can begin

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: The `users` identity schema and the Spanish validation language files that BOTH user stories depend on (US2's login uses them; US1's tests authenticate with `actingAs()` over them because every business route becomes protected in this feature)

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [ ] T003 [P] Ensure the migration `database/migrations/*_create_users_table.php` exists (reuse the Laravel skeleton's migration if already present, otherwise create it) with exactly the schema of `data-model.md` §Entidad nueva `users`: `id` bigint unsigned PK, `name` varchar(255) **NOT NULL**, `email` varchar(255) **NOT NULL** with UNIQUE index, `password` varchar(255) **NOT NULL**, `remember_token` char(100) NULL, timestamps — **«Sin columnas de roles ni permisos»**, único cambio de esquema de `008` (D-037, Principio I)
- [ ] T004 [P] Ensure the model `app/Models/User.php` exists (skeleton default or create it): `$fillable = ['name', 'email', 'password']`, `$hidden = ['password', 'remember_token']`, **sin roles ni permisos y sin relaciones hacia el catálogo** (D-037); ensure `database/factories/UserFactory.php` exists (skeleton default) so tests can create users with `actingAs()`
- [ ] T005 Create `database/seeders/UserSeeder.php` with at least one account created by the team whose password is stored **`Hash::make(...)` — never plaintext** («Solo hash; nunca en claro ni en el repositorio», data-model §users), and wire it into `database/seeders/DatabaseSeeder.php` so `php artisan migrate:fresh --seed` enables the login scenarios (depends on T003, T004)
- [ ] T006 [P] Ensure `lang/es/validation.php` exists with Spanish messages and custom attributes for the fields of libros/categorías/préstamos/portada (heredado de `003` D-024) — this is the source of every field error message rendered by US1 (Principio II)

**Checkpoint**: Foundation ready — `users` schema, `User` model, seeded account and Spanish validation messages in place; user story implementation can now begin

---

## Phase 3: User Story 1 - Navegar y corregir datos (Priority: P1) 🎯 MVP

**Goal**: All main views share one visual structure (layout with own CSS), every form rejects invalid data server-side and shows **all** errors **in Spanish right next to each field** with the entered values kept (`old()`), the interface adapts to 360 × 640 / 768 × 1024 / ≥ 1024 px without clipping, empty lists show «No hay registros» plus the available action, and everything meets WCAG 2.1 AA (labels, keyboard, contrast ≥ 4,5:1).

**Independent Test**: Open the main views (`/catalogo`, `/libros`, `/categorias`, `/prestamos`, `/panel`) and submit a form with invalid data (quickstart V-005): the views share the common structure and the error appears next to the field with the values kept; empty lists (V-009), labels/aria/keyboard (V-010) and the three sizes (V-011) verified. No login UI is required for this story — the tests authenticate with `actingAs()` and the `auth` middleware lands in US2.

### Tests for User Story 1 (requested by plan.md §Source Code and quickstart.md V-012) ⚠️

> **NOTE: Write these tests FIRST, ensure they FAIL before implementation**

- [ ] T007 [P] [US1] Create `tests/Feature/ValidacionTransversalTest.php` covering quickstart V-005 and SC-002 for the forms of `data-model.md` §Inventario #2–#7 (libro crear/editar, categoría, registrar préstamo, devolver préstamo, portada — el formulario de login se cubre en `AuthLoginTest`, US2): with `actingAs()` created user, POST invalid data (vacío; ISBN de 12 dígitos; año 1800) → redirected back, **todos** los campos inválidos muestran su mensaje en español inmediatamente después del `<input>` (assert `aria-invalid="true"` and `aria-describedby` pointing at an existing error element), los valores válidos se conservan con `old()`, corregir un solo campo y reenviar mantiene los demás valores, and no record is created (FR-004, FR-003, SC-002)
- [ ] T008 [P] [US1] Create `tests/Feature/EstadosVaciosTest.php` covering quickstart V-009 and SC-008: with `actingAs()` over an empty business database (`RefreshDatabase` sin `CatalogoSeeder`), `GET /libros`, `GET /categorias` and `GET /prestamos` each render the Spanish message «No hay registros» next to its action link («Añadir libro», «Nueva categoría», «Registrar préstamo»); `GET /catalogo` keeps «No hay libros registrados aún» and `GET /panel` keeps «No hay datos disponibles»; no listing renders an empty area without explanation (D-046)
- [ ] T009 [P] [US1] Create `tests/Feature/AccesibilidadUiTest.php` covering quickstart V-010 and SC-007: for the main views (`/catalogo`, `/libros`, `/categorias`, `/prestamos`, `/panel` and their forms) assert every visible field has a `<label for>` targeting its `id`, error fields carry `aria-invalid` + `aria-describedby`, `<html lang="es">`, exactly one `<h1>` per view, the header/navigation/footer markup is identical across views (estructura común), and interactive elements are native focusable elements (navegación, envío y confirmación operables con teclado) (FR-011, D-045)

### Implementation for User Story 1

- [ ] T010 [P] [US1] Extend `resources/views/layouts/app.blade.php`: `<html lang="es">`, per-view `@yield('titulo')` with a single `h1` per view, navigation where the active page is distinguishable (`aria-current="page"`), and one identical footer (FR-001, ui-structure §1, D-036) — note: US2 adds the session block to this same file later (T033), do not remove these regions
- [ ] T011 [P] [US1] Create the shared partial `resources/views/partials/estado-vacio.blade.php` receiving a Spanish message plus an action (link/label) and rendering «No hay registros» next to the available action — never an empty area (FR-012, D-046, ui-structure §4)
- [ ] T012 [P] [US1] Rework the views under `resources/views/libros/` (listado, `libros.create`, `libros.edit` incl. el formulario de portada de `005`): every visible field follows the mandatory order etiqueta `<label for>` → campo con valor `old()` → error `@error` in Spanish immediately after the field with `aria-invalid="true"` and `aria-describedby`; the empty listing renders `partials/estado-vacio` with «No hay registros» + «Añadir libro» (FR-003/FR-004/FR-011/FR-012; depends on T011)
- [ ] T013 [P] [US1] Apply the same field structure (label → `old()` → error junto al campo con aria) and the empty state «No hay registros» + «Nueva categoría» to the views under `resources/views/categorias/` (FR-004, FR-012; depends on T011)
- [ ] T014 [P] [US1] Apply the same field structure and the empty state «No hay registros» + «Registrar préstamo» to the views under `resources/views/prestamos/` (listado, registro y devolución; FR-004, FR-012; depends on T011)
- [ ] T015 [P] [US1] Apply labels/aria/field structure to the catalog and search views under `resources/views/catalogo/` (listado `GET /catalogo`, ficha, controles de búsqueda de `006` conservando su patrón sin botón — D-029/D-031); keep the own message «No hay libros registrados aún» and add the available action when it applies (FR-004, FR-011, ui-structure §4)
- [ ] T016 [P] [US1] Apply labels/aria/structure to the panel views under `resources/views/panel/`, keeping its «No hay datos disponibles» text (vista de solo consulta de `007`, sin acción; FR-011, ui-structure §4)
- [ ] T017 [P] [US1] Audit `app/Http/Controllers/` (Libro, Categoria, Prestamo, Portada, Catalogo controllers de 003–006) so every write consumes **only** `$request->validated()` and every rejection flows `back()->withErrors()->withInput()` — ningún dato sin validar llega a la base de datos (FR-003, D-041, Principio II)
- [ ] T018 [US1] Add the responsive rules to `public/css/app.css` with media queries in the three verified ranges **≤ 640 px** (teléfono 360 × 640: una columna, formularios apilados, tablas con desplazamiento propio o apiladas), **641–1023 px** (tableta 768 × 1024: rejilla intermedia, navegación legible) and **≥ 1024 px** (escritorio sin cambios) — sin recortes ni desbordamiento horizontal (FR-002/FR-008, SC-001, D-044)
- [ ] T019 [US1] Add the accessibility rules to `public/css/app.css` (same file as T018, run after it): visible `:focus`/`:focus-visible` outlines and a palette whose normal text reaches **contraste ≥ 4,5:1** sobre su fondo (FR-011, SC-007, D-045)
- [ ] T020 [US1] Validate User Story 1 per `specs/008-interfaz-validacion-seguridad/quickstart.md`: run `php artisan test --filter='ValidacionTransversalTest|EstadosVaciosTest|AccesibilidadUiTest'` green plus manual V-005, V-009, V-010 and V-011 (DevTools en 360 × 640, 768 × 1024 y ≥ 1024 px), and confirm the baseline suites of `001`–`007` still pass

**Checkpoint**: At this point, User Story 1 should be fully functional and testable independently (MVP)

---

## Phase 4: User Story 2 - Proteger las operaciones (Priority: P1)

**Goal**: Every business route requires a session (`GET|POST /login`, `POST /logout`, middleware `auth`, sesión a las **2 horas de inactividad**), a failed login shows the single generic Spanish message «Correo o contraseña incorrectos.» with no blocking or waiting, every form is CSRF-protected with the exact failure message next to the form, all output is escaped with `{{ }}`, and deletions only happen via `DELETE` with previous confirmation (GET → 405).

**Independent Test**: With controlled cases (quickstart V-001, V-003, V-004, V-006, V-007, V-008): operations without a session redirect to `/login` and are not executed; five consecutive failed logins always show the same generic message without lockout; a session expired after 2 h asks for login again before processing; a form posted without token saves nothing and shows the exact CSRF message with the input kept; `<script>` and `' OR 1=1 --` render literally; `GET` on deletion URLs answers 405 without touching records.

### Tests for User Story 2 (requested by plan.md §Source Code and quickstart.md V-012) ⚠️

> **NOTE: Write these tests FIRST, ensure they FAIL before implementation**

- [ ] T021 [P] [US2] Create `tests/Feature/AuthAccesoTest.php` covering quickstart V-001 and SC-005: as guest, `GET /catalogo`, `GET /libros`, `GET /categorias`, `GET /prestamos` and `GET /panel` all answer `302` to `/login` rendering no data; a create form posted as guest (`POST /libros` or `POST /prestamos`) redirects to `/login` **sin crear registros**; the same requests with `actingAs()` process normally (FR-009, http-endpoints §2)
- [ ] T022 [P] [US2] Create `tests/Feature/AuthLoginTest.php` covering quickstart V-003, V-002 and SC-006: valid credentials from `UserSeeder` → `302` to the previous URL or `/catalogo`; **5 consecutive failed attempts** (wrong password, and also a nonexistent email) always render the identical text «Correo o contraseña incorrectos.» inside the login form with the email preserved via `old()`, never `401`/`404`, no lockout — a valid login immediately afterwards succeeds; `POST /logout` ends the session and `GET /catalogo` redirects to `/login` again (FR-010, D-039, user-messages §2)
- [ ] T023 [P] [US2] Create `tests/Feature/SesionCaducadaTest.php` covering quickstart V-004 and SC-005: authenticate, `travel(121)->minutes()` (2 h + 1 min de inactividad), then request a protected view and submit a form → `302` to `/login`, the operation is **not executed** (no record created/modified) and `/login` shows «Su sesión expiró. Inicie sesión de nuevo.» (FR-009, D-038, user-messages §2)
- [ ] T024 [P] [US2] Create `tests/Feature/CsrfProteccionTest.php` covering quickstart V-006 and SC-003: POST a form without the `_token` field (and with a tampered token) → `302` back to the origin view showing the exact message «Su sesión expiró o el formulario ya no es válido. Actualice la página e inténtelo de nuevo.», **nada se guarda** and the submitted values are kept; assert the CSRF middleware has **no entries in `$except`** (FR-005, D-040, user-messages §1)
- [ ] T025 [P] [US2] Create `tests/Feature/EscapadoSalidaTest.php` covering quickstart V-007 and SC-003: audit every file under `resources/views/` and fail on any `{!! !!}` outside the short allowlist of Laravel-generated output (p. ej. paginador — D-042); plus the functional case: create a libro titled `<script>alert('x')</script>` and another with `' OR 1=1 --` and assert both render **literal, visible, not executed**, with the record count unchanged (FR-005, FR-006)
- [ ] T026 [P] [US2] Create `tests/Feature/EliminacionDeleteTest.php` covering quickstart V-008 and SC-004: `GET` on the deletion URLs (`/libros/{id}`, `/categorias/{id}`, `/libros/{id}/portada`) answers **405 Method Not Allowed** on every repeated attempt with the records intact; the delete forms in the listings contain `@csrf`, `@method('DELETE')` and a Spanish `confirm()`; a valid `DELETE` removes exactly the confirmed record and a blocked deletion (p. ej. categoría con libros) leaves the record intact with a Spanish message (FR-007, D-043, http-endpoints §4)

### Implementation for User Story 2

- [ ] T027 [P] [US2] Create `app/Http/Requests/LoginRequest.php` validating **in the server** per `data-model.md` §Inventario #1: `email` **obligatorio con formato de correo** (`required|email`) and `password` **obligatorio** (`required`), with Spanish messages/attributes via `lang/es/validation.php`; the errors are rendered next to each field (FR-003, D-041)
- [ ] T028 [P] [US2] Create `lang/es/auth.php` with the exact Spanish texts of `contracts/user-messages.md` §2: «Correo o contraseña incorrectos.», «Inicie sesión para continuar.», «Su sesión expiró. Inicie sesión de nuevo.» — un único mensaje genérico de fallo, sin revelar si la cuenta existe, sin trazas ni detalles técnicos (FR-010, D-039)
- [ ] T029 [P] [US2] Configure the 2-hour inactivity session: `SESSION_LIFETIME=120` in `.env.example` and `config/session.php` honoring it (driver de sesión por defecto del esqueleto); the activity refreshes on each request so 120 minutos sin peticiones caducan la sesión (FR-009, D-038, data-model §Sesión)
- [ ] T030 [US2] Create `app/Http/Controllers/AuthController.php` per http-endpoints §1: `showLogin` returns `200` with the form (or `302` to `/catalogo` if already authenticated); `login` runs `LoginRequest` + `Auth::attempt()` and on success `302` to the previous URL or `/catalogo`, on failure `302` to `/login` with the generic message and the email kept — **jamás** `401`/`404`, **sin** `sleep()`, throttle ni bloqueo (D-039); `logout` closes the session and `302` to `/login` (depends on T027, T028)
- [ ] T031 [US2] Update `routes/web.php`: add `GET /login` (name `login`), `POST /login` (name `login.store`) and `POST /logout` (name `logout`) **outside** the auth group, and wrap **all** business routes (`/catalogo`, `/libros`, `/categorias`, `/prestamos`, `/panel`, `/portadas`) in a `->middleware('auth')` group — «Protegidas: todas las rutas salvo `GET /login`, `POST /login`»; confirm no GET deletion route exists (D-037, http-endpoints §2) (depends on T030)
- [ ] T032 [US2] Create `resources/views/auth/login.blade.php` extending `resources/views/layouts/app.blade.php`: `@csrf`, the mandatory field order etiqueta → campo (`old('email')`) → error `@error` con `aria-invalid`/`aria-describedby` next to the field, generic credential error shown **dentro del formulario** (FR-004/FR-010, ui-structure §2/§3, D-037) (depends on T027, T030)
- [ ] T033 [US2] Add the session block to `resources/views/layouts/app.blade.php` (same file as T010 — apply after it): with session, the navigation shows the greeting with the user's name plus a form `POST /logout` with `@csrf` and the button «Cerrar sesión»; without session only `GET /login` is visible (FR-009, ui-structure §2) (depends on T010)
- [ ] T034 [US2] Configure guest redirection in `bootstrap/app.php` (`withMiddleware` → `redirectGuestsTo`) so any unauthenticated or expired-session request is redirected to `route('login')` flashing the correct text of `lang/es/auth.php` — «Inicie sesión para continuar.» para acceso sin sesión y «Su sesión expiró. Inicie sesión de nuevo.» cuando la sesión caducó (p. ej. distinguiendo ambos casos por la presencia de la cookie de sesión) — rendered by the login view (FR-009, user-messages §2) (depends on T028, T031)
- [ ] T035 [US2] Handle CSRF failures in `bootstrap/app.php` (`withExceptions`): on `TokenMismatchException` redirect `302` back to the origin view with the **exact** message «Su sesión expiró o el formulario ya no es válido. Actualice la página e inténtelo de nuevo.» as an error next to the form, `withInput()` conservando lo ingresado, **sin guardar ningún dato**; keep the CSRF middleware active for every route with **no `$except`/`validateCsrfTokens(except: …)` entries** (FR-005, D-040, http-endpoints §3) (depends on T034 — same file)
- [ ] T036 [US2] Fix output escaping across `resources/views/`: replace every `{!! !!}` rendering user or query data with `{{ }}`, tolerating only Laravel-generated output already escaped by the framework (p. ej. el HTML del paginador), until `tests/Feature/EscapadoSalidaTest.php` (T025) passes (FR-005, D-042)
- [ ] T037 [US2] Sweep the deletion forms under `resources/views/libros/`, `resources/views/categorias/` and `resources/views/prestamos/`: each «Eliminar» submits `<form>` with `@csrf`, `@method('DELETE')` and a previous Spanish `confirm()` («¿Está seguro de que desea eliminar este registro?»); remove any link that would trigger a deletion via GET (FR-007, D-043, ui-structure §3; depends on T036 — same file tree)
- [ ] T038 [US2] Validate User Story 2 per `specs/008-interfaz-validacion-seguridad/quickstart.md`: run `php artisan test --filter='AuthAccesoTest|AuthLoginTest|SesionCaducadaTest|CsrfProteccionTest|EscapadoSalidaTest|EliminacionDeleteTest'` green plus manual V-001, V-002, V-003, V-004, V-006, V-007 and V-008 with `php artisan serve`, and confirm the `001`–`007` suites still pass

**Checkpoint**: Both user stories are now independently functional — the app is fully protected and its interface is coherent

---

## Phase 5: Polish & Cross-Cutting Concerns

**Purpose**: Improvements that affect multiple user stories

- [ ] T039 [P] Execute every scenario V-001 to V-012 of `specs/008-interfaz-validacion-seguridad/quickstart.md` in a clean environment (`php artisan migrate:fresh --seed`) and record the results in `specs/008-interfaz-validacion-seguridad/checklists/requirements.md` (coverage of SC-001 to SC-008)
- [ ] T040 [P] Perform the manual verification that PHPUnit cannot measure — contraste ≥ 4,5:1 con la herramienta de DevTools y ausencia de recortes/desbordamientos en **360 × 640 px**, **768 × 1024 px** y **≥ 1024 px** — and document how it was done and its outcome in `specs/008-interfaz-validacion-seguridad/quickstart.md` (D-045, D-047, SC-001/SC-007)
- [ ] T041 [P] Update `README.md` (documentación oficial, constitución G9): declare that the interface uses **CSS propio sin frameworks CSS** (o declarar cuál se usa, FR-001), document the inicio de sesión y la seguridad transversal (CSRF, escapado, eliminaciones por `DELETE`, sesión de 2 h), and add the capturas of the three sizes (360 × 640, 768 × 1024, ≥ 1024)
- [ ] T042 [P] Final security sweep over `routes/web.php`, `app/`, `resources/views/`, `database/` and the repository: no route of deletion by GET, `@csrf` present in every `<form>`, no `{!! !!}` with data outside the allowlist, Eloquent/Query Builder sin SQL concatenado, contraseñas solo con `Hash::make`, `.env` sin versionar y sin credenciales en el repositorio (FR-005/FR-006/FR-007, Principio IV, heredado D-007)
- [ ] T043 Run the complete `php artisan test` (suites de `001`–`008`, ninguna regresión), clean up the code, and commit all remaining changes with Conventional Commits on `feature/interfaz-seguridad` (D-047, plan.md G9)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — can start immediately
- **Foundational (Phase 2)**: Depends on Setup completion (T001, T002) — BLOCKS all user stories
- **User Story 1 (Phase 3)**: Depends on Foundational (T003–T006 — its tests authenticate over the `users` schema; its field errors come from `lang/es/validation.php`)
- **User Story 2 (Phase 4)**: Depends on Foundational (T003–T006 — `Auth::attempt` over `users`, `LoginRequest` messages); may start in parallel with US1 once the foundation lands
- **Polish (Phase 5)**: Depends on completion of all desired user stories

### User Story Dependencies

- **User Story 1 (P1)**: Can start after Foundational (Phase 2) — no dependencies on US2; the `auth` middleware is added by US2 (T031), so US1 remains testable on its own with `actingAs()` and public routes
- **User Story 2 (P1)**: Can start after Foundational (Phase 2) — no dependencies on US1 except the shared file `resources/views/layouts/app.blade.php` (T010 before T033) and `public/css/app.css` (US1 only)

### Within Each User Story

- Tests (T007–T009, T021–T026) MUST be written and FAIL before implementation
- US1: layout (T010) and estado vacío partial (T011) before the view tasks (T012–T016); responsive CSS (T018) before a11y CSS (T019) — same file
- US2: `LoginRequest`/`lang` (T027, T028) before `AuthController` (T030); controller before routes (T031) and login view (T032); `bootstrap/app.php` tasks sequential (T034 → T035); escaping fixes (T036) before the deletion sweep (T037) — same file tree
- Story complete before moving to the next priority; run the story's validation task (T020 / T038) as its checkpoint

### Parallel Opportunities

- Setup: T002 (with T001, read-only audit)
- Foundational: T003 ∥ T004 ∥ T006 (different files); T005 after T003+T004
- US1 tests: T007 ∥ T008 ∥ T009
- US1 views: T010 ∥ T011 first, then T012 ∥ T013 ∥ T014 ∥ T015 ∥ T016 (different directories) ∥ T017 (controllers)
- US2 tests: T021 ∥ T022 ∥ T023 ∥ T024 ∥ T025 ∥ T026
- US2: T027 ∥ T028 ∥ T029 (different files)
- Polish: T039 ∥ T040 ∥ T041 ∥ T042
- US1 ∥ US2 once the Foundational phase is complete (coordinating the shared `resources/views/layouts/app.blade.php`: T010 → T033)

---

## Parallel Example: User Story 1

```bash
# Launch all tests for User Story 1 together (tests requested):
Task: "Create tests/Feature/ValidacionTransversalTest.php covering V-005"
Task: "Create tests/Feature/EstadosVaciosTest.php covering V-009"
Task: "Create tests/Feature/AccesibilidadUiTest.php covering V-010"

# Then the layout and the shared partial together:
Task: "Extend resources/views/layouts/app.blade.php (lang, h1, nav activa, pie)"
Task: "Create resources/views/partials/estado-vacio.blade.php"

# Then all view groups in parallel (different directories) plus the controller audit:
Task: "Rework resources/views/libros/ (fields + estado vacío + aria)"
Task: "Apply field structure to resources/views/categorias/"
Task: "Apply field structure to resources/views/prestamos/"
Task: "Apply labels/aria to resources/views/catalogo/"
Task: "Apply labels/aria to resources/views/panel/"
Task: "Audit app/Http/Controllers/ for validated() only"

# CSS sequentially (same file):
Task: "Add responsive breakpoints to public/css/app.css"
Task: "Add focus/contrast rules to public/css/app.css"
```

## Parallel Example: User Story 2

```bash
# Launch all tests for User Story 2 together (tests requested):
Task: "Create tests/Feature/AuthAccesoTest.php covering V-001"
Task: "Create tests/Feature/AuthLoginTest.php covering V-002/V-003"
Task: "Create tests/Feature/SesionCaducadaTest.php covering V-004"
Task: "Create tests/Feature/CsrfProteccionTest.php covering V-006"
Task: "Create tests/Feature/EscapadoSalidaTest.php covering V-007"
Task: "Create tests/Feature/EliminacionDeleteTest.php covering V-008"

# Then the three independent implementation pieces together:
Task: "Create app/Http/Requests/LoginRequest.php"
Task: "Create lang/es/auth.php"
Task: "Set SESSION_LIFETIME=120 in .env.example / config/session.php"

# Then the serial chain: AuthController → routes/web.php → auth/login.blade.php
# and bootstrap/app.php (T034 → T035)
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup
2. Complete Phase 2: Foundational (CRITICAL — blocks all stories)
3. Complete Phase 3: User Story 1 (tests first, then layout → partial → views → controllers → CSS → validation)
4. **STOP and VALIDATE**: quickstart V-005, V-009, V-010, V-011 + suites de 001–007 en verde
5. Demo: `php artisan serve` navegando las vistas con errores junto al campo, estados vacíos y las tres resoluciones

### Incremental Delivery

1. Complete Setup + Foundational → foundation ready (`users`, seeder, mensajes en español)
2. Add User Story 1 → test independently → deploy/demo (**MVP!**)
3. Add User Story 2 → test independently → deploy/demo (login, CSRF, escapado, DELETE, sesión 2 h)
4. Polish → full quickstart V-001…V-012 → each story adds value without breaking previous stories

### Parallel Team Strategy

With multiple developers:

1. Team completes Setup + Foundational together (T003 ∥ T004 ∥ T006, then T005)
2. Once Foundational is done:
   - Developer A: User Story 1 (tests, layout, views, CSS)
   - Developer B: User Story 2 (tests, LoginRequest, lang, controller, routes) — coordinating the shared `resources/views/layouts/app.blade.php` (T010 lands before T033)
3. Stories complete and integrate independently; `php artisan test` stays green after each

---

## Notes

- [P] tasks = different files, no dependencies
- [Story] label maps task to specific user story for traceability
- Each user story is independently completable and testable (US1 needs no login UI: its routes are still public until US2's T031, and its tests use `actingAs()`)
- Verify tests fail before implementing
- Commit after each task or logical group with Conventional Commits (branch `feature/interfaz-seguridad`)
- Stop at any checkpoint to validate the story independently
- Avoid: vague tasks, same-file conflicts (`bootstrap/app.php`, `resources/views/layouts/app.blade.php`, `public/css/app.css` are sequential by design), cross-story dependencies that break independence
- Fixed texts from the clarificación 2026-10-26 are verbatim and must not be reworded: CSRF message, «Correo o contraseña incorrectos.», «No hay registros», breakpoints 360 × 640 / 768 × 1024 / ≥ 1024 px
