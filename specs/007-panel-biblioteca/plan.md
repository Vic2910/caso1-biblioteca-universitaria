# Implementation Plan: Panel y reportes de biblioteca (007-panel-biblioteca)

**Branch**: `feature/panel-biblioteca` | **Date**: 2026-10-26 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/007-panel-biblioteca/spec.md`

## Summary

Implementar el panel de indicadores de la Biblioteca Universitaria (Fase 2): una única vista de solo consulta en `GET /panel` que presenta tres indicadores calculados con los registros vigentes al momento de la consulta —(1) ranking de los 5 libros más prestados del historial completo, con su cifra de préstamos y desempate alfabético del título; (2) lista de préstamos vencidos, determinados por fecha de devolución prevista estrictamente anterior a la fecha de consulta y sin devolución real, con libro, estudiante, carné, fechas y días de retraso, ordenada por retraso de mayor a menor; (3) total de ejemplares disponibles por categoría, en orden alfabético español sin tildes ni mayúsculas—, con el mensaje «No hay datos disponibles» cuando un indicador no tiene datos. Sin autenticación diferenciada (el panel es consultable por los tres perfiles; el inicio de sesión global llega con `008-interfaz-validacion-seguridad`), sin escrituras y sin cambios de esquema salvo una migración que fija la colación de ordenación sobre `categorias.nombre`.

## Technical Context

**Language/Version**: PHP 8.3–8.5 (heredado de `001` D-001)

**Primary Dependencies**: Laravel 13, Composer 2.x, Blade + CSS propio (sin frameworks CSS), PHPUnit (D-008/D-016)

**Storage**: MySQL 8.x local (heredado D-002); esquema solo con migraciones — `007` añade una única migración de colación sobre `categorias.nombre` (D-021); los datos provienen de `libros`, `categorias` y `prestamos` (`001`/`004`)

**Testing**: PHPUnit con `RefreshDatabase` sobre base de datos de prueba MySQL; comando `php artisan test`; 5 suites de característica (D-026)

**Target Platform**: Aplicación web local (`php artisan serve`, `http://127.0.0.1:8000`), sin autenticación propia en esta funcionalidad (D-018)

**Project Type**: Web application (monolito Laravel/Blade, un único repositorio)

**Performance Goals**: Sin requisitos de tráfico; decenas–cientos de filas; 3 consultas agregadas por petición (D-023)

**Constraints**: Sin servicios de pago; vista de solo consulta —sin formularios ni eliminaciones— (FR-006); mensajes en español (D-024/D-025); credenciales fuera del repositorio (heredado D-007); fecha de consulta única por petición (D-022)

**Scale/Scope**: 1 ruta, 1 controlador (1 acción), 1 vista nueva + enlace de navegación, 1 migración de colación, ~5 suites de test; alcance limitado al panel —sin CRUD, sin registro de préstamos, sin portadas, sin búsqueda

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| # | Gate (constitución v1.0) | Veredicto | Evidencia en el diseño |
|---|---|---|---|
| G1 | **I. Modelo de datos primero**: esquema con migraciones versionadas, modelos con relaciones | **PASS** | Único cambio: migración de colación `utf8mb4_0900_ai_ci` en `categorias.nombre` (D-021, [data-model.md](data-model.md) §Cambios de esquema); sin entidades nuevas; relaciones Eloquent heredadas de `001` |
| G2 | **II. Validación en el servidor** (Form Requests, `validated()`, errores en español con `old()`) | **PASS (fase sin formularios)** | El panel no recibe entradas: `GET /panel` sin parámetros (D-018); las reglas de formularios siguen en fase posterior; el criterio de vencimiento se aplica como filtro en el servidor (D-020) |
| G3 | **III. Reglas de préstamo coherentes** | **PASS (solo lectura)** | El panel no modifica préstamos ni disponibilidad (FR-006); lee `ejemplares_disponibles` calculado por las reglas de `004` y lo suma por categoría (D-021) |
| G4 | **IV. Seguridad y operaciones explícitas** (Eloquent sin SQL concatenado, escapado `{{ }}`, sin DELETE por GET) | **PASS** | Tres consultas Eloquent/Query Builder parametrizadas (D-023); sin formularios ni eliminaciones; salida escapada con `{{ }}` (D-025) |
| G5 | **V. Aplicación local e interfaz clara** (Laravel, `php artisan serve`, layout Blade común, CSS propio) | **PASS** | Rutas sobre la app local; layout común reutilizado de `001`/`002` con enlace «Panel» (D-025); rótulos y valores en español para los tres perfiles (FR-007) |
| G6 | **Restricciones → Tecnologías**: PHP 8.3+, Laravel 13, Composer 2.x, tablas solo con migraciones | **PASS** | Heredado D-001/D-002; la colación se aplica con `Schema::table(...)->change()` en migración (D-021) |
| G7 | **Restricciones → Seguridad**: Eloquent/Query Builder, validación en servidor, escapado de salida | **PASS** | D-023, D-025; textos renderizados con `{{ }}`; sin SQL concatenado |
| G8 | **Restricciones → Imágenes**: portada en Storage, imagen predeterminada | **PASS (no aplica)** | El panel no muestra portadas; los indicadores muestran título de libro y nombre de categoría como texto |
| G9 | **Flujo de desarrollo**: rama `feature/`, Conventional Commits, README como documentación oficial | **PASS** | Rama `feature/panel-biblioteca` (spec); el README documenta el panel, sus reglas (top 5, vencidos, disponibilidad por categoría) y añade la captura del panel |

**Gate result**: **0 violaciones** → no se requiere *Complexity Tracking*. Re-evaluado tras Phase 1 (ver abajo).

## Project Structure

### Documentation (this feature)

```text
specs/007-panel-biblioteca/
├── plan.md              # This file (/speckit.plan command output)
├── research.md          # Phase 0 output — decisiones D-018…D-026, sin NEEDS CLARIFICATION
├── data-model.md        # Phase 1 output — proyecciones de lectura, reglas de orden, migración de colación
├── quickstart.md        # Phase 1 output — escenarios V-001…V-009
├── contracts/           # Phase 1 output
│   ├── http-endpoints.md      # FR-001/002/003/006: ruta del panel, respuesta 200, solo lectura
│   ├── ui-structure.md        # FR-007/008: layout, tres secciones, estados vacíos, cifras
│   └── user-messages.md       # FR-007/008: textos en español («No hay datos disponibles», rótulos)
├── checklists/requirements.md
└── tasks.md             # Phase 2 output (/speckit.tasks command - NOT created by /speckit.plan)
```

### Source Code (repository root)

```text
app/
├── Http/Controllers/
│   └── PanelController.php    # index: tres consultas agregadas, fecha única (D-018…D-023)
└── Models/
    ├── Libro.php              # (001) se usa prestamos() + withCount para el ranking (D-019)
    ├── Categoria.php          # (001) se usa libros() + withSum para la disponibilidad (D-021)
    └── Prestamo.php           # (001) casts de fechas; consulta de vencidos por fechas (D-020)

database/
└── migrations/
    └── *_alter_categorias_nombre_collation.php   # utf8mb4_0900_ai_ci en categorias.nombre (D-021)

routes/web.php                 # GET /panel → panel.index (D-018)

resources/views/
├── layouts/app.blade.php      # (001/002) + enlace «Panel» en la navegación (D-025)
└── panel/
    └── index.blade.php        # tres indicadores + estados vacíos (D-024, D-025)

public/css/                    # reglas del panel añadidas al CSS propio del proyecto (sin frameworks)

tests/Feature/
├── PanelRankingTest.php        # D-019: top 5, empates, historial completo, solo libros con ≥1 préstamo
├── PanelVencidosTest.php       # D-020: regla de fechas, campos, orden por retraso, fecha límite
├── PanelDisponibilidadTest.php # D-021: suma por categoría, categorías ocultas/sin libros, orden alfabético
├── PanelEstadosVaciosTest.php  # D-024: «No hay datos disponibles» en los tres indicadores
└── PanelLayoutTest.php         # layout común, enlace «Panel», una sola vista (FR-007)
```

**Structure Decision**: Monolito Laravel 13 de aplicación única (árbol estándar de Laravel), heredado de `001-entorno-datos`. El panel reutiliza el layout, modelos y datos sembrados de `001`/`002` y la funcionalidad de préstamos de `004` (aún no planificada: los tests usan factories, no la interfaz de `004`), y añade un controlador con una acción, una vista y una migración de ajuste de colación; no hay frontend/backend separados.

## Dependencias entre funcionalidades

| Feature | Qué aporta al panel |
|---|---|
| `001-entorno-datos` | Migraciones, modelos (`Libro`, `Categoria`, `Prestamo`), factories y siembra (≥ 15 libros, 3 categorías, 3 préstamos) |
| `002-consulta-catalogo` | Layout común con navegación; colación `utf8mb4_0900_ai_ci` en `libros.titulo` (clave del desempate del ranking y de la lista de vencidos) |
| `004-gestion-prestamos` | Reglas de disponibilidad y estados de préstamo; el panel **resume** sus registros sin sustituirlos (supuestos de la spec). No es bloqueante para los tests del panel (se prueban con factories) |
| `008-interfaz-validacion-seguridad` | Inicio de sesión global: cuando se entregue, el panel queda protegido como el resto de vistas; no define permisos diferenciados, por lo que los tres perfiles (estudiante, docente, personal administrativo) pueden consultarlo (clarificación 2026-10-26) |

## Constitution Check (re-evaluación post-Phase 1)

| Gate | Re-veredicto | Nota |
|---|---|---|
| G1–G9 | **PASS** | Los artefactos de Phase 1 (research, data-model, contracts, quickstart) cubren cada gate sin nuevas violaciones; la única decisión de esquema (D-021) usa migración, como exige el Principio I |
| Clarificaciones 2026-10-26 | Satisfechas | Los tres perfiles consultan el panel sin roles diferenciados (D-018); vencidos por días de retraso de mayor a menor (D-020); categorías en orden alfabético español sin tildes/mayúsculas (D-021); cifras visibles junto a cada elemento (D-025); ranking solo con libros con ≥ 1 préstamo (D-019) |
| Casos límite | Cubiertos | Top 5, empates, fechas límite, devolución tardía, categorías ocultas y estados vacíos se especifican en [data-model.md](data-model.md) §Casos límite y se validan en [quickstart.md](quickstart.md) |
| NEEDS CLARIFICATION restantes | **0** | Todos resueltos en [research.md](research.md) |

**Gate result**: **PASS — 0 violaciones, 0 aclaraciones pendientes.**

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

*Ninguna violación: tabla vacía por diseño.*
