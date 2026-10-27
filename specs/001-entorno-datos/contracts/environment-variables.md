# Contract: Variables de entorno de conexión (FR-007)

**Spec**: [../spec.md](../spec.md) FR-007 | **Research**: [../research.md](../research.md) D-007

## Reglas

1. Las credenciales viven **exclusivamente** en `.env` (ignorado por Git). El repositorio solo versiona `.env.example` sin secretos.
2. Ninguna credencial aparece en código, migraciones, seeders ni documentación versionada.
3. Cambiar credenciales no exige editar ningún archivo versionado: basta editar `.env` local.

## Variables requeridas

| Variable | Ejemplo en `.env.example` | Descripción |
|---|---|---|
| `DB_CONNECTION` | `mysql` | Motor elegido entre los permitidos (MySQL/MariaDB/PostgreSQL) |
| `DB_HOST` | `127.0.0.1` | Host del motor de datos |
| `DB_PORT` | `3306` | Puerto (3306 MySQL/MariaDB, 5432 PostgreSQL) |
| `DB_DATABASE` | `biblioteca` | Nombre de la base de datos (previamente creada) |
| `DB_USERNAME` | `root` | Usuario con permisos sobre la base de datos |
| `DB_PASSWORD` | *(vacío en `.env.example`)* | **Nunca versionado** |
| `APP_KEY` | *(generado con `php artisan key:generate`)* | Clave de la aplicación, no se versiona con valor real |

## Incumplimiento

- Presencia de `.env` en Git o de credenciales reales en `.env.example`: **bloqueante** (FR-007).
- Ante valores incorrectos, la aplicación no continúa: ver [user-messages.md](user-messages.md) (FR-008).
