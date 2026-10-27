---

description: "Task list template for feature implementation"
---

# Tasks: Panel y reportes de biblioteca (007-panel-biblioteca)

**Input**: Design documents from `/specs/007-panel-biblioteca/`

**Prerequisites**: plan.md ✅, spec.md ✅, research.md ✅, data-model.md ✅, contracts/ ✅, quickstart.md ✅

**Tests**: SÍ se incluyen — D-026 (research.md) define explícitamente 5 suites de PHPUnit/`RefreshDatabase` y el plan las lista en `tests/Feature/`.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

## Path Conventions

- **Single project**: `app/`, `database/`, `routes/`, `resources/`, `tests/` at repository root (monolito Laravel 13 heredado de `001-entorno-datos`)

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Rama de trabajo y entorno verificable sobre el proyecto Laravel existente (001–006)

- [ ] T001 Crear la rama `feature/panel-biblioteca` y verificar que el proyecto Laravel 13 existente funciona (`composer install`, `php artisan --version`, `php artisan migrate` sin errores) en la raíz del repositorio
- [ ] T002 [P] Verificar que `php.xml`/`phpunit.xml` de la raíz apunta a la base de datos de prueba MySQL configurada para `RefreshDatabase` (heredado D-008); corregir `DB_DATABASE` de prueba si faltara

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: El único cambio de esquema de `007` y la verificación de las relaciones heredadas de `001` — MUST complete before ANY user story work

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [ ] T003 Crear la migración de colación `database/migrations/2026_10_26_XXXXXX_alter_categorias_nombre_collation.php` que aplica `utf8mb4_0900_ai_ci` sobre `categorias.nombre` mediante `Schema::table('categorias', fn (Blueprint $t) => $t->string('nombre')->collation('utf8mb4_0900_ai_ci')->change())`; la migración debe ser **posterior** en orden cronológico a la creación de `categorias` de `001` (D-021, data-model.md §Cambios de esquema); si el motor es MariaDB, usar `utf8mb4_uca1400_ai_ci` (10.11+) o `utf8mb4_unicode_520_ci`
- [ ] T004 Verificar (y crear solo si faltan) las relaciones Eloquent heredadas que consume el panel en `app/Models/Libro.php` (`prestamos()` hasMany, `categoria()` belongsTo), `app/Models/Categoria.php` (`libros()` hasMany) y `app/Models/Prestamo.php` (`libro()` belongsTo), incluidos `casts` de fechas en `Prestamo` (data-model.md §Relaciones)

**Checkpoint**: Foundation ready — user story implementation can now begin

---

## Phase 3: User Story 1 - Consultar indicadores del panel (Priority: P1) 🎯 MVP

**Goal**: Una única vista `GET /panel` muestra los tres indicadores —ranking top 5 de libros más prestados, lista de préstamos vencidos y total de ejemplares disponibles por categoría— calculados con los registros vigentes al momento de la consulta, con estados vacíos «No hay datos disponibles» (FR-001…FR-008)

**Independent Test**: Preparar libros, categorías y préstamos con resultados conocidos (factories de `001`), abrir `/panel` y comparar cada uno de los tres indicadores con el cálculo manual según FR-004/FR-005 (quickstart.md V-001…V-008)

### Tests for User Story 1 (SÍ solicitados — D-026) ⚠️

> **NOTE: Write these tests FIRST, ensure they FAIL before implementation** (`php artisan test` debe mostrarlas en rojo)

- [ ] T005 [P] [US1] Crear `tests/Feature/PanelRankingTest.php`: conteo correcto por libro sobre el historial completo (devueltos y vencidos cuentan), top 5 máximo sin relleno de libros de cuenta 0, desempate alfabético del título con tildes/mayúsculas y por ISBN, determinismo entre consultas (FR-001, FR-004, D-019, V-002)
- [ ] T006 [P] [US1] Crear `tests/Feature/PanelVencidosTest.php`: regla estricta (prevista = hoy NO vence; prevista < hoy SÍ), exclusión de devueltos incluso con retraso, campos mostrados (libro, estudiante, carné, fecha de préstamo, prevista, días de retraso), orden por retraso mayor a menor con desempate por título, prevista = mañana no aparece, prevista = hoy − 1 muestra 1, un libro con varios préstamos pendientes aparece una fila por préstamo (FR-002, FR-005, D-020/D-022, V-004, V-007)
- [ ] T007 [P] [US1] Crear `tests/Feature/PanelDisponibilidadTest.php`: suma de `ejemplares_disponibles` por categoría (no `ejemplares_totales`), categorías con libros y 0 disponibles visibles con cifra 0, categorías sin libros ocultas, orden alfabético español ignorando tildes/mayúsculas (V-006: «Álgebra» ordena como «Algebra») (FR-003, FR-008, D-021)
- [ ] T008 [P] [US1] Crear `tests/Feature/PanelEstadosVaciosTest.php`: con base migrada sin sembrar, cada sección muestra exactamente «No hay datos disponibles»; con datos parciales, solo la sección vacía muestra el mensaje y las demás se renderizan (FR-008, D-024, V-003, V-005)
- [ ] T009 [P] [US1] Crear `tests/Feature/PanelLayoutTest.php`: `GET /panel` responde 200 sin autenticación (D-018), usa el layout común con la misma cabecera/navegación/pie que `/catalogo`, incluye el enlace «Panel» a `route('panel.index')`, contiene las tres secciones en una sola vista, muestra las cifras junto a cada elemento, y el HTML no contiene formularios ni botones/enlaces de escritura (FR-006, FR-007, D-018/D-025, V-008)

### Implementation for User Story 1

- [ ] T010 [US1] Crear `app/Http/Controllers/PanelController.php` con la acción `index` que evalúa `$fechaConsulta = today()` **una sola vez** (D-022) y ejecuta exactamente tres consultas Eloquent (D-023), sin SQL concatenado: (1) ranking — `Libro::has('prestamos')->withCount('prestamos')->orderByDesc('prestamos_count')->orderBy('titulo')->orderBy('isbn')->limit(5)->get()` (D-019); (2) vencidos — `Prestamo::query()->with('libro')->whereNull('fecha_devolucion_real')->where('fecha_devolucion_prevista', '<', $fechaConsulta)->join('libros', 'libros.id', '=', 'prestamos.libro_id')->orderBy('fecha_devolucion_prevista')->orderBy('libros.titulo')->orderBy('prestamos.id')->get(['prestamos.*'])`, calculando días de retraso = `$fechaConsulta->diffInDays($p->fecha_devolucion_prevista)` en PHP (D-020); (3) disponibilidad — `Categoria::query()->withCount('libros')->withSum('libros as ejemplares_disponibles', 'ejemplares_disponibles')->orderBy('nombre')->having('libros_count', '>', 0)->get()` (D-021); pasar los resultados ya calculados a la vista, que solo presenta (D-023)
- [ ] T011 [US1] Registrar la ruta `GET /panel` → `PanelController@index` con nombre `panel.index` en `routes/web.php`, sin parámetros ni middleware de autenticación propio (D-018, contracts/http-endpoints.md §Rutas; métodos de escritura quedan en 405 por defecto de Laravel)
- [ ] T012 [P] [US1] Crear `resources/views/panel/index.blade.php` que extiende `resources/views/layouts/app.blade.php` y presenta en orden: título «Panel de la biblioteca», sección «Libros más prestados» (columnas «N.º», «Título», «Autor», «Préstamos»), sección «Préstamos vencidos» (columnas «Libro», «Estudiante», «Carné», «Fecha de préstamo», «Devolución prevista», «Días de retraso») y sección «Ejemplares disponibles por categoría» (columnas «Categoría», «Ejemplares disponibles»); cada sección sin filas muestra exactamente «No hay datos disponibles»; toda la salida escapada con `{{ }}`; sin formularios ni botones de acción (contracts/ui-structure.md, contracts/user-messages.md, D-024/D-025)
- [ ] T013 [US1] Añadir el enlace «Panel» → `route('panel.index')` a la navegación común en `resources/views/layouts/app.blade.php`, distinguiendo visualmente la página activa; cabecera y pie permanecen idénticos a los del catálogo (FR-007, D-025, contracts/ui-structure.md §1)
- [ ] T014 [US1] Añadir las reglas de estilo de las tres secciones/tablas del panel al CSS propio del proyecto en `public/css/` (archivo del proyecto existente; sin frameworks CSS), usadas por las clases de `resources/views/panel/index.blade.php` (D-025)
- [ ] T015 [US1] Ejecutar `php artisan test` y corregir hasta que las 5 suites `PanelRankingTest`, `PanelVencidosTest`, `PanelDisponibilidadTest`, `PanelEstadosVaciosTest` y `PanelLayoutTest` queden en verde junto con las suites de fases previas (D-026, quickstart.md V-009)

**Checkpoint**: At this point, User Story 1 should be fully functional and testable independently — MVP entregable

---

## Phase 4: Polish & Cross-Cutting Concerns

**Purpose**: Documentación, validación manual y calidad transversal

- [ ] T016 [P] Actualizar `README.md` (documentación oficial): documentar el panel, sus reglas (top 5 con historial completo, vencidos por fecha estricta, disponibilidad por categoría en orden alfabético español), la migración de colación, y añadir la captura de pantalla del panel (Constitución §Flujo de desarrollo, plan.md G9)
- [ ] T017 [P] Ejecutar la validación manual de `specs/007-panel-biblioteca/quickstart.md` escenarios V-001 a V-008 con `php artisan serve` y corregir discrepancias (SC-001…SC-004)
- [ ] T018 [P] Revisión de seguridad y limpieza: salida escapada con `{{ }}` en toda la vista, sin SQL concatenado, sin escrituras ni rutas de eliminación, sin credenciales en el repo, sin dependencias CSS nuevas sin declarar en el README (Constitución Principios IV/V, plan.md G4/G7)
- [ ] T019 Registrar los cambios en la rama `feature/panel-biblioteca` con Conventional Commits (un commit por tarea o grupo lógico)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — can start immediately
- **Foundational (Phase 2)**: Depends on Setup completion — BLOCKS User Story 1
- **User Story 1 (Phase 3)**: Depends on Foundational (T003 migración de colación, T004 relaciones)
- **Polish (Phase 4)**: Depends on User Story 1 being complete

### User Story Dependencies

- **User Story 1 (P1)**: Can start after Foundational (Phase 2). Es la única user story de la spec — entrega el MVP completo del panel.

### Within Each User Story

- Tests (T005–T009) MUST be written and FAIL before implementation (T010–T014)
- Controller (T010) before route (T011); route (T011) before nav link (T013, necesita `panel.index`)
- View (T012) and controller (T010) can be built in parallel (different files)
- CSS (T014) after view (T012, define las clases)
- Full suite green (T015) last

### Task-Level Dependency Notes

- T003, T004 → bloquean T005–T015
- T010 → bloquea T011 y T015
- T011 → bloquea T013 y T015
- T012 → bloquea T014 y T015
- T005–T009 sin dependencias entre sí (archivos distintos)

### Parallel Opportunities

- T002 ∥ T001 (tras crear la rama)
- T005 ∥ T006 ∥ T007 ∥ T008 ∥ T009 (5 suites de test, archivos distintos)
- T010 ∥ T012 (controlador y vista, archivos distintos)
- T016 ∥ T017 ∥ T018 (documentación, validación manual y revisión, archivos/acciones distintos)

---

## Parallel Example: User Story 1

```bash
# Launch all tests for User Story 1 together (D-026):
Task: "Crear tests/Feature/PanelRankingTest.php"
Task: "Crear tests/Feature/PanelVencidosTest.php"
Task: "Crear tests/Feature/PanelDisponibilidadTest.php"
Task: "Crear tests/Feature/PanelEstadosVaciosTest.php"
Task: "Crear tests/Feature/PanelLayoutTest.php"

# Then launch core implementation together:
Task: "Crear app/Http/Controllers/PanelController.php (index con 3 consultas)"
Task: "Crear resources/views/panel/index.blade.php (tres secciones + estados vacíos)"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup (rama + entorno)
2. Complete Phase 2: Foundational (CRITICAL — migración de colación y relaciones)
3. Complete Phase 3: User Story 1 — tests primero (T005–T009 en rojo), luego controlador, ruta, vista, CSS y verde completo (T010–T015)
4. **STOP and VALIDATE**: `php artisan test` en verde + V-001 manual en `/panel`
5. Deploy/demo if ready

### Incremental Delivery

1. Setup + Foundational → esquema y relaciones listos
2. Add User Story 1 (los tres indicadores, una vista) → Test independently → Deploy/Demo (MVP! — el panel completo de la Fase 2)
3. Add Polish → README, validación quickstart, revisión de seguridad

### Notes

- [P] tasks = different files, no dependencies
- [Story] label maps task to specific user story for traceability
- El panel es de **solo lectura**: no se implementan formularios, validaciones de entrada ni escrituras (Principio II se aplica en fases con formularios; FR-006)
- Fecha de consulta única por petición vía `today()` (D-022) — nunca aceptar `?fecha=` del usuario
- Commit after each task or logical group
- Avoid: calcular indicadores en la vista, filtrar vencidos por la columna `estado`, rellenar el ranking con libros de cuenta 0, mensajes distintos de «No hay datos disponibles»
