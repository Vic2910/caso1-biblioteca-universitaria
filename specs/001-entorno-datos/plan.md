# Implementation Plan: Entorno y base de datos (001-entorno-datos)

**Branch**: `001-entorno-datos` | **Date**: 2026-10-26 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/001-entorno-datos/spec.md`

## Summary

Preparar el entorno local y la base de datos de la Biblioteca Universitaria (Fase 1): crear un proyecto Laravel 13 (PHP 8.3+) que arranque con `php artisan serve` siguiendo el README, sin servicios de pago; modelar las entidades **Libro**, **Categoría** y **Préstamo** exclusivamente con migraciones versionadas (claves foráneas `RESTRICT`, unicidad en ISBN y nombre de categoría); cargar datos de prueba de ≥ 15 libros, 3 categorías y 3 préstamos (activo/devuelto/vencido) con la disponibilidad de ejemplares coherente; mantener las credenciales fuera del repositorio; mostrar un aviso en español sin credenciales ante fallos de conexión; y documentar en el README la conducta de eliminación de registros relacionados. Motor de datos: **MySQL 8 local** (elegido entre los permitidos por la constitución; ver investigación).

## Technical Context

**Language/Version**: PHP 8.3–8.5 (mínimo de Laravel 13, verificado)

**Primary Dependencies**: Laravel 13, Composer 2.x, Blade + CSS propio (Bootstrap/Tailwind opcionales declarables en README), PHPUnit (incluido)

**Storage**: MySQL 8.x local (alternativas permitidas: MariaDB, PostgreSQL local); esquema solo mediante migraciones de Laravel

**Testing**: PHPUnit con `RefreshDatabase` sobre base de datos MySQL de prueba; comando `php artisan test`

**Target Platform**: Aplicación web local (`php artisan serve`, `http://127.0.0.1:8000`), equipo de 2–4 integrantes

**Project Type**: Web application (monolito Laravel/Blade, un único repositorio)

**Performance Goals**: Sin requisitos de tráfico en Fase 1; volumen de decenas de filas (15+ libros, 3+3 registros por catálogo)

**Constraints**: Sin servicios de pago obligatorios; credenciales solo en `.env` (no versionado); mensajes de error visibles en español y sin exponer credenciales; esquema y datos de prueba como dependencia de las fases posteriores

**Scale/Scope**: 3 entidades, ~21+ filas sembradas, 1 repositorio; alcance limitado a entorno y datos (la consulta del catálogo es `002-consulta-catalogo`)

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| # | Gate (constitución v1.0) | Veredicto | Evidencia en el diseño |
|---|---|---|---|
| G1 | **I. Modelo de datos primero**: migraciones para Libro, Categoría y Préstamo con FK, unicidad y eliminación documentada; modelos con `$fillable`, casts y relaciones | **PASS** | [data-model.md](data-model.md): migraciones exclusivas (D-003), `restrictOnDelete()`, únicos en `isbn` y `nombre`, relaciones Eloquent definidas, conducta de borrado documentada (FR-006) |
| G2 | **II. Validación en el servidor** (Form Requests, `validated()`, errores en español con `old()`) | **PASS (fase sin formularios)** | Esta fase no crea formularios; las reglas (ISBN 13 dígitos, año 1900–actual, devolución prevista > préstamo) quedan especificadas en [data-model.md](data-model.md) para implementarse en la Fase 2; los datos de prueba se validan en factories/seeders |
| G3 | **III. Reglas de préstamo coherentes** (descuento de ejemplares, máx. 3 activos por estudiante) | **PASS (alcance de datos)** | Solo se exige coherencia de datos: invariante `disponibles = totales − activos` verificado en la siembra (FR-004/SC-002); la lógica de préstamos es fase posterior |
| G4 | **IV. Seguridad y operaciones explícitas** (Eloquent sin SQL concatenado, `DELETE` nunca por GET, `@csrf`, escapado) | **PASS** | Seeders y consultas con Eloquent/Query Builder parametrizado; sin eliminaciones vía interfaz en esta fase (los intentos se prueban vía `DELETE`/consola, V-006); credenciales excluidas (D-007) |
| G5 | **V. Aplicación local e interfaz clara** (Laravel, `php artisan serve`, sin servicios de pago) | **PASS** | Laravel 13 + MySQL local gratuitos; comando de inicio único documentado (D-009, FR-001) |
| G6 | **Restricciones → Tecnologías**: PHP 8.3+, Laravel 13, Composer 2.x, MySQL/MariaDB/PostgreSQL, tablas solo con migraciones | **PASS** | D-001, D-002, D-003 |
| G7 | **Restricciones → Seguridad**: credenciales fuera del repositorio | **PASS** | D-007 + [contracts/environment-variables.md](contracts/environment-variables.md) |
| G8 | **Restricciones → Imágenes**: portada en Storage con ruta en BD, imagen predeterminada si no existe | **PASS** | Datos de prueba sin portada real → `portada = NULL` → imagen predeterminada (clarificación 2026-10-26); Storage efectivo en fase de portadas (`005`) |
| G9 | **Flujo de desarrollo**: rama `feature/`, Conventional Commits, README como documentación oficial | **PASS** | Rama `feature/entorno-datos` propuesta; README documenta instalación, motor, ER, reglas de eliminación y conducta de borrado (FR-005, FR-006) |

**Gate result**: **0 violaciones** → no se requiere *Complexity Tracking*. Re-evaluado tras Phase 1 (ver abajo).

## Project Structure

### Documentation (this feature)

```text
specs/001-entorno-datos/
├── plan.md              # This file (/speckit.plan command output)
├── research.md          # Phase 0 output — decisiones D-001…D-009, sin NEEDS CLARIFICATION
├── data-model.md        # Phase 1 output — entidades, FK, unicidad, estados, siembra
├── quickstart.md        # Phase 1 output — escenarios V-001…V-008
├── contracts/           # Phase 1 output
│   ├── environment-variables.md   # FR-007: credenciales fuera del repo
│   ├── cli-commands.md            # FR-001/003/004: secuencia de comandos
│   └── user-messages.md           # FR-006/008: avisos en español
├── checklists/requirements.md
└── tasks.md             # Phase 2 output (/speckit.tasks command - NOT created by /speckit.plan)
```

### Source Code (repository root)

```text
app/
├── Exceptions/            # Manejador de fallos de conexión → vista en español (D-006)
├── Http/Controllers/      # (vacío en esta fase; catálogo en 002)
└── Models/
    ├── Libro.php          # $fillable, casts, categoria(), prestamos()
    ├── Categoria.php      # $fillable, libros()
    └── Prestamo.php       # $fillable, casts de fechas, estado, libro()

database/
├── migrations/            # Única fuente del esquema (FR-003)
│   ├── *_create_categorias_table.php   # nombre UNIQUE
│   ├── *_create_libros_table.php       # isbn UNIQUE, FK categoria_id RESTRICT
│   └── *_create_prestamos_table.php     # FK libro_id RESTRICT, estado, fechas
├── factories/             # Libro/Categoria/Prestamo factories con estados de préstamo
└── seeders/               # ≥15 libros, 3 categorías, 3 préstamos (activo/devuelto/vencido)

resources/views/
├── layouts/app.blade.php  # layout común (base para 002+)
├── errors/conexion.blade.php  # aviso de fallo de conexión en español (D-006)
└── welcome.blade.php      # página inicial que prueba la conexión

routes/web.php

tests/Feature/
├── MigracionesYSemillaTest.php   # V-002, V-003, V-004
├── FalloConexionTest.php         # V-005
├── EliminacionRelacionadaTest.php# V-006
└── CredencialesTest.php          # V-007

.env.example               # versionado sin secretos (.env ignorado)
README.md                  # instalación, motor elegido, diagrama ER,
                           # «Eliminación de registros relacionados», comandos
```

**Structure Decision**: Monolito Laravel 13 de aplicación única (árbol estándar de Laravel) — un solo repositorio exige la constitución; no hay frontend/backend separados ni apps móviles. El layout Blade común declarado en el Principio V se crea como base mínima aquí y se consume desde `002-consulta-catalogo`.

## Constitution Check (re-evaluación post-Phase 1)

| Gate | Re-veredicto | Nota |
|---|---|---|
| G1–G9 | **PASS** | Los artefactos de Phase 1 (research, data-model, contracts, quickstart) cubren cada gate sin nuevas violaciones; ninguna decisión de diseño (D-001…D-009) contradice la constitución |
| Clarificaciones 2026-10-26 | Satisfechas | Únicos: ISBN + nombre de categoría (D-003); sin portada real (D-005); 3 estados de préstamo (data-model §siembra); coherencia de disponibilidad (invariante en data-model y V-003) |
| NEEDS CLARIFICATION restantes | **0** | Todos resueltos en [research.md](research.md) |

**Gate result**: **PASS — 0 violaciones, 0 aclaraciones pendientes.**

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

*Ninguna violación: tabla vacía por diseño.*
