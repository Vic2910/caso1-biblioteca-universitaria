---

description: "Task list template for feature implementation"
---

# Tasks: Registro, consulta y devolución de préstamos (004-gestion-prestamos)

**Input**: Design documents from `/specs/004-gestion-prestamos/`

**Prerequisites**: plan.md (required), spec.md (required for user stories), research.md, data-model.md, contracts/

**Tests**: Sí solicitados explícitamente — D-025 define 5 suites de característica y quickstart.md exige `php artisan test` en verde (V-014). Se incluyen tareas de test al inicio de cada historia.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

## Path Conventions

- **Single project**: `app/`, `routes/`, `resources/`, `tests/` at repository root (monolito Laravel 13 heredado de `001-entorno-datos`, ver plan.md §Project Structure)
- **Sin cambios de esquema**: la tabla `prestamos` y su FK `restrictOnDelete()` ya existen desde `001`; **004 no añade migraciones** (D-018…D-027)

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Verificar el entorno heredado de `001`/`002` antes de tocar código de préstamos

- [ ] T001 Crear la rama `feature/prestamos` desde la rama con el trabajo de `001-entorno-datos` y `002-consulta-catalogo` (raíz del repositorio, Conventional Commits)
- [ ] T002 [P] Verificar la línea base: ejecutar `composer install` y `php artisan migrate --seed` en la raíz del proyecto y confirmar que `database/seeders/` siembra los 3 préstamos de prueba (1 `activo`, 1 `devuelto`, 1 `vencido`) y ≥ 15 libros con disponibilidad coherente, y que `php artisan test` pasa con las suites de `001` y `002`

**Checkpoint**: Entorno y datos de prueba listos (quickstart.md §Requisitos previos)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Rutas, servicio central y navegación que TODAS las historias necesitan

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [ ] T003 Registrar las 5 rutas de la tabla D-018 en `routes/web.php`: `GET /prestamos` → `prestamos.index`, `GET /prestamos/crear` → `prestamos.create`, `POST /prestamos` → `prestamos.store`, `GET /prestamos/{id}/devolver` → `prestamos.form-devolucion`, `POST /prestamos/{id}/devolver` → `prestamos.devolver`, apuntando a `PrestamoController` (app/Http/Controllers/PrestamoController.php); **sin rutas PUT/PATCH/DELETE** (FR-007, contrato http-endpoints.md §Restricciones)
- [ ] T004 [P] Añadir helpers de estado al modelo `Prestamo` en `app/Models/Prestamo.php` sin tocar columnas: `devuelto(): bool`, `noDevuelto(): bool` (estado `activo` o `vencido`) y casts de `fecha_prestamo`/`fecha_devolucion_prevista`/`fecha_devolucion_real` a `date` si faltan (data-model.md §Entidad de escritura)
- [ ] T005 [P] Crear el núcleo de `PrestamoService` en `app/Services/Prestamos/PrestamoService.php` con los métodos compartidos: `sincronizarVencidos(): void` (Eloquent: `Prestamo::where('estado', 'activo')->whereNull('fecha_devolucion_real')->whereDate('fecha_devolucion_prevista', '<', today())->update(['estado' => 'vencido'])` — estricto `<`, vencido **desde el día siguiente**, jamás toca filas `devuelto`, D-020), `libroDisponible(Libro $libro): bool` (`ejemplares_disponibles >= 1`) y `prestamosNoDevueltos(string $carnet): int` (carné con `trim()` exclusivo y `estado IN ('activo','vencido')`, D-023/D-026); los métodos `registrar()` y `devolver()` se implementan en las fases US1/US2 sobre este mismo fichero
- [ ] T006 Añadir el enlace «Préstamos» → `route('prestamos.index')` a la navegación de `resources/views/layouts/app.blade.php` con estado activo diferenciado (D-018, ui-structure.md §1; requiere T003)

**Checkpoint**: Foundation ready - user story implementation can now begin in parallel

---

## Phase 3: User Story 1 - Registrar un préstamo (Priority: P1) 🎯 MVP

**Goal**: Registrar el préstamo de un libro a un estudiante (nombre, carné, fechas), descontando exactamente un ejemplar disponible y rechazando sin disponibilidad, límite de 3 o fechas inválidas

**Independent Test**: Con un libro disponible y un estudiante sin préstamos activos, registrar un préstamo y comprobar el registro y la disponibilidad (quickstart V-001); los rechazos V-003…V-007 no modifican la disponibilidad

### Tests for User Story 1

> **NOTE: Write these tests FIRST, ensure they FAIL before implementation**

- [ ] T007 [P] [US1] Crear `tests/Feature/PrestamoRegistroTest.php`: registro válido (fila creada con estado `activo`, disponibilidad −1 exacta, flash «Préstamo registrado correctamente.», 302 → `prestamos.index`), devolución prevista igual/anterior a la fecha de préstamo con error en español junto al campo y valores conservados con `old()`, `libro_id` inexistente → «El libro seleccionado no existe.», formulario vacío → errores en español junto a cada campo obligatorio (V-001, V-005, V-006)
- [ ] T008 [P] [US1] Crear `tests/Feature/PrestamoReglasTest.php`: libro sin disponibilidad rechazado con «No hay ejemplares disponibles de este libro.» junto al campo Libro sin cambiar la disponibilidad; límite de 3 contando `activo` **+ `vencido`** rechazado con «El estudiante ya tiene el máximo de tres préstamos sin devolver.»; carné con espacios sobrantes (`" 12345 "`) cuenta como el mismo estudiante; carrera determinista por el último ejemplar (dos registros consecutivos → segundo rechazado, disponibilidad 0, **nunca negativa**) (V-003, V-004, V-007, V-008)

### Implementation for User Story 1

- [ ] T009 [P] [US1] Crear `app/Http/Requests/RegistrarPrestamoRequest.php`: `authorize()` → `true`; reglas `libro_id: required|integer|exists:libros,id`, `nombre_estudiante: required|string|min:2|max:255`, `carnet_estudiante: required|string|max:50` con `trim()` en `prepareForValidation()` (D-023), `fecha_prestamo: required|date`, `fecha_devolucion_prevista: required|date|after:fecha_prestamo`; en `withValidator()` delegar en `PrestamoService` la disponibilidad (`libro_id` → «No hay ejemplares disponibles de este libro.») y el límite de 3 (`carnet_estudiante` → «El estudiante ya tiene el máximo de tres préstamos sin devolver.»); `messages()`/`attributes()` con TODOS los textos en español de contracts/user-messages.md §2 (depende de T005)
- [ ] T010 [US1] Implementar `PrestamoService::registrar(array $datosValidados): Prestamo` en `app/Services/Prestamos/PrestamoService.php`: `DB::transaction()` con `Libro::whereKey($id)->lockForUpdate()->first()`, comprobación `ejemplares_disponibles >= 1` (rechazo R1), re-verificación `prestamosNoDevueltos($carnet) < 3` (rechazo R2, vencidos cuentan), decremento **condicional** (`WHERE ejemplares_disponibles > 0`, nunca baja de cero, D-022), creación del préstamo con estado `activo` y llamada a `sincronizarVencidos()` (depende de T005, T009)
- [ ] T011 [P] [US1] Crear la vista `resources/views/prestamos/create.blade.php` según ui-structure.md §3: extiende `layouts/app.blade.php`, título «Registrar préstamo», `POST` → `prestamos.store` con `@csrf`, campos en orden (select `libro_id` con opción «{título} ({ISBN}) — disponibles: {n}» por libro ordenado **incluidos los agotados** y opción por defecto «Seleccione un libro», `nombre_estudiante`, `carnet_estudiante`, `fecha_prestamo` con valor por defecto hoy, `fecha_devolucion_prevista`), errores en español junto a **cada** campo con `old()` incluida la re-selección del `<select>`, botón «Registrar préstamo» y enlace «Volver a préstamos» (depende de T009 por los nombres de campo)
- [ ] T012 [US1] Implementar `@create` y `@store` en `app/Http/Controllers/PrestamoController.php`: `@create` carga `Libro::orderBy('titulo')->get()` para el selector y `fecha_prestamo` por defecto hoy; `@store` usa `$request->validated()` → `PrestamoService::registrar()`, invoca `sincronizarVencidos()` y redirige a `prestamos.index` con flash «Préstamo registrado correctamente.»; controlador delgado, sin reglas de negocio (Principio III, depende de T010, T011)

**Checkpoint**: At this point, User Story 1 should be fully functional and testable independently

---

## Phase 4: User Story 2 - Registrar una devolución (Priority: P1)

**Goal**: Registrar la devolución con fecha real, pasar el estado a `devuelto`, incrementar la disponibilidad exactamente una vez y rechazar la devolución repetida

**Independent Test**: Devolver un préstamo activo conocido y comprobar el estado, la fecha real y la disponibilidad +1 (quickstart V-002); devolver dos veces no suma dos veces (V-008)

### Tests for User Story 2

> **NOTE: Write these tests FIRST, ensure they FAIL before implementation**

- [ ] T013 [P] [US2] Crear `tests/Feature/PrestamoDevolucionTest.php`: devolución de préstamo `activo` (estado → `devuelto`, `fecha_devolucion_real` fijada, disponibilidad **+1 exactamente una vez**, flash «Devolución registrada correctamente.», fila sin botón de devolución); devolución de préstamo `vencido` permitida con fecha real ≥ fecha de préstamo; devolución repetida → «Este préstamo ya fue devuelto.» sin volver a sumar; fecha real vacía o anterior a la fecha de préstamo → error junto al campo con `old()`; `{id}` inexistente → 404 «Préstamo no encontrado»; `PUT/DELETE /prestamos/{id}` → 404/405 (V-002, V-008, V-009, V-012)
- [ ] T014 [P] [US2] Crear `app/Http/Requests/DevolverPrestamoRequest.php`: `authorize()` → `true`; `fecha_devolucion_real: required|date` y en `withValidator()` comparación contra el préstamo en curso `on_or_after:fecha_prestamo` (la regla cruza entrada y registro existente, se evalúa con el modelo, D-021); `messages()`/`attributes()` en español según contracts/user-messages.md §3 («La fecha de devolución real es obligatoria.», «La fecha de devolución real no es válida.», «La fecha de devolución real no puede ser anterior a la fecha de préstamo.»)

### Implementation for User Story 2

- [ ] T015 [US2] Implementar `PrestamoService::devolver(Prestamo $p, string $fechaReal): Prestamo` en `app/Services/Prestamos/PrestamoService.php`: `DB::transaction()` con `lockForUpdate()` sobre el préstamo; si ya está `devuelto` rechaza **sin tocar la disponibilidad** con «Este préstamo ya fue devuelto.» (M2); si no, fija `fecha_devolucion_real`, `estado` → `devuelto` e `increment('ejemplares_disponibles')` exactamente una vez; llama a `sincronizarVencidos()` (R4, R8, depende de T005)
- [ ] T016 [P] [US2] Crear la vista `resources/views/prestamos/devolucion.blade.php` según ui-structure.md §4: extiende `layouts/app.blade.php`, título «Registrar devolución», resumen de solo lectura (libro título + ISBN, estudiante, carné, fecha de préstamo, devolución prevista, estado actual), campo `fecha_devolucion_real` con error junto al campo y `old()`, botón «Registrar devolución» (`POST` → `prestamos.devolver` con `@csrf`), enlace «Volver a préstamos»; si el préstamo ya está `devuelto`, muestra «Este préstamo ya fue devuelto.» junto a la acción y **sin** botón de envío (depende de T014)
- [ ] T017 [US2] Implementar `@devolver` y `@registrarDevolucion` en `app/Http/Controllers/PrestamoController.php`: `Prestamo::find($id)` → `null` hace `abort(404, 'Préstamo no encontrado')`; `@devolver` invoca `sincronizarVencidos()` y muestra el formulario (o el mensaje M2 sin botón si ya está `devuelto`); `@registrarDevolucion` usa `$request->validated()` → `PrestamoService::devolver()` y redirige a `prestamos.index` con flash «Devolución registrada correctamente.»; el rechazo de devolución repetida vuelve al formulario con el error junto a la acción (depende de T015, T016)
- [ ] T018 [P] [US2] Revisar/crear `resources/views/errors/404.blade.php` para que use `layouts/app.blade.php` y muestre «Préstamo no encontrado» con el mensaje controlado en español y enlace «Volver a préstamos» → `prestamos.index` (mismo patrón D-013 de `002`, contrato http-endpoints.md §Respuestas de error)

**Checkpoint**: At this point, User Stories 1 AND 2 should both work independently

---

## Phase 5: User Story 3 - Consultar préstamos (Priority: P2)

**Goal**: Listar los préstamos paginados (10/página) con libro, estudiante, fechas y estado actual (`activo`/`devuelto`/`vencido`) y la acción de devolución donde corresponde

**Independent Test**: Con los 3 préstamos sembrados, abrir `/prestamos` y comprobar datos, estados, orden, paginación y estado vacío (quickstart V-010)

### Tests for User Story 3

> **NOTE: Write these tests FIRST, ensure they FAIL before implementation**

- [ ] T019 [P] [US3] Crear `tests/Feature/PrestamoListadoTest.php`: 200 en `GET /prestamos` mostrando libro (título + ISBN), nombre, carné, las tres fechas (devolución real «—» si `NULL`) y badge de estado correcto; 10 préstamos por página con paginación navegable; orden determinista `fecha_prestamo` DESC → `id` DESC; botón «Registrar devolución» solo en filas `activo`/`vencido`; base sin préstamos → «No hay préstamos registrados aún» sin controles de paginación; cabecera, navegación (con «Préstamos») y pie idénticos a `/catalogo` (V-010, V-013)

### Implementation for User Story 3

- [ ] T020 [US3] Implementar `@index` en `app/Http/Controllers/PrestamoController.php`: primero `PrestamoService::sincronizarVencidos()` (D-020), luego `Prestamo::with('libro')->orderByDesc('fecha_prestamo')->orderByDesc('id')->paginate(10)->withQueryString()` y devolver la vista con los préstamos y el flash de éxito (sin filtros ni búsqueda: eso es de `006`, D-024; depende de T005)
- [ ] T021 [P] [US3] Crear la vista `resources/views/prestamos/index.blade.php` según ui-structure.md §2: extiende `layouts/app.blade.php`, título «Préstamos registrados», flash junto al título, estado vacío «No hay préstamos registrados aún» sin tabla ni paginación, tabla con las 8 columnas en orden (Libro, Estudiante, Carné, Fecha de préstamo, Devolución prevista, Devolución real o «—», Estado, Acción), badge de estado con texto «Activo»/«Vencido»/«Devuelto» y colores diferenciados con **CSS propio** (sin frameworks CSS), enlace «Registrar devolución» → `prestamos.form-devolucion` **solo** en filas `activo`/`vencido`, textos de datos escapados con `{{ }}`
- [ ] T022 [P] [US3] Crear la vista de paginación `resources/views/pagination/prestamos.blade.php` con CSS propio, análoga a `resources/views/pagination/catalogo.blade.php` de `002` (D-011/D-024): «Anterior», «Siguiente» y números de página, 10 por página

**Checkpoint**: All user stories should now be independently functional

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Mejoras y validación transversales a todas las historias

- [ ] T023 [P] Crear `tests/Feature/PrestamoVencimientoTest.php` (D-025): un préstamo con `fecha_devolucion_prevista` = hoy sigue `activo`; con prevista = ayer pasa a `vencido` **sin intervención manual**; el estado se evalúa al **registrar**, al **devolver** y al **listar**; los `devuelto` jamás cambian (V-011, FR-002, data-model.md §Transiciones de estado)
- [ ] T024 [P] Verificar la inmutabilidad FR-007 en `routes/web.php`: no existe ninguna ruta `PUT`/`PATCH`/`DELETE` de préstamos ni vistas de edición/eliminación; cualquier modificación externa queda fuera de alcance (V-012)
- [ ] T025 Ejecutar la suite completa `php artisan test` en la raíz del proyecto y validar los escenarios manuales V-001…V-013 de `specs/004-gestion-prestamos/quickstart.md`; corregir cualquier regresión (las 5 suites de `004` deben estar en verde junto con las de `001` y `002`, V-014)
- [ ] T026 [P] Actualizar `README.md` (documentación oficial, gate G9): documentar el flujo de préstamos, las reglas (límite de 3 no devueltos contando vencidos, vencido desde el día siguiente, disponibilidad ±1 exacta, inmutabilidad), las 5 rutas y añadir las capturas de listado, registro y devolución
- [ ] T027 [P] Añadir los comentarios `[CONCEPTO]` que documentan las reglas de préstamo en `app/Services/Prestamos/PrestamoService.php` y los formularios nuevos, siguiendo la convención de `001`/`002` (constitución v1.0, §Flujo de desarrollo)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies - can start immediately
- **Foundational (Phase 2)**: Depends on Setup completion - BLOCKS all user stories
  - T003 → T006 (el enlace de navegación necesita el nombre de ruta)
  - T004 y T005 en paralelo (ficheros distintos)
- **User Stories (Phase 3+)**: All depend on Foundational phase completion
  - US1 y US2 (ambas P1) pueden ejecutarse en paralelo: comparten ficheros pero por separado son verificables; si trabaja el mismo equipo en un fichero, ejecutarlas en orden US1 → US2
  - US3 depende de T005 (sincronización) pero no de la lógica de US1/US2; se ejecuta después por prioridad (P2) y porque el redirect de éxito de US1/US2 apunta a su listado
- **Polish (Final Phase)**: Depends on all desired user stories being complete (T023 requiere `store`, `registrarDevolucion` e `index`)

### User Story Dependencies

- **User Story 1 (P1)**: Can start after Foundational (Phase 2) - No dependencies on other stories
- **User Story 2 (P1)**: Can start after Foundational (Phase 2) - Independiente de US1 (préstamos de prueba sembrados en `001` sirven como base); integra con US1 solo en el listado común
- **User Story 3 (P2)**: Can start after Foundational (Phase 2) - Independiente; consume los datos que crean US1/US2 pero funciona con los 3 préstamos sembrados

### Within Each User Story

- Tests written FIRST and FAIL before implementation
- Requests/validación antes que el servicio; servicio antes que el controlador; controlador antes que la vista de integración (o en paralelo cuando son ficheros distintos)
- Story complete before moving to next priority

### Parallel Opportunities

- T002 ∥ con cualquier tarea de la Fase 1
- T004 ∥ T005 (Fase 2, ficheros distintos)
- T007 ∥ T008 ∥ T009 (US1: dos tests + Form Request, tres ficheros distintos)
- T013 ∥ T014 (US2: test + Form Request)
- T016 ∥ T018 (US2: vista de devolución + vista 404)
- T019 ∥ T021 ∥ T022 (US3: test + vista index + vista paginación)
- T023 ∥ T026 ∥ T027 (Polish: test de vencimiento + README + comentarios)
- Si el equipo está repartido: dev A ⇒ US1, dev B ⇒ US2, dev C ⇒ US3 tras la Fase 2

---

## Parallel Example: User Story 1

```bash
# Launch all tests for User Story 1 together:
Task: "Crear tests/Feature/PrestamoRegistroTest.php"
Task: "Crear tests/Feature/PrestamoReglasTest.php"

# Launch independent implementation files together:
Task: "Crear app/Http/Requests/RegistrarPrestamoRequest.php"
Task: "Crear resources/views/prestamos/create.blade.php"

# Then the dependent service + controller sequentially:
Task: "Implementar PrestamoService::registrar() en app/Services/Prestamos/PrestamoService.php"
Task: "Implementar @create/@store en app/Http/Controllers/PrestamoController.php"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup
2. Complete Phase 2: Foundational (CRITICAL - blocks all stories)
3. Complete Phase 3: User Story 1 (tests en verde: V-001, V-003…V-007)
4. **STOP and VALIDATE**: Test User Story 1 independently (`php artisan test --filter=Prestamo`)
5. Deploy/demo if ready (`php artisan serve` → `http://127.0.0.1:8000/prestamos/crear`)

### Incremental Delivery

1. Complete Setup + Foundational → Foundation ready
2. Add User Story 1 (registro) → Test independently → Deploy/Demo (MVP!)
3. Add User Story 2 (devolución) → Test independently → Deploy/Demo (ciclo completo: disponibilidad −1/+1)
4. Add User Story 3 (listado) → Test independently → Deploy/Demo (supervisión con estados)
5. Polish → suite completa en verde + README + quickstart V-001…V-014
6. Each story adds value without breaking previous stories

### Parallel Team Strategy

With multiple developers:

1. Team completes Setup + Foundational together (T003 es la tarea crítica)
2. Once Foundational is done:
   - Developer A: User Story 1 (registro + reglas)
   - Developer B: User Story 2 (devolución)
   - Developer C: User Story 3 (listado)
3. Stories complete and integrate independently (T012/T017/T020 tocan el mismo controlador: coordinar commits por historia)

---

## Notes

- [P] tasks = different files, no dependencies
- [Story] label maps task to specific user story for traceability
- Each user story is independently completable and testable
- Tests fail before implementing (TDD solicitado en D-025)
- Commit after each task or logical group (Conventional Commits, rama `feature/prestamos`)
- Stop at any checkpoint to validate story independently
- **Sin migraciones**: `004` no crea esquema; si aparece la necesidad de una columna, es un error de alcance (plan.md §Technical Context)
- Mensajes literales M1/M2 y textos en español de `contracts/user-messages.md` no se traducen ni se reescriben
