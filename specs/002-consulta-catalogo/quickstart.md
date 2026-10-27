# Quickstart: validación de 002-consulta-catalogo

**Spec**: [spec.md](spec.md) | **Data model**: [data-model.md](data-model.md) | **Contracts**: [contracts/](contracts/)

Guía de ejecución y validación de extremo a extremo. No contiene implementación: los detalles pertenecen a `tasks.md`.

## Requisitos previos

- Entorno de `001-entorno-datos` funcionando (PHP ≥ 8.3, Composer 2.x, MySQL 8.x local).
- Proyecto Laravel 13 instalado y con la base de datos de prueba sembrada.

## Puesta en marcha

```bash
composer install
cp .env.example .env        # completar credenciales (contrato: 001 contracts/environment-variables.md)
php artisan key:generate
php artisan migrate --seed  # esquema + ≥15 libros y ≥3 categorías (001)
php artisan test            # suite completa, incluidos los tests de 002
php artisan serve           # → http://127.0.0.1:8000
```

## Escenarios de validación

### V-001 — Listado paginado completo (FR-001, SC-001)

1. Abrir `http://127.0.0.1:8000/catalogo`.
2. **Esperado**: primera página con 10 tarjetas (portada, título, autor, año, categoría); con ≥ 15 libros sembrados existen «Siguiente» y números de página.
3. Navegar por todas las páginas y volver a la anterior: se alcanzan **todos** los libros, cada página muestra ≤ 10 y la última no tiene posiciones vacías.

### V-002 — Orden alfabético determinista (FR-005)

1. Crear (p. ej. con `tinker` o en el test) libros: «Álgebra lineal», «algebra abstracta», «Zoología», «Biología» y dos con el mismo título de autores distintos.
2. **Esperado**: «algebra abstracta» y «Álgebra lineal» se ordenan como «algebra…» (sin distinguir tilde ni mayúscula); «Biología» antes que «Zoología»; los títulos iguales se ordenan por autor A–Z y, si coinciden, por ISBN.
3. Repetir la consulta: el orden es idéntico (determinista).
   Referencia: [data-model.md](data-model.md) §Regla de ordenación.

### V-003 — Imagen predeterminada (FR-003, SC-003)

1. Con los datos sembrados (todos sin portada), revisar el listado y una ficha.
2. **Esperado**: el 100 % muestra `public/images/libro-sin-portada.svg`, sin espacios vacíos ni iconos rotos.
3. Asignar a un libro una ruta de portada inexistente y recargar: se muestra igualmente la imagen predeterminada (respaldo `onerror`).
   Contrato: [contracts/ui-structure.md](contracts/ui-structure.md) §5.

### V-004 — Ficha de detalle completa (FR-002, SC-002)

1. Desde una tarjeta del listado (tarjeta entera clicable), abrir el detalle.
2. **Esperado**: HTTP 200 con portada, título, autor, editorial, año de publicación, ISBN, total de ejemplares, ejemplares disponibles y categoría, coincidentes con los datos de prueba.
3. Comprobar el enlace «Volver al catálogo».

### V-005 — Libro inexistente (edge case, Historia 2)

1. Abrir `http://127.0.0.1:8000/catalogo/999999`.
2. **Esperado**: HTTP **404**, vista personalizada con «Libro no encontrado» y enlace «Volver al catálogo» que regresa al listado.

### V-006 — Catálogo vacío (edge case, Historia 1)

1. Base migrada **sin** sembrar (`php artisan migrate:fresh` sin `--seed`) → abrir `/catalogo`.
2. **Esperado**: HTTP 200 con «No hay libros registrados aún» en la zona del listado, sin tarjetas ni controles de paginación.

### V-007 — Página fuera de rango (edge case, Historia 1)

1. Con catálogo sembrado, abrir `/catalogo?page=999`; después `/catalogo?page=0` y `/catalogo?page=abc`.
2. **Esperado**: HTTP **404** en los tres casos, vista personalizada con «Página no válida» y enlace «Volver al catálogo».
   Contrato: [contracts/http-endpoints.md](contracts/http-endpoints.md) §Respuesta 404.

### V-008 — Estructura visual común (FR-004, SC-004)

1. Comparar visualmente listado, detalle y vista 404.
2. **Esperado**: misma cabecera, misma navegación (con «Catálogo») y mismo pie en las tres vistas; una sola aplicación reconocible.

### V-009 — Pruebas automatizadas

```bash
php artisan test
```

**Esperado**: suites `CatalogoIndexTest`, `CatalogoDetalleTest`, `CatalogoOrdenYPaginacionTest`, `CatalogoErroresTest` y `CatalogoLayoutTest` en verde (ver D-016), junto con las de `001`.

## Criterios de aceptación cubiertos

| SC | Escenario |
|---|---|
| SC-001 | V-001, V-002 |
| SC-002 | V-004 |
| SC-003 | V-003 |
| SC-004 | V-008 |
