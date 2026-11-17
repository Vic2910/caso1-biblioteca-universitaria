# Quickstart: validación de 001-entorno-datos

**Spec**: [spec.md](spec.md) | **Data model**: [data-model.md](data-model.md) | **Contracts**: [contracts/](contracts/)

Guía de ejecución y validación de extremo a extremo. No contiene implementación: los detalles de creación pertenecen a `tasks.md`.

## Requisitos previos

- PHP >= 8.3 con extensiones PDO (ver `composer doctor` / docs de Laravel 13)
- Composer 2.x
- MySQL 8.x local (o MariaDB/PostgreSQL, según lo declarado en el README)
- Git

## Puesta en marcha

```bash
composer install
cp .env.example .env        # y completar credenciales en .env (contrato: contracts/environment-variables.md)
php artisan key:generate
php artisan migrate --seed  # esquema versionado + datos de prueba
php artisan serve           # comando de inicio único → http://127.0.0.1:8000
```

Detalle y condiciones de éxito de cada paso: [contracts/cli-commands.md](contracts/cli-commands.md).

## Escenarios de validación

### V-001 — Arranque siguiendo solo el README (FR-001, SC-003)

1. En un entorno limpio, seguir exclusivamente los pasos del README.
2. **Esperado**: la aplicación responde en `http://127.0.0.1:8000` conectada a la base de datos; sin servicios de pago ni pasos no documentados.

### V-002 — Esquema versionado (FR-003)

1. `php artisan migrate:status` → 100 % de migraciones ejecutadas.
2. `php artisan migrate:rollback && php artisan migrate` → reversión y reaplicación sin errores.
3. Inspeccionar el esquema: FK `libros.categoria_id` y `prestamos.libro_id` con `ON DELETE RESTRICT`; índices únicos en `libros.isbn` y `categorias.nombre`.
   Referencia: [data-model.md](data-model.md).

### V-003 — Datos de prueba (FR-004, SC-002)

Tras `php artisan migrate:fresh --seed` (comprobaciones por consola/`tinker`):

1. ≥ 15 libros, ≥ 3 categorías, exactamente 3 préstamos.
2. Los tres préstamos cubren los estados `activo`, `devuelto` y `vencido`.
3. Todos los libros tienen `portada = NULL` (imagen predeterminada).
4. Para cada libro: `ejemplares_disponibles = ejemplares_totales − ejemplares en préstamos activos`.

### V-004 — Relaciones (FR-002, SC-001)

1. 100 % de los libros tiene categoría asignada; 100 % de los préstamos apunta a un libro existente.
2. Comprobar en la consola las relaciones Eloquent `Libro→Categoria` y `Prestamo→Libro`.

### V-005 — Fallo de conexión (FR-008, SC-004)

1. Introducir credenciales erróneas en `.env` (o detener MySQL) y acceder a la aplicación.
2. **Esperado**: aviso visible en español que explica el fallo y cómo revisar la configuración; **sin** credenciales ni SQL expuestos; no se sirve ninguna vista de datos.
   Contrato: [contracts/user-messages.md](contracts/user-messages.md) §1.
3. Restaurar la configuración correcta → la aplicación vuelve a funcionar.

### V-006 — Conducta de eliminación (FR-006, SC-005)

1. Intentar eliminar una categoría con libros → bloqueado, aviso en español con el motivo.
2. Intentar eliminar un libro con préstamos → bloqueado, aviso en español con el motivo.
3. Eliminar una categoría sin libros → éxito; eliminar un libro sin préstamos → éxito.
4. Verificar que el README explica esta conducta en español (sección «Eliminación de registros relacionados»).

### V-007 — Credenciales fuera del repositorio (FR-007)

1. `git status` / `git ls-files | grep .env` → `.env` **no** está versionado; `.env.example` sí, sin secretos.
2. Cambiar `DB_PASSWORD` en `.env` → sin modificar ningún archivo versionado.

### V-008 — Pruebas automatizadas

```bash
php artisan test
```

**Esperado**: tests de característica en verde cubriendo migraciones/semilla (V-002 a V-004), fallo de conexión (V-005) y restricciones de eliminación (V-006).

## Criterios de aceptación cubiertos

| SC | Escenario |
|---|---|
| SC-001 | V-004 |
| SC-002 | V-003 |
| SC-003 | V-001 |
| SC-004 | V-005 |
| SC-005 | V-006 |
