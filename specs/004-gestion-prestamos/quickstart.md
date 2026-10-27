# Quickstart: validación de 004-gestion-prestamos

**Spec**: [spec.md](spec.md) | **Data model**: [data-model.md](data-model.md) | **Contracts**: [contracts/](contracts/)

Guía de ejecución y validación de extremo a extremo. No contiene implementación: los detalles pertenecen a `tasks.md`.

## Requisitos previos

- Entorno de `001-entorno-datos` funcionando (PHP ≥ 8.3, Composer 2.x, MySQL 8.x local).
- Proyecto Laravel 13 instalado, migrado y **sembrado** (incluye los 3 préstamos de prueba: 1 `activo`, 1 `devuelto`, 1 `vencido`).
- Catálogo de la Fase 1 disponible (≥ 15 libros, `002`); el CRUD de `003` no es prerequisite.

## Puesta en marcha

```bash
composer install
cp .env.example .env        # completar credenciales (001 contracts/environment-variables.md)
php artisan key:generate
php artisan migrate --seed  # esquema + libros/categorías/préstamos de prueba (001)
php artisan test            # suite completa, incluidas las 5 suites de 004
php artisan serve           # → http://127.0.0.1:8000
```

## Escenarios de validación

### V-001 — Registro válido y descuento de disponibilidad (US1.1, SC-001)

1. Anotar la disponibilidad actual de un libro con ejemplares disponibles.
2. Abrir `http://127.0.0.1:8000/prestamos/crear`, seleccionar ese libro, nombre y carné nuevos, fecha de préstamo hoy y devolución prevista futura; enviar.
3. **Esperado**: redirección al listado con «Préstamo registrado correctamente.»; el préstamo aparece con estado **Activo**; la disponibilidad del libro disminuye **exactamente en uno** (comprobar en la ficha del libro `002` o en BD).

### V-002 — Registro de devolución (US2.1, SC-001)

1. En el listado, pulsar «Registrar devolución» en un préstamo **Activo**; introducir una fecha real válida (no anterior a la fecha de préstamo); enviar.
2. **Esperado**: «Devolución registrada correctamente.»; el estado pasa a **Devuelto** con su fecha real; la disponibilidad del libro aumenta **exactamente en uno**; la fila ya no ofrece «Registrar devolución».

### V-003 — Sin disponibilidad (US1.2, SC-002)

1. Elegir un libro con `disponibles: 0` en el selector (o agotar sus ejemplares) y enviar un registro.
2. **Esperado**: rechazo con «No hay ejemplares disponibles de este libro.» junto al campo **Libro**, conservando el resto de lo ingresado; la disponibilidad **no** cambia.

### V-004 — Límite de tres préstamos no devueltos (US1.3, SC-002)

1. Con un carné que ya tenga 3 préstamos sin devolver (mezcla `activo` + `vencido`), intentar un nuevo registro.
2. **Esperado**: rechazo con «El estudiante ya tiene el máximo de tres préstamos sin devolver.» junto al carné; sin cambio de disponibilidad.
3. Devolver uno de los tres (V-002) y repetir el registro: ahora **se acepta** (la plaza liberada cuenta).
4. Repetir el paso 1 usando el mismo carné con espacios sobrantes (p. ej. `" 12345 "`): se rechaza igualmente — mismo estudiante (D-023).

### V-005 — Fechas inválidas en el registro (US1.4, SC-002, SC-004)

1. Enviar el registro con devolución prevista **igual o anterior** a la fecha de préstamo.
2. **Esperado**: «La devolución prevista debe ser posterior a la fecha de préstamo.» junto al campo, con todos los valores conservados (`old()`).
3. Enviar el formulario **vacío**: errores en español junto a **cada** campo obligatorio; no se crea el préstamo.

### V-006 — Libro inexistente (edge case, SC-002)

1. Enviar `POST /prestamos` con un `libro_id` inexistente (p. ej. con `tinker` o `curl`; la UI usa selector, pero el servidor es la fuente de verdad).
2. **Esperado**: rechazo con «El libro seleccionado no existe.» junto al campo; sin cambios.

### V-007 — Carrera por el último ejemplar (edge case, SC-002)

1. Con un libro con `disponibles: 1`, registrar dos préstamos consecutivos de ese libro.
2. **Esperado**: el primero se registra; el segundo recibe «No hay ejemplares disponibles de este libro.»; la disponibilidad queda en **0, nunca negativa**.
   Referencia: [data-model.md](data-model.md) §Reglas de negocio R1/R8.

### V-008 — Devolución de préstamo vencido (edge case)

1. Tomar un préstamo **Vencido** (sembrado o generado con fecha prevista pasada); registrar su devolución con fecha real ≥ fecha de préstamo.
2. **Esperado**: estado **Devuelto**, disponibilidad +1. Repetir la devolución: «Este préstamo ya fue devuelto.» (M2) sin volver a sumar.

### V-009 — Fecha real ausente o anterior (US2, edge case, SC-004)

1. En el formulario de devolución, enviar **vacío**; después con una fecha **anterior** a la fecha de préstamo.
2. **Esperado**: en ambos casos, error en español junto al campo «Fecha de devolución real», conservando lo ingresado y sin modificar nada.

### V-010 — Listado, estados y paginación (US3, SC-003)

1. Abrir `http://127.0.0.1:8000/prestamos` con los datos sembrados.
2. **Esperado**: los 3 préstamos aparecen con libro, estudiante, carné, fechas y estado correctos (**Activo**, **Devuelto**, **Vencido**); más préstamos generados paginan a 10 por página (la navegación alcanza todos, ordenado por fecha de préstamo descendente); «Registrar devolución» solo en filas no devueltos; con base sin préstamos: «No hay préstamos registrados aún» sin paginación.

### V-011 — Estado vencido calculado (FR-002, SC-003)

1. Registrar un préstamo con devolución prevista **mañana**: aparece **Activo**. Avanzar la fecha de las pruebas (o crear con prevista = ayer vía `tinker`) y volver a listar (o registrar/devolver cualquier préstamo).
2. **Esperado**: el préstamo con prevista **anterior** a hoy aparece **Vencido** sin intervención manual; con prevista = hoy sigue **Activo** (vencido desde el **día siguiente**); los `Devuelto` nunca cambian.
   Referencia: [data-model.md](data-model.md) §Transiciones de estado.

### V-012 — Inmutabilidad (FR-007)

1. Intentar editar o eliminar un préstamo (no hay enlaces en la interfaz; probar `PUT/DELETE /prestamos/{id}` con `curl`).
2. **Esperado**: los verbos/ rutas de modificación no existen (405/404); ningún préstamo puede alterarse salvo por su devolución.

### V-013 — Estructura visual común (SC-004)

1. Comparar visualmente listado, formulario de registro y formulario de devolución.
2. **Esperado**: misma cabecera, misma navegación (con «Préstamos») y mismo pie; errores en español junto a los campos; una sola aplicación.

### V-014 — Pruebas automatizadas

```bash
php artisan test
```

**Esperado**: suites `PrestamoRegistroTest`, `PrestamoReglasTest`, `PrestamoDevolucionTest`, `PrestamoListadoTest` y `PrestamoVencimientoTest` en verde (ver D-025), junto con las de `001` y `002`.

## Criterios de aceptación cubiertos

| SC | Escenario |
|---|---|
| SC-001 | V-001, V-002, V-007 |
| SC-002 | V-003, V-004, V-005, V-006, V-007, V-009 |
| SC-003 | V-001, V-002, V-010, V-011 |
| SC-004 | V-004, V-005, V-009, V-013 |
