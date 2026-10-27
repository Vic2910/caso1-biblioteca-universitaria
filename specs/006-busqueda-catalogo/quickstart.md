# Quickstart: validación de 006-busqueda-catalogo

**Spec**: [spec.md](spec.md) | **Data model**: [data-model.md](data-model.md) | **Contracts**: [contracts/](contracts/)

Guía de ejecución y validación de extremo a extremo. No contiene implementación: los detalles pertenecen a `tasks.md`.

## Requisitos previos

- Entorno de `001-entorno-datos` funcionando (PHP ≥ 8.3, Composer 2.x, MySQL 8.x local).
- Listado paginado de `002-consulta-catalogo` disponible (`GET /catalogo`), con el seed de Fase 1 (≥ 15 libros, ≥ 3 categorías).

## Puesta en marcha

```bash
composer install
cp .env.example .env        # completar credenciales (contrato: 001 contracts/environment-variables.md)
php artisan key:generate
php artisan migrate --seed  # esquema + ≥15 libros y ≥3 categorías (001)
php artisan test            # suite completa, incluidos los tests de 006
php artisan serve           # → http://127.0.0.1:8000
```

Comprobación del cambio de esquema de `006` (D-030):

```sql
SHOW FULL COLUMNS FROM libros WHERE Field = 'editorial';  -- Collation = utf8mb4_0900_ai_ci
```

## Escenarios de validación

### V-001 — Búsqueda por los cuatro campos (FR-003, SC-001, SC-003)

1. Abrir `http://127.0.0.1:8000/catalogo` y escribir en «Buscar» un texto que coincida con el **título** de un libro conocido; repetir con el **autor**, la **editorial** y los primeros dígitos del **ISBN**.
2. **Esperado**: en los cuatro casos el listado se actualiza solo y muestra únicamente los libros cuyo título, autor, editorial o ISBN **contienen** el texto (coincidencia parcial, en cualquier posición); ningún libro ajeno aparece.
3. Repetir un caso con MAYÚSCULAS y sin tildes (p. ej. «algebra» debe encontrar «Álgebra lineal»): la coincidencia no distingue caso ni acentuación.
   Referencia: [contracts/http-endpoints.md](contracts/http-endpoints.md) §Combinación.

### V-002 — ISBN con ceros iniciales (edge case)

1. Buscar por los primeros dígitos de un ISBN que empiece por `0` (o por su posición parcial intermedia).
2. **Esperado**: coincidencia parcial sobre el valor almacenado, conservando los ceros iniciales; el libro esperado aparece y no se altera el formato del ISBN mostrado.

### V-003 — Filtro de categoría exacto y regla Y (FR-003, FR-005, SC-005)

1. Seleccionar una categoría en el desplegable (sin texto).
2. **Esperado**: solo libros de esa categoría; la opción activa permanece marcada.
3. Con la categoría seleccionada, escribir además un texto que solo algunos de sus libros contienen.
4. **Esperado**: solo los libros que cumplen **ambos** criterios simultáneamente (regla Y); cambiar de categoría actualiza el listado sin pulsar botón alguno.

### V-004 — Sin coincidencias (FR-004, SC-002)

1. Escribir un texto sin coincidencias (p. ej. `zzzznoexiste`).
2. **Esperado**: HTTP 200, **cero** tarjetas, mensaje exacto «No se encontraron libros que coincidan con la búsqueda» y el texto seguido en el campo (criterios visibles para ajustarlos).
3. Comprobar que **no** aparece el mensaje «No hay libros registrados aún» ni un 404.
   Contrato: [contracts/user-messages.md](contracts/user-messages.md) §1.

### V-005 — Criterio vacío o solo espacios (edge case, FR-005)

1. Escribir únicamente espacios en blanco en «Buscar» y dejar el desplegable en «Todas las categorías».
2. **Esperado**: catálogo completo con la paginación habitual de Fase 1 (sin residuos de filtrado).

### V-006 — Limpiar criterios (FR-006, SC-004)

1. Aplicar texto y categoría a la vez y comprobar el listado filtrado.
2. Pulsar «Limpiar» (enlace visible porque hay criterios).
3. **Esperado**: el listado vuelve al catálogo completo (≥ 15 libros, todas las categorías), la URL queda en `/catalogo` sin parámetros, el campo queda vacío, el desplegable en «Todas las categorías» y el enlace «Limpiar» desaparece.

### V-007 — Debounce en escritura rápida (FR-001, edge case)

1. Con las herramientas de red del navegador abiertas, escribir rápido un texto de 6–8 caracteres de golpe en «Buscar».
2. **Esperado**: una **única** petición `GET /catalogo?q=…` tras dejar de escribir (~300 ms), no una por tecla; el último texto escrito es el que determina los resultados; el foco vuelve al campo tras la carga para seguir escribiendo.
3. Cambiar la categoría: el listado se actualiza de inmediato, sin retardo.

### V-008 — Paginación sobre resultados filtrados (FR-005)

1. Aplicar un criterio que deje **más de 10** resultados (p. ej. una categoría con varios libros o un texto corto).
2. **Esperado**: 10 tarjetas por página, «Anterior»/«Siguiente»/números; al navegar, los enlaces **conservan** `q` y `categoria` (comprobar la URL) y el orden sigue siendo `titulo → autor → isbn`.
3. Añadir manualmente `&page=999` a la URL filtrada.
4. **Esperado**: HTTP 404 «Página no válida» con enlace «Volver al catálogo».
   Contrato: [contracts/http-endpoints.md](contracts/http-endpoints.md) §Respuesta `404`.

### V-009 — Estados vacíos diferenciados (edge case)

1. Base migrada **sin** sembrar (`php artisan migrate:fresh` sin `--seed`) → abrir `/catalogo`.
2. **Esperado**: «No hay libros registrados aún» (sin criterio).
3. Con la misma base, escribir cualquier texto en «Buscar».
4. **Esperado**: «No se encontraron libros que coincidan con la búsqueda» con el texto visible en el campo.
5. Con base sembrada, repetir el paso 3 con `categoria` = una categoría sin libros (si procede) o texto sin coincidencias: mismo mensaje de §1.

### V-010 — Pruebas automatizadas

```bash
php artisan test
```

**Esperado**: suites `BusquedaCatalogoTest`, `BusquedaCombinacionTest`, `BusquedaVacioYLimpiezaTest`, `BusquedaPaginacionTest` y `BusquedaInterfazTest` en verde (ver D-035), junto con las de `001` y `002`.

## Criterios de aceptación cubiertos

| SC | Escenario |
|---|---|
| SC-001 | V-001, V-003, V-008 |
| SC-002 | V-004, V-009 |
| SC-003 | V-001, V-002 |
| SC-004 | V-006, V-009 |
| SC-005 | V-003 |
