---

description: "Task list for 001-entorno-datos (Entorno y base de datos)"
---

# Tasks: Entorno y base de datos (001-entorno-datos)

**Input**: Design documents from `/specs/001-entorno-datos/`

**Prerequisites**: plan.md (required), spec.md (required for user stories), research.md, data-model.md, contracts/, quickstart.md

**Tests**: Tests ARE requested — `plan.md` §Source Code names the four feature test files explicitly and `quickstart.md` V-008 requires `php artisan test` green. Test tasks are therefore included per user story.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2)
- Include exact file paths in descriptions

## Path Conventions

- **Single project**: `app/`, `database/`, `resources/`, `routes/`, `tests/` at repository root (monolito Laravel 13, per `plan.md` §Source Code)
- All paths below are relative to the repository root unless absolute

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Project initialization and basic structure per `plan.md` and `research.md` D-001/D-002/D-007/D-008

- [X] T001 Create Laravel 13 project skeleton in the repository root with `composer create-project laravel/laravel .` (PHP >= 8.3, Composer 2.x) producing the standard tree of `plan.md` §Source Code (`app/`, `database/`, `resources/`, `routes/`, `tests/`), then make the initial Conventional Commit containing the freshly created Laravel project (constitution «Flujo de desarrollo»: el primer commit contiene el proyecto Laravel recién creado)
- [X] T002 [P] Configure `.env.example` with the variables of `specs/001-entorno-datos/contracts/environment-variables.md` — `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_DATABASE=biblioteca`, `DB_USERNAME=root`, `DB_PASSWORD` (vacío, **nunca versionado**), `APP_KEY` sin valor real — and confirm `.env` is listed in `.gitignore` (FR-007, D-007)
- [X] T003 [P] Configure `phpunit.xml` for the dedicated MySQL test database: `DB_CONNECTION=mysql`, `DB_DATABASE=biblioteca_test` (research D-008: RefreshDatabase sobre el mismo motor MySQL que desarrollo, sin SQLite en memoria)
- [X] T004 [P] Create the local MySQL 8 databases `biblioteca` (development) and `biblioteca_test` (tests) required by `php artisan migrate --seed` and `php artisan test` (research D-002); they will be documented in `README.md` during US1

**Checkpoint**: Laravel 13 installed, environment contract in place, databases ready — foundational work can begin

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core infrastructure that MUST be complete before ANY user story can be implemented

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [X] T005 Create the shared Blade layout `resources/views/layouts/app.blade.php` (Principio V «layout Blade común»; base for `resources/views/welcome.blade.php`, `resources/views/errors/conexion.blade.php` in US1 and for `002-consulta-catalogo`+)
- [X] T006 [P] Register the placeholder route `GET /` in `routes/web.php` rendering the `welcome` view (content and DB connectivity check completed in US1)

**Checkpoint**: Foundation ready — user story implementation can now begin

---

## Phase 3: User Story 1 - Preparar el entorno y los datos (Priority: P1) 🎯 MVP

**Goal**: The team can start the application following the README (`php artisan serve`), with a schema created exclusively via migrations (FK `RESTRICT`, unique ISBN and category name), seeded with ≥15 libros, 3 categorías and 3 préstamos covering activo/devuelto/vencido with coherent availability, credentials outside the repository, and a Spanish notice without credentials when the connection fails.

**Independent Test**: Follow `README.md` in a prepared environment (quickstart V-001) and check `php artisan migrate:fresh --seed` + `php artisan test` reproduce V-002 to V-005 and V-007 (connection, data counts, states, relations, availability invariant, credentials excluded).

### Tests for User Story 1 (requested by plan.md §Source Code and quickstart.md V-008) ⚠️

> **NOTE: Write these tests FIRST, ensure they FAIL before implementation**

- [X] T007 [P] [US1] Create `tests/Feature/MigracionesYSemillaTest.php` covering quickstart V-002/V-003/V-004: `migrate:status` 100 % Ran, `migrate:rollback` + `migrate` without errors, FK `libros.categoria_id` and `prestamos.libro_id` with `ON DELETE RESTRICT`, unique indexes `libros_isbn_unique` and `categorias_nombre_unique`, ≥15 libros / ≥3 categorías / exactly 3 préstamos with `activo`+`devuelto`+`vencido`, all `portada = NULL`, invariant `ejemplares_disponibles = ejemplares_totales − ejemplares en préstamos activos` for 100 % of libros, 100 % libros with `categoria_id` valid and 100 % préstamos with `libro_id` valid
- [X] T008 [P] [US1] Create `tests/Feature/FalloConexionTest.php` covering quickstart V-005 and `contracts/user-messages.md` §1: on `PDOException`/`QueryException` (acceso denegado, host inexistente o base no disponible) the app renders the Spanish notice, does NOT serve any data view, and the response contains no user, password, host or SQL (SC-004)
- [X] T009 [P] [US1] Create `tests/Feature/CredencialesTest.php` covering quickstart V-007 (FR-007): `.env` is not tracked by Git, `.env.example` is tracked with `DB_PASSWORD` empty and no real secrets

### Implementation for User Story 1

- [X] T010 [P] [US1] Create migration `database/migrations/*_create_categorias_table.php`: `nombre` `varchar(100)` **NOT NULL** with unique index `categorias_nombre_unique` (FR-003, clarificación 2026-10-26)
- [X] T011 [P] [US1] Create migration `database/migrations/*_create_libros_table.php`: `isbn` `char(13)` **NOT NULL UNIQUE** (`libros_isbn_unique`), `titulo`/`autor`/`editorial` `varchar(255)` NOT NULL, `anio_publicacion` `smallint` NOT NULL (entre 1900 y el año actual se valida en servidor), `ejemplares_totales` `int unsigned` NOT NULL `>= 1`, `ejemplares_disponibles` `int unsigned` NOT NULL `<= ejemplares_totales`, `categoria_id` `bigint unsigned` FK → `categorias.id` **`restrictOnDelete()`** (Laravel usa cascade por defecto — D-004), `portada` `varchar(255)` nullable (NULL = imagen predeterminada), timestamps
- [X] T012 [US1] Create migration `database/migrations/*_create_prestamos_table.php` with a timestamp prefix AFTER the `libros` migration (FK order categorías → libros → préstamos): `libro_id` `bigint unsigned` FK → `libros.id` **`restrictOnDelete()`**, `nombre_estudiante` `varchar(255)` NOT NULL, `carnet_estudiante` `varchar(50)` NOT NULL, `fecha_prestamo` `date` NOT NULL, `fecha_devolucion_prevista` `date` NOT NULL (> `fecha_prestamo`, validación de servidor), `fecha_devolucion_real` `date` nullable (NOT NULL solo cuando `estado = 'devuelto'`), `estado` NOT NULL uno de `activo`|`devuelto`|`vencido`, index on `estado` (depends on T011)
- [X] T013 [P] [US1] Create `app/Models/Categoria.php` with `$fillable = ['nombre']` and relation `libros()` → `hasMany(Libro)` (Principio I)
- [X] T014 [P] [US1] Create `app/Models/Libro.php` with `$fillable` for all writable fields, `casts` (`ejemplares_totales`/`ejemplares_disponibles` int), `categoria()` → `belongsTo(Categoria)` and `prestamos()` → `hasMany(Prestamo)` (Principio I)
- [X] T015 [P] [US1] Create `app/Models/Prestamo.php` with `$fillable`, date `casts` (`fecha_prestamo`, `fecha_devolucion_prevista`, `fecha_devolucion_real` nullable), `estado` (`activo`|`devuelto`|`vencido`) and `libro()` → `belongsTo(Libro)` (Principio I)
- [X] T016 [US1] Create `database/factories/CategoriaFactory.php` generating unique `nombre` values (≤100 caracteres) (depends on T013)
- [X] T017 [US1] Create `database/factories/LibroFactory.php`: unique 13-digit ISBN matching `regex:/^[0-9]{13}$/`, `anio_publicacion` entre 1900 y el año actual, `ejemplares_totales >= 1`, `ejemplares_disponibles <= ejemplares_totales`, `portada = null` (datos de prueba sin portada real — clarificación 2026-10-26) (depends on T014)
- [X] T018 [US1] Create `database/factories/PrestamoFactory.php` with states `activo()` (sin `fecha_devolucion_real`, prevista futura), `devuelto()` (`fecha_devolucion_real` no nula) and `vencido()` (sin `fecha_devolucion_real`, prevista pasada), `fecha_devolucion_prevista` siempre > `fecha_prestamo` (depends on T015)
- [X] T019 [US1] Create `database/seeders/CatalogoSeeder.php` loading exactly 3 categorías (nombres únicos), ≥15 libros (ISBN únicos de 13 dígitos, `portada = NULL`, asignados a categorías existentes) and exactly 3 préstamos (1 `activo`, 1 `devuelto`, 1 `vencido` sobre libros existentes), preserving the invariant `ejemplares_disponibles = ejemplares_totales − ejemplares en préstamos activos` for every libro, in FK order categorías → libros → préstamos (FR-004, SC-002, D-005) (depends on T016, T017, T018)
- [X] T020 [US1] Wire `database/seeders/DatabaseSeeder.php` to invoke `CatalogoSeeder` so `php artisan migrate:fresh --seed` loads all test data (depends on T019)
- [X] T021 [P] [US1] Create `resources/views/errors/conexion.blade.php` (extends `resources/views/layouts/app.blade.php`): Spanish notice containing (a) «no se pudo conectar con la base de datos», (b) «revise la configuración de conexión en el archivo `.env`», (c) **no** user, password, host nor SQL — verbatim contract `contracts/user-messages.md` §1 (FR-008, SC-004)
- [X] T022 [US1] Configure connection-failure handling in `bootstrap/app.php` (`withExceptions`) plus `app/Exceptions/` so `PDOException`/`QueryException` of access denial, missing host or unavailable database renders `errors.conexion` in Spanish and blocks every data view, without leaking credentials or SQL (research D-006, FR-008) (depends on T021)
- [X] T023 [US1] Complete `resources/views/welcome.blade.php` (extends `resources/views/layouts/app.blade.php`, output escaped with `{{ }}`) and wire `routes/web.php` so `GET /` proves the database connection (depends on T005, T006, T022)
- [X] T024 [US1] Write `README.md` in Spanish with: prerequisites (PHP >= 8.3, Composer 2.x, MySQL 8, Git), install steps of `contracts/cli-commands.md` (`composer install`, `cp .env.example .env`, editar `.env`, `php artisan key:generate`, `php artisan migrate --seed`), creation of the `biblioteca` database, chosen engine MySQL 8 with MariaDB/PostgreSQL variant note (FR-005, D-002), ER diagram of `data-model.md`, and the single start command `php artisan serve` → `http://127.0.0.1:8000` (FR-001, D-009, SC-003)
- [X] T025 [US1] Validate User Story 1 end to end per `quickstart.md`: `php artisan migrate:status`, `php artisan migrate:rollback && php artisan migrate`, `php artisan migrate:fresh --seed` (V-002/V-003/V-004 checks via tinker), `php artisan serve` following only `README.md` (V-001), wrong-credentials run showing `resources/views/errors/conexion.blade.php` (V-005), `git ls-files` without `.env` (V-007), and `php artisan test` green (V-008) — validated 2026-10-28: all checks green (see Notes «Registro de validación V-001…V-008»)

**Checkpoint**: At this point, User Story 1 should be fully functional and testable independently (MVP)

---

## Phase 4: User Story 2 - Documentar la conducta de eliminación (Priority: P2)

**Goal**: The deletion behavior of related records is decided, enforced and documented in `README.md` (section «Eliminación de registros relacionados»), so the team knows what happens when deleting a categoría with libros or a libro with préstamos.

**Independent Test**: Locate the documented behavior in `README.md` and confirm it with controlled deletion attempts over test data: blocked with a Spanish notice while related records exist (quickstart V-006, SC-005); successful when there are none.

> **Note**: The README section (T028) can be written as soon as Phase 2 is done; the service (T027) and the test run (T026/T029) require the US1 schema and seed data (T010–T020), as the spec's own independent test uses «datos de prueba».

### Tests for User Story 2 (requested by plan.md §Source Code and quickstart.md V-008) ⚠️

> **NOTE: Write this test FIRST, ensure it FAILS before implementation**

- [X] T026 [P] [US2] Create `tests/Feature/EliminacionRelacionadaTest.php` covering quickstart V-006 and `contracts/user-messages.md` §2: deleting a categoría with libros is blocked with the Spanish message about libros asociados (app check) and a direct delete also fails at DB level by `RESTRICT`; deleting a libro with préstamos is blocked with the Spanish message about préstamos asociados; deleting a categoría without libros and a libro without préstamos succeeds (FR-006, SC-005)

### Implementation for User Story 2

- [X] T027 [US2] Implement `app/Services/EliminacionService.php` with the app-side pre-check required by `data-model.md` §Reglas de eliminación documentadas: `eliminarCategoria()` blocks while `libros` exist and `eliminarLibro()` blocks while `prestamos` exist, exposing the Spanish messages of `contracts/user-messages.md` §2; the `restrictOnDelete()` FKs (D-004) remain as database backstop (depends on T010–T015 schema/models and T026)
- [X] T028 [US2] Add the section «Eliminación de registros relacionados» to `README.md` in Spanish documenting the table of `data-model.md` §Reglas de eliminación documentadas: categoría con libros → bloqueada (aviso de libros asociados), libro con préstamos → bloqueada (aviso de préstamos asociados), sin registros relacionados → eliminación correcta, siempre mediante `DELETE` con confirmación, nunca GET (Principio IV, FR-006, SC-005)
- [X] T029 [US2] Validate User Story 2 per quickstart V-006: run `php artisan test` with `tests/Feature/EliminacionRelacionadaTest.php` green (6/6), perform the four controlled deletion attempts (2 blocked, 2 successful) and confirm `README.md` explains the behavior in Spanish (section «Eliminación de registros relacionados» present) — validated 2026-10-28

**Checkpoint**: All user stories should now be independently functional

---

## Phase 5: Polish & Cross-Cutting Concerns

**Purpose**: Improvements that affect multiple user stories

- [X] T030 [P] Execute every scenario V-001 to V-008 of `specs/001-entorno-datos/quickstart.md` in a clean environment and record the results (SC-001 to SC-005 coverage) — results recorded in Notes «Registro de validación V-001…V-008»
- [X] T031 [P] Review `README.md` against FR-001 (único comando de inicio `php artisan serve`), FR-005 (motor elegido documentado), FR-006 (sección «Eliminación de registros relacionados») y `contracts/user-messages.md` (todos los mensajes visibles en español, sin credenciales) — PASS: §Comando de inicio (único, `php artisan serve`), §Requisitos previos (MySQL 8 + variantes MariaDB/PostgreSQL), §Eliminación de registros relacionados (tabla con los 4 casos + «DELETE nunca GET»), avisos en español sin credenciales
- [X] T032 [P] Final security sweep: `git ls-files` has no `.env`, grep of `app/`, `database/`, `resources/`, `routes/`, `tests/` and `README.md` shows no credentials or concatenated SQL (FR-007, Principio IV), Eloquent/Query Builder used everywhere (D-006/D-007) — PASS: solo `.env.example` trackeado (`DB_PASSWORD=` y `APP_KEY=` vacíos), `.env` en `.gitignore`; sin credenciales ni SQL concatenado con entrada de usuario en código de la aplicación; las únicas consultas `DB::select` son introspección estática de `information_schema` en `tests/Feature/MigracionesYSemillaTest.php` (sin interpolación); portadas de vistas compiladas obsoletas limpiadas con `view:clear`
- [X] T033 Run `php artisan test` one last time, clean up code, and commit all remaining changes with Conventional Commits on `feature/entorno-datos` (constitution «Flujo de desarrollo», plan.md G9)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — can start immediately
- **Foundational (Phase 2)**: Depends on Setup completion (T001) — BLOCKS all user stories
- **User Story 1 (Phase 3)**: Depends on Foundational (T005, T006)
- **User Story 2 (Phase 4)**: Depends on Foundational; its test/service/validation tasks additionally require US1's schema and seed data (T010–T020) — see note in Phase 4
- **Polish (Phase 5)**: Depends on completion of all desired user stories

### User Story Dependencies

- **User Story 1 (P1)**: Can start after Foundational (Phase 2) — no dependencies on other stories; delivers the MVP
- **User Story 2 (P2)**: README documentation (T028) can start after Foundational; service, test execution and validation (T026, T027, T029) need US1's migrations/models/seeders (spec: verification «sobre datos de prueba»)

### Within Each User Story

- Tests (T007–T009, T026) MUST be written and FAIL before implementation
- Migrations before models (FK order categorías → libros → préstamos)
- Models before factories; factories before seeders
- Seeders/schema before connection-failure wiring validation
- README before the end-to-end validation that follows it (V-001)

### Parallel Opportunities

- Setup: T002 ∥ T003 ∥ T004 (after T001)
- Foundational: T006 (with T005, different files)
- US1 tests: T007 ∥ T008 ∥ T009
- US1 migrations: T010 ∥ T011 (T012 follows T011 for timestamp/FK order)
- US1 models: T013 ∥ T014 ∥ T015
- US1 factories: T016 ∥ T017 ∥ T018 (after their models)
- US1 views: T021 (after Phase 2 layout)
- Polish: T030 ∥ T031 ∥ T032
- Different user stories can proceed in parallel once their prerequisites are met

---

## Parallel Example: User Story 1

```bash
# Launch all tests for User Story 1 together (tests requested):
Task: "Create tests/Feature/MigracionesYSemillaTest.php covering V-002/V-003/V-004"
Task: "Create tests/Feature/FalloConexionTest.php covering V-005"
Task: "Create tests/Feature/CredencialesTest.php covering V-007"

# Launch all models for User Story 1 together:
Task: "Create app/Models/Categoria.php"
Task: "Create app/Models/Libro.php"
Task: "Create app/Models/Prestamo.php"

# Then the factories together:
Task: "Create database/factories/CategoriaFactory.php"
Task: "Create database/factories/LibroFactory.php"
Task: "Create database/factories/PrestamoFactory.php"
```

## Parallel Example: User Story 2

```bash
# Documentation can run while the service is implemented (different files):
Task: "Implement app/Services/EliminacionService.php"
Task: "Add section «Eliminación de registros relacionados» to README.md"

# After both, validation runs sequentially:
Task: "Validate US2: php artisan test + quickstart V-006"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup
2. Complete Phase 2: Foundational (CRITICAL — blocks all stories)
3. Complete Phase 3: User Story 1 (tests first, then migrations → models → factories → seeders → connection handling → README → validation)
4. **STOP and VALIDATE**: quickstart V-001 to V-005, V-007, V-008
5. Demo: `php artisan migrate:fresh --seed && php artisan serve` following only `README.md`

### Incremental Delivery

1. Complete Setup + Foundational → foundation ready
2. Add User Story 1 → test independently → deploy/demo (**MVP!**)
3. Add User Story 2 → test independently → deploy/demo
4. Each story adds value without breaking previous stories

### Parallel Team Strategy

With multiple developers:

1. Team completes Setup + Foundational together
2. Once Foundational is done:
   - Developer A: User Story 1 (tests, migrations, models)
   - Developer B: User Story 2 documentation (T028) while US1 schema lands
3. Stories complete and integrate independently

---

## Notes

- [P] tasks = different files, no dependencies
- [Story] label maps task to specific user story for traceability
- Each user story is independently completable and testable (US2's live verification uses US1's test data, by design of the spec)
- Verify tests fail before implementing
- Commit after each task or logical group with Conventional Commits (branch `feature/entorno-datos`)
- Stop at any checkpoint to validate the story independently
- Avoid: vague tasks, same-file conflicts, cross-story dependencies that break independence

## Registro de validación V-001…V-008 (2026-10-28)

Ejecutado en entorno local limpio (`migrate:fresh --seed`, MySQL 8 en `127.0.0.1:3306`, PHP 8.4):

| Escenario | Resultado | Evidencia |
|---|---|---|
| V-001 Arranque por README | ✅ | `php artisan serve` → `GET http://127.0.0.1:8000` = 200; el `main` muestra «Conexión a la base de datos verificada» con datos en vivo (16 libros, 3 categorías, 3 préstamos) |
| V-002 Esquema versionado | ✅ | `migrate:status` = 6/6 «Ran»; `migrate:rollback` + `migrate` sin errores; FK `restrictOnDelete()` en ambas migraciones y bloqueo `RESTRICT` comprobado en BD (error 1451 en borrado directo); índices únicos `libros_isbn_unique` y `categorias_nombre_unique` (`Non_unique=0`) |
| V-003 Datos de prueba | ✅ | 3 categorías, 16 libros (≥15), exactamente 3 préstamos (`activo`/`devuelto`/`vencido`), `portada` NULL en el 100 %, invariante `disponibles = totales − activos` = `true` en el 100 % |
| V-004 Relaciones | ✅ | 0 libros sin categoría, 0 préstamos sin libro; relaciones Eloquent `Libro→Categoria` y `Prestamo→Libro` resuelven |
| V-005 Fallo de conexión | ✅ | Con `DB_USERNAME` inválido: HTTP 500 con `errors.conexion` en español («no se pudo conectar… revise la configuración… `.env`»); sin usuario, contraseña, host ni SQL en la respuesta; restaurado `.env` → 200 con normalidad |
| V-006 Eliminación | ✅ | App: 2 intentos bloqueados con los mensajes en español del contrato + 2 exitosos (temporales, semilla intacta); BD: borrado directo de categoría con libros y libro con préstamos bloqueado por `RESTRICT`; README lo documenta |
| V-007 Credenciales fuera del repo | ✅ | `git ls-files` solo contiene `.env.example` (`DB_PASSWORD=`, `APP_KEY=` vacíos); `git check-ignore .env` confirma ignorado; cambios en `.env` no tocan archivos versionados |
| V-008 Pruebas automatizadas | ✅ | `php artisan test` = 21/21 en verde (103 aserciones), `EliminacionRelacionadaTest` 6/6 |

**Cobertura SC**: SC-001→V-004, SC-002→V-003, SC-003→V-001, SC-004→V-005, SC-005→V-006 — todas cubiertas.
