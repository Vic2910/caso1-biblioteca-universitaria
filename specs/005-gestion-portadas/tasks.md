---

description: "Task list for 005-gestion-portadas (Gestión de portadas de libros)"
---

# Tasks: Gestión de portadas de libros (005-gestion-portadas)

**Input**: Design documents from `/specs/005-gestion-portadas/`

**Prerequisites**: plan.md, spec.md, research.md (D-018…D-026), data-model.md, contracts/ (http-endpoints.md, ui-structure.md, user-messages.md), quickstart.md

**Tests**: Sí — el diseño los pide explícitamente: `plan.md` §Source Code nombra las cinco suites (`PortadaSubidaTest`, `PortadaEliminacionTest`, `PortadaAccesoTest`, `PortadaFalloAlmacenamientoTest`, `PortadaConcurrenciaTest`) y `research.md` D-026 fija la estrategia (`RefreshDatabase` + `Storage::fake('public')` + mocks de fallo). Las suites de cada historia se escriben ANTES de su implementación y deben fallar antes de implementar.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2)
- Include exact file paths in descriptions

## Path Conventions

- **Single project**: `app/`, `routes/`, `resources/`, `tests/` at repository root (monolito Laravel 13, per `plan.md` §Source Code)
- All paths below are relative to the repository root unless absolute

## ⚠️ Prerequisitos heredados (001, 002, 003)

Este repositorio puede contener hoy solo la documentación de `specs/`. Esta funcionalidad reutiliza sin duplicar:

- **`001-entorno-datos`**: aplicación Laravel 13, modelo `app/Models/Libro.php`, columna `libros.portada varchar(255) NULL` (0 migraciones nuevas en `005`, data-model §Cambios de esquema), imagen predeterminada `public/images/libro-sin-portada.svg`, layout `resources/views/layouts/app.blade.php`, entorno de pruebas PHPUnit/MySQL.
- **`002-consulta-catalogo`**: parcial `resources/views/partials/portada.blade.php`, vistas de listado y detalle, vista `resources/views/errors/404.blade.php`.
- **`003-gestion-libros`**: `app/Http/Controllers/LibroController.php` (acciones `update`/`destroy`), Form Request `app/Http/Requests/LibroUpdateRequest.php`, formulario `resources/views/libros/edit.blade.php` + parcial `resources/views/libros/_form.blade.php`, `lang/es/validation.php`, rutas de libros en `routes/web.php`.
- **`008-interfaz-validacion-seguridad`** (opcional): solo aporta la ruta `login` para el enlace de la vista 401 (D-023); `005` funciona y se prueba sin él (`assertStatus(401)`).

Si algún artefacto falta, completar la fase correspondiente antes de continuar (plan.md §Dependencias entre features).

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Rama de trabajo y verificación de los prerrequisitos heredados de `001`–`003`

- [ ] T001 Crear la rama `feature/portadas` y verificar los prerrequisitos: ejecutar `php artisan test` con las suites de `001`–`003` en verde y confirmar la presencia de `app/Http/Controllers/LibroController.php` (acciones `update`/`destroy`), `app/Http/Requests/LibroUpdateRequest.php`, `resources/views/libros/edit.blade.php`, `resources/views/libros/_form.blade.php`, `resources/views/partials/portada.blade.php`, `resources/views/errors/404.blade.php`, `public/images/libro-sin-portada.svg` y la columna `libros.portada varchar(255) NULL` en la BD migrada (`php artisan migrate --seed`); si falta algo, detenerse y completar `001`–`003` (data-model §Cambios de esquema: cero migraciones propias)
- [ ] T002 [P] Verificar que el contenido de `storage/app/public/` —en particular `storage/app/public/portadas/` creado en runtime— queda fuera de Git por el `.gitignore` heredado de `001`, y dejar constancia en el código/documentación de que **no** se requiere `php artisan storage:link` porque las portadas se sirven por `GET /portadas/{id}` (D-018)

**Checkpoint**: Prerrequisitos de `001`–`003` presentes y verdes; la fundación puede empezar

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Ruta de lectura de portadas (con sesión) y reconciliación de directorio — componentes compartidos por AMBAS historias: toda historia debe poder verificar la imagen en listado/detalle y garantizar «cero huérfanos»

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [ ] T003 [P] Crear la vista `resources/views/errors/401.blade.php` que extienda `resources/views/layouts/app.blade.php`: mensaje «Debe iniciar sesión para ver las imágenes de portadas.» y enlace «Iniciar sesión» hacia `route('login')` solo si esa ruta existe (`@if (Route::has('login'))`); no revela si la portada existía ni detalles del archivo (D-023, contracts/user-messages.md §5)
- [ ] T004 Crear el controlador `app/Http/Controllers/PortadaController.php` con la acción `show`: ① comprobación de sesión **antes** de tocar BD o disco — sin sesión → `response()->view('errors.401', [], 401)` (nunca los bytes de la imagen); ② con sesión: libro inexistente → 404 «Libro no encontrado», `portada IS NULL` → 404 «Portada no encontrada», archivo ausente en disco → 404 (vista `errors/404`); ③ archivo presente → `Storage::disk('public')->response($ruta)` con el `Content-Type` derivado (image/jpeg/image/png/image/webp) (D-023, contracts/http-endpoints.md §3)
- [ ] T005 Registrar la ruta `GET /portadas/{libro}` con nombre `portadas.show` en `routes/web.php` apuntando a `PortadaController@show`; la ruta se declara **sin** middleware `auth` — la comprobación es propia de la acción para responder 401 en lugar de redirigir (D-023, decision de alternativas) (depends T004)
- [ ] T006 Actualizar el parcial `resources/views/partials/portada.blade.php`: con portada → `src` = `route('portadas.show', $libro)` (antes `Storage::disk('public')->url(...)`) y `alt` = `{{ $libro->titulo }}` (antes «Portada de {título}»); con `portada = NULL` → `src` = `asset('images/libro-sin-portada.svg')` y `alt` «Sin portada»; conservar el atributo `onerror` de respaldo a la predeterminada (cubre 401 y archivo caído); todo el texto renderizado con `{{ }}` (D-023, D-024, contracts/ui-structure.md §3) (depends T005)
- [ ] T007 [P] Añadir el método público `reconciliarPortada()` a `app/Models/Libro.php`: elimina de `portadas/{$this->id}` (disco `public`) todo archivo cuya ruta difiera de `$this->portada` vigente en BD; tolerante a fallos de disco (registra el error y no lanza); es el paso ⑤/③ compartido de las transiciones T1, T2 y T3 de data-model.md §Ciclo de vida (D-020)

**Checkpoint**: Ruta de entrega con sesión, parcial con `alt`/ruta nuevos y reconciliación disponibles — user story implementation can now begin

---

## Phase 3: User Story 1 - Agregar o reemplazar una portada (Priority: P1) 🎯 MVP

**Goal**: El usuario encargado asocia una portada a un libro sin portada y la reemplaza por otra desde el formulario de edición; la imagen se muestra en listado y detalle, el archivo anterior deja de existir, los rechazos (formato/tamaño/fallo de disco) muestran mensajes en español junto al campo sin alterar la portada previa.

**Independent Test**: Agregar una imagen válida a un libro sin portada y sustituirla por otra válida desde el formulario de edición, verificando ambas presentaciones (listado y detalle) y la eliminación del archivo anterior (quickstart V-001, V-002, V-003).

### Tests for User Story 1 (solicitados por plan.md §Source Code y research.md D-026) ⚠️

> **NOTE: Write these tests FIRST, ensure they FAIL before implementation**

- [ ] T008 [P] [US1] Crear `tests/Feature/PortadaSubidaTest.php` con `RefreshDatabase` + `Storage::fake('public')` cubriendo quickstart V-001/V-002/V-003 y SC-001/SC-003: JPG, PNG y WebP aceptados; archivo de **exactamente 2 MB (2048 KB)** aceptado (límite inclusivo — generar la fixture como imagen válida ampliada/padeada a 2048 KB); PNG de 3 MB rechazado; GIF, SVG y TIFF rechazados; `.jpg` cuyo contenido no es imagen rechazado (`mimes` analiza el contenido, no la extensión); mensajes exactos «La portada debe ser una imagen JPG, PNG o WebP.» y «La portada no puede superar los 2 MB.» visibles junto al campo `portada`; resto de valores del formulario conservados con `old()`; portada existente intacta tras cada rechazo; edición de otros datos sin archivo no toca `libros.portada` ni el disco (T4); tras un reemplazo el archivo anterior ya no existe y `portadas/{id}` contiene exactamente un archivo; `libros.portada` guarda la ruta relativa; mensaje de éxito «Portada actualizada correctamente.»
- [ ] T009 [P] [US1] Crear `tests/Feature/PortadaAccesoTest.php` con `Storage::fake('public')` cubriendo quickstart V-007/V-008 y SC-006/SC-007: sin sesión, `GET /portadas/{id}` → **401** con el mensaje «Debe iniciar sesión para ver las imágenes de portadas.» y **sin** bytes de la imagen; con `actingAs(...)` → 200 con los bytes correctos y `Content-Type` derivado; libro inexistente → 404 «Libro no encontrado»; `portada IS NULL` → 404 «Portada no encontrada»; archivo ausente → 404; en el listado y en el detalle, el 100 % de las portadas reales tiene `alt` = título del libro y la predeterminada `alt` «Sin portada»; sin sesión, el catálogo muestra la imagen predeterminada (fallback `onerror`), no un icono roto
- [ ] T010 [P] [US1] Crear `tests/Feature/PortadaConcurrenciaTest.php` con `Storage::fake('public')` cubriendo quickstart V-010 y SC-005: dos reemplazos superpuestos del mismo libro (el segundo captura la ruta antigua antes del primer `UPDATE`); predomina la última escritura — `libros.portada` corresponde al último `UPDATE` y `portadas/{id}` contiene **exactamente un archivo** tras cada operación, sin huérfanos
- [ ] T011 [P] [US1] Crear `tests/Feature/PortadaFalloAlmacenamientoTest.php` con los **casos de guardado** de quickstart V-009 y SC-003 (D-026: mock sobre la fachada `Storage::shouldReceive(...)` o Mockery parcial sobre `Storage::disk('public')` para que falle en guardar): 302 de vuelta con el mensaje exacto «No se pudo guardar la imagen. Inténtelo de nuevo.» junto al campo `portada`, portada previa intacta en BD y en disco, registro del libro intacto; fallo al persistir la BD tras guardar el archivo nuevo → el archivo nuevo se elimina como compensación y no queda huérfano (los casos de borrado se añaden en T016)

### Implementation for User Story 1

- [ ] T012 [US1] Ampliar `app/Http/Requests/LibroUpdateRequest.php` con la regla literal de data-model.md §Reglas de validación: `'portada' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048']`, más los mensajes personalizados en español «La portada debe ser una imagen JPG, PNG o WebP.» (para `file`/`mimes`) y «La portada no puede superar los 2 MB.» (para `max`), en el propio Form Request o en el bloque `custom` de `lang/es/validation.php` de `003`; mensajes mostrados junto al campo y demás campos conservados con `old()`; el resto de reglas de `003` sin cambios (D-019, contracts/user-messages.md §1)
- [ ] T013 [US1] Ampliar `LibroController@update` en `app/Http/Controllers/LibroController.php` para que, **solo** si `$request->hasFile('portada')`, ejecute la secuencia T1/T2 de data-model.md §Ciclo de vida: ① capturar `$rutaAnterior = $libro->portada`; ② guardar primero el archivo nuevo en `portadas/{libro->id}/{identificador_unico}.{jpg|png|webp}` del disco `public` (D-018 — adaptar cualquier subida plano heredada de `003` a este directorio por libro); ③ actualizar `libros.portada` con la nueva ruta, y si falla borrar el archivo recién guardado como compensación; ④ borrar `$rutaAnterior`; ⑤ `$libro->reconciliarPortada()`; flash de éxito «Portada actualizada correctamente.»; cualquier fallo de disco → 302 de vuelta con «No se pudo guardar la imagen. Inténtelo de nuevo.» junto al campo `portada`, sin rutas ni trazas, con la portada existente y el registro intactos; sin `hasFile` → ninguna operación de disco y `portada` sin tocar (T4, FR-001/FR-006, D-020, D-025) (depends T007, T012)
- [ ] T014 [P] [US1] Ampliar el formulario de edición en `resources/views/libros/edit.blade.php` (y, si `003` compartió el campo en `resources/views/libros/_form.blade.php`, aplicar ahí el input restringido a la edición): `enctype="multipart/form-data"`, `<label for>` «Portada», `<input type="file" name="portada" accept="image/jpeg,image/png,image/webp">`, leyenda «JPG, PNG o WebP, hasta 2 MB. Al guardar un archivo nuevo se reemplaza la portada actual.», vista previa actual con el parcial `resources/views/partials/portada.blade.php` (o `route('portadas.show', $libro)`), `@error('portada')` inmediatamente bajo el input, mensaje de éxito `session('exito')` junto al formulario, valores con `old()` tras un rechazo; **sin** controles de eliminación todavía y **ningún control** en `resources/views/catalogo/index.blade.php` ni `resources/views/catalogo/show.blade.php` (contracts/ui-structure.md §1–§2, D-024)

**Checkpoint**: At this point, User Story 1 should be fully functional and testable independently

---

## Phase 4: User Story 2 - Eliminar una portada (Priority: P2)

**Goal**: El usuario encargado elimina la portada de un libro mediante un botón con confirmación en el formulario de edición (`DELETE`, nunca GET); el archivo desaparece, la referencia se retira y el libro vuelve a mostrar la imagen predeterminada; eliminar el libro retira también su directorio de portadas.

**Independent Test**: Eliminar una portada existente mediante el botón de eliminación con confirmación del formulario de edición y comprobar que el archivo desaparece y que el libro vuelve a mostrar la imagen predeterminada en listado y detalle (quickstart V-004, V-005).

### Tests for User Story 2 (solicitados por plan.md §Source Code y research.md D-026) ⚠️

> **NOTE: Write these tests FIRST, ensure they FAIL before implementation**

- [ ] T015 [P] [US2] Crear `tests/Feature/PortadaEliminacionTest.php` con `RefreshDatabase` + `Storage::fake('public')` cubriendo quickstart V-004/V-005 y SC-002/SC-004/SC-005: `DELETE /libros/{id}/portada` a libro con portada → 302 a la edición con «Portada eliminada correctamente.», archivo borrado (`Storage::assertMissing`), `portada = NULL` y listado/detalle con la predeterminada y `alt` «Sin portada»; un `GET /libros/{id}/portada` **no** elimina nada (la ruta no existe para GET); libro sin portada → 404 «Portada no encontrada»; libro inexistente → 404 «Libro no encontrado»; la eliminación del libro (`DELETE /libros/{id}` de `003`) retira el directorio `portadas/{id}` completo y no queda ningún archivo huérfano
- [ ] T016 [P] [US2] Ampliar `tests/Feature/PortadaFalloAlmacenamientoTest.php` con los **casos de borrado** de quickstart V-009: disco mockeado para fallar al borrar el archivo en la eliminación de portada → 302 con el mensaje exacto «No se pudo guardar la imagen. Inténtelo de nuevo.» junto al campo `portada`, con el archivo y la referencia en BD intactos (SC-003)

### Implementation for User Story 2

- [ ] T017 [US2] Añadir la acción `destroy` a `app/Http/Controllers/PortadaController.php` siguiendo la transición T3 de data-model.md §Ciclo de vida: libro inexistente → 404 «Libro no encontrado»; `portada IS NULL` → 404 «Portada no encontrada» (vista `errors/404`); ① borrar el archivo — fallo de disco → 302 a la edición con «No se pudo guardar la imagen. Inténtelo de nuevo.» junto al campo y archivo + referencia intactos; ② `$libro->portada = null; $libro->save()`; ③ `$libro->reconciliarPortada()`; ④ redirigir al formulario de edición con el mensaje «Portada eliminada correctamente.» (D-021, contracts/http-endpoints.md §2, contracts/user-messages.md §2/§6)
- [ ] T018 [US2] Registrar la ruta `DELETE /libros/{id}/portada` con nombre `libros.portadas.destroy` en `routes/web.php` apuntando a `PortadaController@destroy`; la ruta **no** debe existir para GET/POST — `GET /libros/{id}/portada` no elimina nada (FR-005, Principio IV, contracts/http-endpoints.md §2) (depends T017)
- [ ] T019 [US2] Añadir en `resources/views/libros/edit.blade.php` (junto al campo `portada`) el botón «Eliminar portada», visible **solo** si el libro tiene portada, como formulario propio con `@csrf`, `@method('DELETE')` hacia `route('libros.portadas.destroy', $libro)` y `onsubmit="return confirm('¿Seguro que desea eliminar la portada de este libro? La imagen dejará de mostrarse.')"` (texto literal de contracts/user-messages.md §3); operable con teclado y con texto visible; ningún control equivalente en `resources/views/catalogo/show.blade.php` (FR-005, SC-004, D-021, D-024) (depends T018)
- [ ] T020 [P] [US2] Añadir el hook de borrado en `app/Models/Libro.php` (`booted()` → `static::deleting`) que, antes de borrar la fila, ejecute `Storage::disk('public')->deleteDirectory("portadas/{$libro->id}")` y además elimine el archivo indicado en `libros.portada` cuando quede fuera de ese directorio (portadas dadas de alta en `003` con `store('portadas')`), cubriendo FR-004/SC-005 por cualquier vía de borrado (controlador, tinker, tests, seeders); tolerante a fallos de disco — un error se registra y no impide eliminar el libro (D-022)

**Checkpoint**: At this point, User Stories 1 AND 2 should both work independently

---

## Phase 5: Polish & Cross-Cutting Concerns

**Purpose**: Documentación, validación extremo a extremo y revisión transversal (SC-001…SC-007 y gates de la constitución)

- [ ] T021 [P] Ampliar `README.md` con la sección «Gestión de portadas»: formatos aceptados (JPG/PNG/WebP), límite de 2 MB inclusivo, asociar/reemplazar/eliminar solo desde el formulario de edición, necesidad de sesión iniciada para ver las imágenes, ausencia de requisito de `php artisan storage:link`, y capturas de listado/detalle con la imagen real y la predeterminada (plan.md §Source Code, gate G9)
- [ ] T022 Ejecutar `php artisan test` completo (suites de `001`–`003` en verde + las cinco suites de `005`) y validar manualmente con `php artisan serve` los escenarios V-001 a V-010 de `specs/005-gestion-portadas/quickstart.md`
- [ ] T023 [P] Revisión transversal frente a `specs/005-gestion-portadas/checklists/requirements.md` y a SC-001…SC-007: eliminación de portada solo por `DELETE` con confirmación previa; mensajes sin rutas, trazas ni detalles técnicos; `alt` no vacío en el 100 % de las imágenes (título / «Sin portada»); `old()` conserva los valores tras cada rechazo; la comprobación de sesión ocurre antes de tocar BD/disco; 0 migraciones nuevas; ninguna vista fuera de la edición recibe controles de portada
- [ ] T024 Confirmar los cambios en la rama `feature/portadas` con Commits Conventional (uno por tarea o grupo lógico, sin archivos de `storage/app/public/`) y marcar las tareas completadas en `specs/005-gestion-portadas/tasks.md` (gate G9)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies - can start immediately
- **Foundational (Phase 2)**: Depends on Setup completion - BLOCKS all user stories
- **User Story 1 (Phase 3)**: Depends on Foundational (Phase 2) — en concreto T006 (parcial con `route('portadas.show')`) y T007 (reconciliación) para sus tests de presentación y de huérfanos
- **User Story 2 (Phase 4)**: Depends on Foundational (Phase 2) — reutiliza T007 (`reconciliarPortada()`) y el parcial de T006 para comprobar la imagen predeterminada; NO depende de la implementación de US1 (puede crear la portada de prueba directamente en BD + `Storage::fake`)
- **Polish (Final Phase)**: Depends on all desired user stories being complete

### User Story Dependencies

- **User Story 1 (P1)**: Can start after Foundational (Phase 2) - No dependencies on other stories — **MVP**
- **User Story 2 (P2)**: Can start after Foundational (Phase 2) - Independiente de US1: sus tests siembran la portada de prueba con `Storage::fake('public')` + `DB::table('libros')->update(['portada' => ...])`
- Ambas historias comparten sin embargo los archivos `routes/web.php` (T005, T018) y `resources/views/libros/edit.blade.php` (T014, T019): si se trabajan en paralelo, secuenciar esos dos ficheros o integrar por ramas distintas

### Within Each User Story

- Tests (T008–T011 / T015–T016) se escriben PRIMERO y deben FALLAR antes de implementar
- Validación (Form Request) antes que el controlador; controlador antes que las vistas que consumen sus mensajes
- Rutas antes que las vistas que generan `route(...)`
- Historia completa antes de pasar a la siguiente por prioridad

### Parallel Opportunities

- Setup: T002 en paralelo con T001
- Foundational: T003 y T007 en paralelo entre sí y con T004
- US1: las cuatro suites T008, T009, T010, T011 son archivos distintos → todas en paralelo; T014 (vista) en paralelo con T012/T013 (PHP)
- US2: T015 y T016 en paralelo; T020 (modelo) en paralelo con T017/T018/T019
- Polish: T021 y T023 en paralelo
- Con capacidad suficiente, US1 y US2 en paralelo tras la fundación (resolviendo la coordinación sobre `routes/web.php` y `edit.blade.php`)

---

## Parallel Example: User Story 1

```bash
# Launch all test suites for User Story 1 together (first, must fail):
Task: "Crear tests/Feature/PortadaSubidaTest.php (T008)"
Task: "Crear tests/Feature/PortadaAccesoTest.php (T009)"
Task: "Crear tests/Feature/PortadaConcurrenciaTest.php (T010)"
Task: "Crear tests/Feature/PortadaFalloAlmacenamientoTest.php (T011)"

# Then implementation in parallel where files differ:
Task: "Ampliar app/Http/Requests/LibroUpdateRequest.php (T012)"
Task: "Ampliar resources/views/libros/edit.blade.php (T014)"

# Sequential last (depends on T007 + T012):
Task: "Ampliar LibroController@update en app/Http/Controllers/LibroController.php (T013)"
```

---

## Parallel Example: User Story 2

```bash
# Launch test tasks together (first, must fail):
Task: "Crear tests/Feature/PortadaEliminacionTest.php (T015)"
Task: "Ampliar tests/Feature/PortadaFalloAlmacenamientoTest.php con casos de borrado (T016)"

# Implementation in parallel (files differ):
Task: "Añadir PortadaController@destroy (T017)"
Task: "Añadir hook deleting en app/Models/Libro.php (T020)"

# Sequential:
Task: "Registrar DELETE /libros/{id}/portada en routes/web.php (T018, tras T017)"
Task: "Añadir botón «Eliminar portada» en resources/views/libros/edit.blade.php (T019, tras T018)"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup
2. Complete Phase 2: Foundational (CRITICAL - blocks all stories)
3. Complete Phase 3: User Story 1 (tests first, then T012 → T013 → T014)
4. **STOP and VALIDATE**: `php artisan test` en verde y quickstart V-001…V-003, V-006…V-010 a mano
5. Deploy/demo if ready

### Incremental Delivery

1. Setup + Foundational → ruta de entrega con sesión y reconciliación listas
2. Add User Story 1 → Test independently → Deploy/Demo (MVP: asociar/reemplazar portadas)
3. Add User Story 2 → Test independently → Deploy/Demo (eliminar portadas y libros sin huérfanos)
4. Polish → README, validación V-001…V-010 completa, revisión SC-001…SC-007
5. Each story adds value without breaking previous stories

### Parallel Team Strategy

1. Team completes Setup + Foundational together
2. Once Foundational is done:
   - Developer A: User Story 1 (T008–T014)
   - Developer B: User Story 2 (T015–T020), coordinando el uso de `routes/web.php` y `resources/views/libros/edit.blade.php`
3. Stories complete and integrate independently

---

## Notes

- [P] tasks = different files, no dependencies
- [Story] label maps task to specific user story for traceability; Setup, Foundational y Polish no llevan etiqueta de historia
- Each user story should be independently completable and testable
- Verify tests fail before implementing
- Commit after each task or logical group (Conventional Commits, rama `feature/portadas`)
- Stop at any checkpoint to validate story independently
- Avoid: vague tasks, same file conflicts, cross-story dependencies that break independence
- Messages texts are contracts: use them verbatim from `contracts/user-messages.md` (§1, §2, §3, §4, §5, §6)
