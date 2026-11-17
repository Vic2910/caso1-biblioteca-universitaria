# Implementation Plan: Gestión de portadas de libros (005-gestion-portadas)

**Branch**: `005-gestion-portadas` (spec: `feature/portadas`) | **Date**: 2026-10-26 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/005-gestion-portadas/spec.md`

## Summary

Gestionar las portadas de los libros (Fase 2): asociar, reemplazar y eliminar la imagen de un libro **desde el formulario de edición** (reemplazo al elegir un archivo nuevo, eliminación mediante botón con confirmación; la vista de detalle no recibe controles). El servidor acepta únicamente JPG, PNG o WebP de hasta 2 MB (límite inclusivo) con validación en Form Request y mensajes en español junto al campo, conservando los valores ingresados. Las imágenes se guardan en el disco `public` de **Laravel Storage** en un directorio propio por libro (`portadas/{id}/`) con la ruta registrada en `libros.portada` (columna ya creada en `001`); reemplazar, eliminar la portada o eliminar el libro retira siempre el archivo anterior o el directorio completo, sin huérfanos, incluso ante reemplazos concurrentes (última escritura + reconciliación). Las portadas se sirven mediante una ruta propia `GET /portadas/{libro}` que **exige sesión iniciada** (sin sesión: HTTP 401 en español solicitando el inicio de sesión), mientras que la imagen predeterminada de `001`/`002` sigue siendo un asset público. Toda imagen mostrada en listado y detalle lleva `alt` con el título del libro y la predeterminada un `alt` genérico. Un fallo de almacenamiento no altera la portada existente ni el registro del libro.

## Technical Context

**Language/Version**: PHP 8.3–8.5 (heredado de `001` D-001)

**Primary Dependencies**: Laravel 13, Composer 2.x, Blade + CSS propio, Laravel Storage (disco local `public`), PHPUnit (heredado D-001/D-008)

**Storage**: MySQL 8.x local (heredado D-002) **sin migraciones nuevas** — `libros.portada` ya existe desde `001`; archivos de portada en el disco `public` de Laravel Storage bajo `portadas/{libro_id}/` (D-018)

**Testing**: PHPUnit con `RefreshDatabase` y `Storage::fake`/disco mockeado para fallos; comando `php artisan test` (D-026)

**Target Platform**: Aplicación web local (`php artisan serve`, `http://127.0.0.1:8000`); la visualización de portadas exige sesión iniciada (FR-007; login proporcionado por `008-interfaz-validacion-seguridad`)

**Project Type**: Web application (monolito Laravel/Blade, un único repositorio)

**Performance Goals**: Sin requisitos de tráfico; decenas–cientos de libros; una E/S de archivo por operación de portada (guardar/borrar) sin lecturas por fila al pintar el listado

**Constraints**: Sin servicios de pago; mensajes en español junto al campo y portada existente intacta ante cualquier rechazo o fallo (FR-002/FR-006); credenciales fuera del repositorio (heredado D-007); operaciones solo con sesión y eliminación por `DELETE` con confirmación (FR-005, constitución IV)

**Scale/Scope**: 2 rutas nuevas + la ampliación del `update` de libros de `003`; 1 controlador nuevo (+ acción en el controlador de libros), 1 Form Request ampliado, 2 vistas afectadas (formulario de edición, parcial de portada) + vista 401; **0 migraciones**; ~5 suites de test; alcance limitado a portadas (CRUD de libros en `003`, préstamos en `004`, búsqueda en `006`, login en `008`)

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| # | Gate (constitución v1.0) | Veredicto | Evidencia en el diseño |
|---|---|---|---|
| G1 | **I. Modelo de datos primero**: esquema con migraciones versionadas, modelos con `$fillable`, casts y relaciones | **PASS** | Sin cambios de esquema: `libros.portada varchar(255) NULL` ya creada en `001` ([data-model.md](data-model.md) §Cambios de esquema); el hook `deleting` del modelo `Libro` documenta la conducta de borrado del archivo (D-022) |
| G2 | **II. Validación en el servidor** (Form Requests, `validated()`, errores en español con `old()`) | **PASS** | Reglas `file`/`mimes`/`max:2048` en el Form Request de edición de libros, mensajes en español junto al campo `portada` y conservación de los demás valores con `old()` (D-019, [contracts/user-messages.md](contracts/user-messages.md)) |
| G3 | **III. Reglas de préstamo coherentes** | **PASS (no aplica)** | Las portadas no tocan ejemplares ni préstamos; la eliminación del libro sigue la regla de `003` (bloqueada si tiene préstamos) y, cuando procede, su archivo se elimina junto con él (FR-004, D-022) |
| G4 | **IV. Seguridad y operaciones explícitas** (`DELETE` nunca por GET, `@csrf`, escapado, Eloquent sin SQL concatenado) | **PASS** | Eliminación de portada por `DELETE /libros/{id}/portada` con confirmación en el formulario (FR-005, D-021); portadas servidas solo con sesión (FR-007, D-023); `alt` y mensajes renderizados con `{{ }}` (D-024) |
| G5 | **V. Aplicación local e interfaz clara** (Laravel, `php artisan serve`, layout común, CSS propio, sin servicios de pago) | **PASS** | Disco de Storage local, sin servicios externos; formulario de edición y errores comparten el layout de `001`/`002`; estilos propios (D-018, D-025) |
| G6 | **Restricciones → Tecnologías**: PHP 8.3+, Laravel 13, Composer 2.x, tablas solo con migraciones | **PASS** | Heredado D-001/D-003; esta fase no crea tablas ni columnas (0 migraciones) |
| G7 | **Restricciones → Seguridad**: Eloquent/Query Builder, validación en servidor, `@csrf`, escapado, credenciales fuera del repo | **PASS** | D-019, D-021, D-023; credenciales heredadas de D-007; sin SQL con entradas del usuario |
| G8 | **Restricciones → Imágenes**: portadas en Laravel Storage con ruta en BD, validar JPG/PNG/WebP hasta 2 MB, reemplazar y eliminar correctamente, imagen predeterminada si no existe | **PASS** | Núcleo de esta funcionalidad: D-018 (Storage + ruta en BD), D-019 (validación), D-020/D-021/D-022 (reemplazo y borrado sin huérfanos), D-023 (predeterminada reutilizada) |
| G9 | **Flujo de desarrollo**: rama `feature/`, Conventional Commits, README como documentación oficial | **PASS** | Rama `feature/portadas` (spec); el README documenta la gestión de portadas, formatos/límite y la necesidad de sesión (FR-001/FR-002/FR-007) |

**Gate result**: **0 violaciones** → no se requiere *Complexity Tracking*. Re-evaluado tras Phase 1 (ver abajo).

## Project Structure

### Documentation (this feature)

```text
specs/005-gestion-portadas/
├── plan.md              # This file (/speckit.plan command output)
├── research.md          # Phase 0 output — decisiones D-018…D-026, sin NEEDS CLARIFICATION
├── data-model.md        # Phase 1 output — ciclo de vida de la portada, reglas, sin migraciones
├── quickstart.md        # Phase 1 output — escenarios V-001…V-010
├── contracts/           # Phase 1 output
│   ├── http-endpoints.md      # FR-001/005/007: PUT edición, DELETE portada, GET portada (401)
│   ├── ui-structure.md        # FR-001/005/008: controles en el formulario, alt en listado/detalle
│   └── user-messages.md       # FR-002/006/008: errores en español, confirmación, 401, alt
├── checklists/requirements.md
└── tasks.md             # Phase 2 output (/speckit.tasks command - NOT created by /speckit.plan)
```

### Source Code (repository root)

```text
app/
├── Http/Controllers/
│   ├── LibroController.php       # (003) ampliado: @update procesa el archivo nuevo + compensaciones (D-020)
│   └── PortadaController.php     # nuevo: @show sirve la imagen con sesión (D-023), @destroy elimina portada (D-021)
├── Http/Requests/
│   └── [Form Request de edición de libros de 003]  # ampliado con reglas de portada (D-019)
└── Models/
    └── Libro.php                 # (001) + hook deleting → borra portadas/{id} (D-022)

storage/app/public/portadas/{libro_id}/   # creado en runtime; NO versionado (gitignore heredado)

routes/web.php                    # GET /portadas/{id} (auth por comprobación propia), DELETE /libros/{id}/portada (D-021, D-023)

resources/views/
├── partials/portada.blade.php    # (002) ampliado: src → route('portadas.show', $libro); alt = título (D-023, D-024)
├── libros/
│   └── edit.blade.php            # (003) ampliado: input file + botón «Eliminar portada» con confirmación + errores junto al campo (D-019, D-021)
└── errors/401.blade.php          # nueva: 401 en español pidiendo inicio de sesión, enlace a /login si existe la ruta (D-023)

tests/Feature/
├── PortadaSubidaTest.php             # asociar/reemplazar, 3 formatos, 2 MB inclusivo, rechazos (V-001…V-003)
├── PortadaEliminacionTest.php        # DELETE con confirmación, borrado de archivo, borrado del libro (V-004…V-006)
├── PortadaAccesoTest.php             # 401 sin sesión, 200 con sesión, alt en listado/detalle (V-007, V-008)
├── PortadaFalloAlmacenamientoTest.php# disco mockeado → mensaje + portada intacta (V-009)
└── PortadaConcurrenciaTest.php       # última escritura y cero huérfanos (V-010)

README.md                      # sección «Gestión de portadas»: formatos, 2 MB, sesión, capturas
```

**Structure Decision**: Monolito Laravel 13 de aplicación única (árbol estándar de Laravel), heredado de `001`/`002`. `005` no introduce frontend/backend separados ni migraciones: reutiliza el modelo `Libro`, el parcial `partials/portada`, el layout y las vistas de error de las fases previas, y añade un controlador de portadas con dos acciones, la ampliación del formulario de edición que entrega `003-gestion-libros` y cinco suites de test.

**Dependencias entre features** (declaradas para `tasks.md`): `001` (modelo, columna `portada`, imagen predeterminada), `002` (parcial de portada y vistas de listado/detalle), `003` (rutas y formulario de edición de libros donde viven los controles; su plan aún no está escrito — se asume el controlador `LibroController` con `update`/`destroy` y un Form Request de edición), `008` (rutas de inicio de sesión, para que el 401 de portadas enlace a `/login`; `005` no depende de él para funcionar, ver D-023).

## Constitution Check (re-evaluación post-Phase 1)

| Gate | Re-veredicto | Nota |
|---|---|---|
| G1–G9 | **PASS** | Los artefactos de Phase 1 (research, data-model, contracts, quickstart) cubren cada gate sin nuevas violaciones; ninguna decisión (D-018…D-026) contradice la constitución |
| Restricciones → Imágenes (G8) | **PASS** | [data-model.md](data-model.md) y [contracts/http-endpoints.md](contracts/http-endpoints.md) cubren validación, reemplazo, borrado y predeterminada de extremo a extremo |
| Clarificaciones 2026-10-26 | Satisfechas | Controles solo en el formulario de edición (ui-structure §2); conservación al editar otros datos (data-model §transiciones); sesión para imágenes (http-endpoints §GET portada); última escritura sin huérfanos (D-020); `alt` = título y genérico en la predeterminada (user-messages §4) |
| NEEDS CLARIFICATION restantes | **0** | Todos resueltos en [research.md](research.md) |

**Gate result**: **PASS — 0 violaciones, 0 aclaraciones pendientes.**

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

*Ninguna violación: tabla vacía por diseño.*
