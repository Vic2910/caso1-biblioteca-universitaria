# Research: 001-entorno-datos (Entorno y base de datos)

**Date**: 2026-10-26 | **Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md)

Todos los `NEEDS CLARIFICATION` del Contexto Técnico quedan resueltos en este documento.

---

## D-001: Stack tecnológico (fijado por la constitución)

- **Decision**: PHP 8.3+ (8.3–8.5), Laravel 13, Composer 2.x, ejecución con `php artisan serve`, vistas Blade con CSS propio.
- **Rationale**: La constitución (v1.0, Restricciones → Tecnologías) fija estas tecnologías y FR-005 obliga a respetarlas. Verificado: Laravel 13 se publicó el 2026-03-17 y exige PHP >= 8.3 como mínimo (laravel.com/docs/13.x/deployment), por lo que el requisito de la constitución y el del framework coinciden.
- **Alternatives considered**: Laravel 12 u otros stacks (rechazados: la constitución prevalece); PHP < 8.3 (incompatible con Laravel 13).

## D-002: Motor de base de datos

- **Decision**: **MySQL 8.x local** (gratuito, instalado en la máquina del equipo), declarado en el README tal como exige FR-005.
- **Rationale**: La constitución permite MySQL, MariaDB o Supabase/PostgreSQL. MySQL 8 es gratuito, no implica servicios de pago (FR-001), es el driver por defecto de Laravel (`DB_CONNECTION=mysql`) y el más documentado para un equipo local en Windows/Linux. Supabase es un servicio en la nube, innecesario para una app local.
- **Alternatives considered**: MariaDB (casi idéntico a MySQL; viable si el equipo ya lo tiene instalado — el README lo admitirá como variante); PostgreSQL local (permitido, pero exige cambiar el driver y es menos habitual en los entornos del equipo); Supabase/PostgreSQL (servicio externo, evitado por FR-001).

## D-003: Creación del esquema

- **Decision**: Esquema creado **exclusivamente con migraciones de Laravel** (archivos versionados en `database/migrations/`), con claves foráneas `restrictOnDelete()` y restricciones de unicidad sobre `libros.isbn` y `categorias.nombre`.
- **Rationale**: FR-003 y el Principio I de la constitución lo exigen textualmente ("sus tablas se crean exclusivamente con migraciones"). Las migraciones se ejecutan en orden cronológico, permiten `down()` para rollback y mantienen el esquema sincronizado con modelos y vistas.
- **Alternatives considered**: Scripts SQL manuales (rechazado: no versionados como migraciones); Doctrine DBAL/schema dumps (rechazado: añade dependencia sin necesidad); crear tablas desde seeders o factories (rechazado: viola FR-003).

## D-004: Conducta de eliminación de datos relacionados

- **Decision**: **`ON DELETE RESTRICT` en ambas claves foráneas** (`libros.categoria_id` → `categorias`, `prestamos.libro_id` → `libros`). Eliminar una categoría con libros o un libro con préstamos falla en la base de datos y la aplicación muestra un aviso en español que indica que existen registros relacionados. La conducta se documenta en el README (FR-006, SC-005).
- **Rationale**: FR-006 exige bloquear la eliminación mientras existan registros relacionados; `RESTRICT` lo garantiza en la capa de datos, no solo en la aplicación, y es la estrategia por defecto recomendada para referencias que no deben borrarse por accidente. Necesidad de `restrictOnDelete()` explícito porque Laravel usa `cascade` por defecto en `foreignId()->constrained()`.
- **Alternatives considered**: `CASCADE` (rechazado: borra libros al borrar una categoría, viola FR-006); `SET NULL` (imposible: `categoria_id` y `libro_id` son obligatorios, FR-002 exige que el 100 % tenga categoría/libro); soft deletes (rechazado: no lo pide la especificación y añade lógica de restauración fuera del alcance de la Fase 1); solo validación en aplicación (rechazada como única vía: puede eludirse con SQL directo; se mantiene además la comprobación previa para dar un mensaje amable).

## D-005: Datos de prueba (seeders y factories)

- **Decision**: Seeders + factories de Eloquent en `database/seeders/` y `database/factories/`, invocados con `php artisan migrate:fresh --seed` (o `db:seed`). Contenido mínimo:
  - ≥ 15 libros con ISBN únicos de 13 dígitos, todos **sin archivo de portada real** (`portada = null` → imagen predeterminada).
  - Exactamente 3 categorías (mínimo tres registros por catálogo) con nombres únicos.
  - 3 préstamos que cubren los tres estados: uno `activo`, uno `devuelto`, uno `vencido`.
  - Invariante: `ejemplares_disponibles = ejemplares_totales − ejemplares_en_préstamos_activos` para cada libro.
  - Orden de seeding: categorías → libros → préstamos (respetando las claves foráneas).
- **Rationale**: FR-004, SC-002 y las clarificaciones de sesión 2026-10-26. Las factories de Laravel permiten estados (`activo()`, `devuelto()`, `vencido()`) y relaciones (`for()`, `recycle()`); los seeders controlan el orden frente a las restricciones de clave foránea.
- **Alternatives considered**: Inserts SQL crudos (rechazado: no reutiliza modelos ni factories y se desvía del estándar Laravel); datos aleatorios sin invariante de disponibilidad (rechazado: viola la clarificación de coherencia); portadas reales en `storage` (rechazado por clarificación: imagen predeterminada en toda la fase).

## D-006: Manejo de fallos de conexión (FR-008)

- **Decision**: Capturar las excepciones de conexión (`Illuminate\Database\QueryException` / `PDOException` con fallo de acceso) en el manejador de excepciones de la aplicación (`bootstrap/app.php`, `withExceptions`) y renderizar una vista propia en **español** que explique el fallo, indique que hay que revisar la configuración de conexión (`.env`) y **no muestre credenciales ni mensajes SQL con credenciales**. La aplicación no continúa (respuesta de error, no se sirven vistas de datos).
- **Rationale**: FR-008 y SC-004 exigen bloquear el inicio con un aviso visible en español en el 100 % de los intentos, sin exponer credenciales. El mensaje por defecto de PDO/Laravel puede contener host, usuario y parte del SQL, por lo que debe sustituirse por un mensaje controlado.
- **Alternatives considered**: Middleware de health-check en cada petición (rechazado como única vía: más coste y solo retrasa el mismo fallo); dejar la página de error 500 por defecto (rechazado: expone detalles técnicos y no está en español); comprobar la conexión solo en `php artisan serve` (rechazado: el fallo también puede ocurrir al consultar datos).

## D-007: Credenciales fuera del repositorio (FR-007)

- **Decision**: Usar el mecanismo estándar de Laravel: `.env` ignorado por Git (`.gitignore` por defecto) y **`.env.example` versionado** con valores de ejemplo sin secretos. Toda la configuración de conexión se lee de variables de entorno (`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`), de modo que las credenciales cambian sin editar archivos versionados.
- **Rationale**: FR-007 y las Restricciones → Seguridad de la constitución ("excluir credenciales del repositorio").
- **Alternatives considered**: Archivo de configuración versionado con credenciales (rechazado: viola FR-007); vault externo (rechazado: dependencia innecesaria para app local).

## D-008: Estrategia de pruebas

- **Decision**: PHPUnit (incluido en Laravel 13) con tests de característica (`tests/Feature/`) sobre una base de datos de prueba MySQL dedicada (p. ej. `biblioteca_test`) usando `RefreshDatabase`; `php artisan test` como comando único.
- **Rationale**: Laravel 13 trae PHPUnit y Pest como estándar; se elige PHPUnit por ser el incluido por defecto sin dependencias adicionales. `RefreshDatabase` recrea el esquema desde migraciones en cada test, lo que valida a la vez que las migraciones son correctas y reversibles. Se usa el mismo motor MySQL que en desarrollo para fidelidad de restricciones FK/unicidad.
- **Alternatives considered**: SQLite en memoria (rechazado: no valida el comportamiento real de `RESTRICT` y tipos de MySQL); solo verificación manual (rechazado: SC-001/SC-002/SC-005 piden evidencia reproducible); Pest (viable, misma base, se adoptaría si el equipo lo prefiere).

## D-009: Comando único de inicio (FR-001)

- **Decision**: Documentar en el README un flujo de arranque que termina en **un único comando de inicio**: `php artisan serve`. La preparación previa (composer install, `.env`, key:generate, migrate, seed) se documenta como pasos de instalación únicos; el comando de arranque del servidor es siempre el mismo.
- **Rationale**: FR-001 ("único comando de inicio documentado"), Principio V (ejecución con `php artisan serve`) y SC-003 (instalación completa siguiendo solo el README).
- **Alternatives considered**: Script bash/Makefile multiplataforma (rechazado como obligatorio: añade dependencia de herramientas; puede ofrecerse como atajo opcional declarado en el README); Docker (rechazado: peso excesivo y la constitución pide ejecución local directa).

## Resolución de NEEDS CLARIFICATION

| Unknown del Contexto Técnico | Resolución |
|---|---|
| Language/Version | PHP 8.3–8.5 (D-001) |
| Primary Dependencies | Laravel 13, Composer 2.x (D-001) |
| Storage | MySQL 8.x local (D-002) |
| Testing | PHPUnit + RefreshDatabase sobre MySQL de prueba (D-008) |
| Target Platform | Local (equipo del desarrollador), `php artisan serve` (D-001, D-009) |
| Project Type | Web app Laravel (monolito Blade) (D-001) |
| Performance Goals | Sin requisitos de tráfico en Fase 1; datos de prueba ≤ decenas de filas (sin NEEDS CLARIFICATION) |
| Constraints | Sin servicios de pago, credenciales fuera del repo, mensajes en español (D-006, D-007) |
| Scale/Scope | 3 entidades, ~20+ filas sembradas, 1 repositorio (spec + constitución) |

**Ningún `NEEDS CLARIFICATION` queda sin resolver.**
