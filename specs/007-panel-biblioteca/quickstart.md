# Quickstart: validación de 007-panel-biblioteca

**Spec**: [spec.md](spec.md) | **Data model**: [data-model.md](data-model.md) | **Contracts**: [contracts/](contracts/)

Guía de ejecución y validación de extremo a extremo. No contiene implementación: los detalles pertenecen a `tasks.md`.

## Requisitos previos

- Entorno de `001-entorno-datos` funcionando (PHP ≥ 8.3, Composer 2.x, MySQL 8.x local).
- Proyecto Laravel 13 instalado con la base de datos migrada y sembrada (≥ 15 libros, 3 categorías, 3 préstamos activo/devuelto/vencido).
- La migración de colación de `007` sobre `categorias.nombre` incluida en `php artisan migrate`.

## Puesta en marcha

```bash
composer install
cp .env.example .env        # completar credenciales (contrato: 001 contracts/environment-variables.md)
php artisan key:generate
php artisan migrate --seed  # esquema + datos de prueba (001)
php artisan test            # suite completa, incluidos los tests de 007
php artisan serve           # → http://127.0.0.1:8000
```

## Escenarios de validación

### V-001 — Panel completo con datos sembrados (FR-007, SC-001, SC-003)

1. Abrir `http://127.0.0.1:8000/panel`.
2. **Esperado**: HTTP 200 con layout común (misma cabecera/navegación/pie que `/catalogo`, con enlace «Panel») y **las tres secciones** en una sola vista: «Libros más prestados», «Préstamos vencidos» y «Ejemplares disponibles por categoría», con rótulos y cifras en español.
3. Con los 3 préstamos sembrados: el ranking lista al menos el libro del préstamo sembrado con su cifra ≥ 1; vencidos contiene el préstamo `vencido` sembrado con libro, estudiante, carné, fechas y días de retraso ≥ 1; disponibilidad lista las 3 categorías con sus sumas de disponibles.

### V-002 — Ranking: conteo, top 5 y desempates (FR-001, FR-004, SC-002)

1. Con datos controlados (test o `tinker`): crear 6 libros — A con 3 préstamos, B y C con 2 (empate), D y E con 1, F con 0 — situando en el empate dos títulos que difieren en tildes/mayúsculas (p. ej. «Álgebra» y «algebra»).
2. **Esperado**: el ranking tiene exactamente 5 filas (F, sin préstamos, **no** aparece); orden A → B/C (empate resuelto alfabéticamente sin distinguir tilde/mayúscula) → D/E; cada fila muestra su cifra junto al libro; el historial completo cuenta (incluidos préstamos devueltos y vencidos).
3. Repetir la consulta: el orden es idéntico (determinista).
   Referencia: [data-model.md](data-model.md) §Indicador 1.

### V-003 — Ranking sin datos (edge case, FR-008)

1. Base migrada **sin** sembrar y sin préstamos → abrir `/panel`.
2. **Esperado**: la sección «Libros más prestados» muestra «No hay datos disponibles», sin posiciones ni relleno con libros de cuenta 0.

### V-004 — Vencidos: regla de fechas y campos (FR-002, FR-005, SC-002)

1. Crear préstamos: P1 con prevista = **hoy**; P2 con prevista = hoy − 3; P3 con prevista = hoy − 10; P4 devuelto con prevista hoy − 20 (fecha real registrada).
2. **Esperado**: la lista contiene **solo** P3 y P2 (P1 no vence —regla estricta—; P4 está devuelto y se excluye aunque llegó tarde); orden P3 antes que P2 (mayor retraso primero); P3 muestra «7» días de retraso y P2 «3»; cada fila muestra libro, estudiante, carné, fecha de préstamo y devolución prevista.
3. Dar a P3 **dos** préstamos pendientes más del mismo libro: el libro aparece en una fila por préstamo.
   Referencia: [data-model.md](data-model.md) §Indicador 2.

### V-005 — Vencidos sin datos (edge case, FR-008)

1. Con libros y préstamos pero **ninguno** vencido (todos a tiempo o devueltos) → abrir `/panel`.
2. **Esperado**: «No hay datos disponibles» en la sección de vencidos; las otras dos secciones se muestran con normalidad.

### V-006 — Disponibilidad por categoría (FR-003, SC-002)

1. Con categorías «Historia», «Álgebra» y «Zoología» con libros y disponibilidades conocidas, más una categoría «Vacía» sin libros.
2. **Esperado**: se listan las categorías con libros —incluida la de **0** disponibles, mostrando «0»—; «Vacía» **no** aparece; el orden es alfabético español: «Álgebra» se ordena como «Algebra» (sin distinguir tilde ni mayúscula); cada cifra es la suma de `ejemplares_disponibles` de sus libros y coincide con el cálculo manual.
3. Quitar todos los libros de todas las categorías → «No hay datos disponibles» en la sección.
   Referencia: [data-model.md](data-model.md) §Indicador 3.

### V-007 — Fecha de consulta del día (edge case, FR-005)

1. Registrar un préstamo cuya devolución prevista es **mañana**; abrir `/panel` hoy.
2. **Esperado**: no aparece en vencidos (la comparación usa la fecha del día de consulta, definida por el servidor, sin entrada del usuario).
3. Simular en test la misma data con prevista = hoy − 1: aparece con 1 día de retraso.

### V-008 — Solo consulta y estructura (FR-006, SC-003)

1. Revisar el HTML de `/panel`.
2. **Esperado**: no hay formularios, botones de escritura ni enlaces de eliminación; la respuesta es idéntica en dos GET consecutivos con los mismos datos; la vista comparte layout con el catálogo y el enlace «Panel» navega al panel desde cualquier vista con el layout común.

### V-009 — Pruebas automatizadas

```bash
php artisan test
```

**Esperado**: suites `PanelRankingTest`, `PanelVencidosTest`, `PanelDisponibilidadTest`, `PanelEstadosVaciosTest` y `PanelLayoutTest` en verde (ver D-026), junto con las de fases previas.

## Criterios de aceptación cubiertos

| SC | Escenario |
|---|---|
| SC-001 | V-001 |
| SC-002 | V-002, V-004, V-006 |
| SC-003 | V-001, V-008 |
| SC-004 | V-002, V-003, V-004, V-005, V-006, V-007 |
