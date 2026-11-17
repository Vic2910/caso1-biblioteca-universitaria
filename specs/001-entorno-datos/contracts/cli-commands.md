# Contract: Comandos locales (FR-001, FR-003, FR-004)

**Spec**: [../spec.md](../spec.md) | **Research**: [../research.md](../research.md) D-003, D-005, D-009

Todos los comandos se ejecutan en la raíz del proyecto Laravel. El README documenta exactamente esta secuencia.

## Instalación y preparación (una sola vez por entorno)

| # | Comando | Condición de éxito |
|---|---|---|
| 1 | `composer install` | Dependencias instaladas (Composer 2.x) |
| 2 | `cp .env.example .env` | `.env` local creado (no versionado) |
| 3 | *(editar `.env` con datos de conexión)* | Ver [environment-variables.md](environment-variables.md) |
| 4 | `php artisan key:generate` | `APP_KEY` generada |
| 5 | `php artisan migrate --seed` | Migraciones aplicadas **y** datos de prueba cargados, salida `DONE` sin errores |

> `migrate --seed` solo puede ejecutarse sobre una base creada o refrescada (supuesto de la especificación). Para rehacer el entorno: `php artisan migrate:fresh --seed`.

## Comando de inicio único

```bash
php artisan serve
```

- **Éxito**: servidor local en `http://127.0.0.1:8000` y la aplicación responde conectada a la base de datos.
- **Fallo de conexión**: el servidor no sirve la aplicación; se emite el aviso de [user-messages.md](user-messages.md) (FR-008).

## Comandos de verificación

| Comando | Propósito | Éxito esperado |
|---|---|---|
| `php artisan migrate:status` | Todas las migraciones marcadas como ejecutadas | 100 % `Ran` |
| `php artisan migrate:rollback` | Reversibilidad del esquema | Reversa sin errores; `migrate` vuelve a aplicarlas |
| `php artisan db:seed` | Recarga de datos de prueba (solo sobre base refrescada) | ≥ 15 libros, ≥ 3 categorías, 3 préstamos (activo/devuelto/vencido) |
| `php artisan test` | Pruebas PHPUnit (`tests/Feature/`) | Todas en verde |

## Restricciones del contrato

- El esquema **solo** cambia mediante nuevas migraciones; nunca con SQL manual sobre la base compartida (FR-003).
- Los seeders son idempotentes sobre una base refrescada (`migrate:fresh --seed`); recargarlos sobre datos modificados manualmente está fuera del alcance de esta fase.
