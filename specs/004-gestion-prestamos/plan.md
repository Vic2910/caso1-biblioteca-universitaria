# Implementation Plan: Registro, consulta y devolución de préstamos (004-gestion-prestamos)

**Branch**: `004-gestion-prestamos` | **Date**: 2026-10-26 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/004-gestion-prestamos/spec.md`

## Summary

Implementar el ciclo de vida de los préstamos (Fase 2): registrar un préstamo de un libro a un estudiante (nombre y carné) con fechas de préstamo y devolución prevista, descontando un ejemplar disponible; listar los préstamos paginados con su estado —`activo`, `devuelto` o `vencido`, este último **calculado automáticamente** desde el día siguiente a la devolución prevista y evaluado al registrar, devolver y listar—; y registrar la devolución, que exige fecha real, incrementa la disponibilidad exactamente una vez y rechaza la devolución repetida. El servidor impide préstamos sin disponibilidad y más de tres préstamos no devueltos (activos o vencidos) por estudiante (carné comparado sin espacios sobrantes); los préstamos son inmutables (sin editar ni eliminar). Toda la lógica de préstamo se centraliza en un servicio (Principio III), se valida con Form Requests en español junto a los campos, y **no hay cambios de esquema**: la tabla `prestamos` ya fue creada en `001-entorno-datos`.

## Technical Context

**Language/Version**: PHP 8.3–8.5 (heredado de `001` D-001)

**Primary Dependencies**: Laravel 13, Composer 2.x, Blade + CSS propio (sin frameworks CSS, D-011), PHPUnit (D-008/D-016)

**Storage**: MySQL 8.x local (heredado D-002); esquema solo con migraciones — `prestamos` y su FK `restrictOnDelete()` ya creadas en `001`; **004 no añade migraciones** (D-018…D-027); Carbon para fechas

**Testing**: PHPUnit con `RefreshDatabase` sobre base de datos de prueba MySQL; comando `php artisan test`; 5 suites de característica (D-025)

**Target Platform**: Aplicación web local (`php artisan serve`, `http://127.0.0.1:8000`), sin autenticación en esta fase

**Project Type**: Web application (monolito Laravel/Blade, un único repositorio)

**Performance Goals**: Sin requisitos de tráfico; decenas–cientos de filas; listado paginado con eager loading `with('libro')` y una sincronización de vencidos por petición de escritura/listado (D-020)

**Constraints**: Sin servicios de pago; mensajes en español junto al campo conservando lo ingresado (D-021); credenciales fuera del repositorio (heredado D-007); préstamos inmutables — sin editar ni eliminar, solo registrar/consultar/devolver (FR-007); disponibilidad nunca inferior a cero ante concurrencia (D-022)

**Scale/Scope**: 5 rutas, 1 controlador (4 acciones), 2 Form Requests, 1 servicio central, 3 vistas nuevas/ajustadas, ~5 suites de test; sin cambios de esquema; alcance limitado a préstamos — sin CRUD de libros (`003`), sin portadas (`005`), sin búsqueda (`006`), sin panel (`007`)

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| # | Gate (constitución v1.0) | Veredicto | Evidencia en el diseño |
|---|---|---|---|
| G1 | **I. Modelo de datos primero**: esquema con migraciones versionadas, modelos con relaciones | **PASS** | Sin cambios de esquema: `prestamos` y su FK `RESTRICT` ya existen desde `001` (D-003/D-004); el `estado` persistido y su sincronización se documentan en [data-model.md](data-model.md); `Prestamo` gana helpers sin tocar columnas |
| G2 | **II. Validación en el servidor** (Form Requests, `validated()`, errores en español con `old()`) | **PASS** | `RegistrarPrestamoRequest` y `DevolverPrestamoRequest` validan campos y delegan las reglas de negocio al servicio central; errores en español junto al campo y valores conservados con `old()` (D-021, [contracts/user-messages.md](contracts/user-messages.md)) |
| G3 | **III. Reglas de préstamo coherentes** (centralizadas; descuenta al prestar, suma al devolver; disponibilidad y límite de 3) | **PASS** | `PrestamoService` es el único lugar con las reglas de disponibilidad, fechas, límite y vencimiento (D-019); descuenta/suma exactamente una vez en transacción (D-022); ningún controlador o vista duplica las reglas |
| G4 | **IV. Seguridad y operaciones explícitas** (Eloquent sin SQL concatenado, escapado `{{ }}`, sin operaciones destructivas por GET) | **PASS** | Escrituras solo vía `POST` con `@csrf`; consultas Eloquent con `with('libro')`; sin DELETE ni edición de préstamos (FR-007); búsquedas explícitas con `abort(404, mensaje en español)` |
| G5 | **V. Aplicación local e interfaz clara** (Laravel, `php artisan serve`, layout Blade común, CSS propio) | **PASS** | Rutas sobre la app local; las 3 vistas extienden `layouts/app.blade.php` y añaden el enlace «Préstamos» a la navegación (D-018, D-024); tablas, formularios y badges de estado con CSS propio |
| G6 | **Restricciones → Tecnologías**: PHP 8.3+, Laravel 13, Composer 2.x, tablas solo con migraciones | **PASS** | Heredado D-001/D-003; sin migraciones nuevas porque el esquema de `prestamos` existe desde `001` |
| G7 | **Restricciones → Seguridad**: Eloquent/Query Builder, validación en servidor, escapado de salida | **PASS** | D-021/D-022; textos de datos (nombre, carné, títulos) renderizados con `{{ }}`; reglas de negocio validadas en servidor, no en la interfaz (supuesto de la spec) |
| G8 | **Restricciones → Imágenes**: Storage con ruta en BD, imagen predeterminada | **PASS (no aplica)** | `004` no muestra portadas (el listado muestra título/ISBN del libro); la gestión de imágenes queda en `005-gestion-portadas` |
| G9 | **Flujo de desarrollo**: rama `feature/`, Conventional Commits, README como documentación oficial | **PASS** | Rama `feature/prestamos` (spec); el README documenta el flujo de préstamos, las reglas (límite 3, vencimiento) y las capturas de listado/registro/devolución |

**Gate result**: **0 violaciones** → no se requiere *Complexity Tracking*.

## Project Structure

### Documentation (this feature)

```text
specs/004-gestion-prestamos/
├── plan.md              # This file (/speckit.plan command output)
├── research.md          # Phase 0 output — decisiones D-018…D-027, sin NEEDS CLARIFICATION
├── data-model.md        # Phase 1 output — entidades, transiciones de estado, reglas de negocio, invariantes
├── quickstart.md        # Phase 1 output — escenarios V-001…V-010
├── contracts/           # Phase 1 output
│   ├── http-endpoints.md      # FR-001/002/004/005/007: rutas, parámetros, 200/302/404
│   ├── ui-structure.md        # FR-001/006: listado, formulario de registro, formulario de devolución, estados
│   └── user-messages.md       # FR-003/006 + edge cases: textos en español (éxito, rechazo, vacío)
├── checklists/requirements.md
└── tasks.md             # Phase 2 output (/speckit.tasks command - NOT created by /speckit.plan)
```

### Source Code (repository root)

```text
app/
├── Http/Controllers/
│   └── PrestamoController.php        # index (listado, D-024), create/store (registro, D-018), form-devolución/devolución (D-018)
├── Http/Requests/
│   ├── RegistrarPrestamoRequest.php  # campos + reglas de negocio delegadas al servicio (D-021)
│   └── DevolverPrestamoRequest.php   # fecha real obligatoria y no anterior al préstamo (D-021)
├── Models/
│   └── Prestamo.php                  # (crecimiento de 001) helpers de estado/vencimiento; relación libro() ya existente
└── Services/Prestamos/
    └── PrestamoService.php           # ÚNICO lugar de las reglas: registrar, devolver, vencimiento, límite 3, disponibilidad (D-019, D-020, D-022)

routes/web.php                        # GET /prestamos, GET /prestamos/crear, POST /prestamos, GET|POST /prestamos/{id}/devolver (D-018)

resources/views/
├── layouts/app.blade.php             # (001/002) + enlace «Préstamos» en la navegación (D-018)
├── pagination/prestamos.blade.php    # 10 por página con CSS propio, análoga a pagination/catalogo (D-024)
└── prestamos/
    ├── index.blade.php               # listado paginado: datos, badge de estado, acción devolver, estado vacío
    ├── create.blade.php              # formulario de registro (errores junto al campo, old())
    └── devolucion.blade.php          # formulario de devolución con resumen del préstamo

tests/Feature/
├── PrestamoRegistroTest.php          # registro válido, descuento de disponibilidad, fechas inválidas, libro inexistente (V-001, V-005…V-007)
├── PrestamoReglasTest.php            # sin disponibilidad, límite de 3, carné con espacios, disponibilidad nunca < 0 (V-003, V-004, V-008)
├── PrestamoDevolucionTest.php        # devolución (activo y vencido), suma única, devolución repetida, fecha real inválida (V-002, V-009)
├── PrestamoListadoTest.php           # datos y estados en el listado, paginación, orden, estado vacío (V-010)
└── PrestamoVencimientoTest.php       # vencido calculado el día siguiente; evaluado al registrar, devolver y listar (V-011)
```

**Structure Decision**: Monolito Laravel 13 de aplicación única (árbol estándar de Laravel), heredado de `001-entorno-datos`. `004` reutiliza el layout, los modelos (`Libro`, `Prestamo`), las migraciones y los datos sembrados de `001` (incluidos los 3 préstamos de prueba) y el layout ampliado en `002`; añade un controlador con cuatro acciones, dos Form Requests, el servicio central de reglas de préstamo, tres piezas de vista y ninguna migración. Es la primera funcionalidad con escrituras sobre `prestamos` y `libros.ejemplares_disponibles`; no hay frontend/backend separados.

## Constitution Check (re-evaluación post-Phase 1)

| Gate | Re-veredicto | Nota |
|---|---|---|
| G1–G9 | **PASS** | Los artefactos de Phase 1 (research, data-model, contracts, quickstart) cubren cada gate sin nuevas violaciones; ninguna decisión requiere migración ni rompe el esquema de `001` |
| Clarificaciones 2026-10-26 | Satisfechas | Vencido calculado y evaluado al registrar/devolver/listar (D-020, [data-model.md](data-model.md) §Transiciones); vencidos cuentan para el límite de 3 (D-026, [data-model.md](data-model.md) §Reglas de negocio); sin editar ni eliminar préstamos (D-018: el controlador no expone esas operaciones) |
| NEEDS CLARIFICATION restantes | **0** | Todos resueltos en [research.md](research.md) |

**Gate result**: **PASS — 0 violaciones, 0 aclaraciones pendientes.**

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

*Ninguna violación: tabla vacía por diseño.*
