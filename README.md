# Biblioteca Universitaria — Fase 1: entorno y base de datos

Aplicación web local para la gestión de una biblioteca universitaria, construida
con **Laravel 13** (PHP >= 8.3) y una base de datos relacional. Esta primera
fase deja el entorno y los datos listos: esquema versionado con migraciones,
datos de prueba cargados y avisos en español ante fallos de conexión.

## Requisitos previos

| Requisito | Versión mínima |
|---|---|
| PHP | 8.3 (con extensiones `pdo_mysql`, `mbstring`, `openssl`, `curl`, `fileinfo`, `zip`) |
| Composer | 2.x |
| MySQL | 8.x local (gratuito) |
| Git | cualquier versión reciente |

> **Motor de datos**: el motor elegido y documentado es **MySQL 8 local**
> (gratuito y sin servicios de pago). La aplicación es compatible también con
> **MariaDB** (casi idéntica a MySQL: basta con apuntar `DB_HOST`/`DB_PORT` a
> su instalación) y con **PostgreSQL local** (requiere cambiar
> `DB_CONNECTION=pgsql` y el puerto `5432`). El resto de la guía asume MySQL.

## Instalación (una sola vez por entorno)

```bash
composer install                    # 1. dependencias de PHP
cp .env.example .env                # 2. crear el archivo local de configuración
# 3. editar .env y completar los datos de conexión (ver abajo)
php artisan key:generate            # 4. generar la APP_KEY
php artisan migrate --seed          # 5. crear el esquema y cargar los datos de prueba
```

### Configuración de conexión (`.env`)

El archivo `.env` **no se versiona** (está en `.gitignore`); solo se versiona
`.env.example` como plantilla sin secretos. Edita tu `.env` local con los datos
de tu instalación de MySQL:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=biblioteca
DB_USERNAME=root
DB_PASSWORD=            # la contraseña de tu usuario local, nunca se versiona
```

### Crear la base de datos

La base de datos debe existir **antes** de ejecutar las migraciones. Crea las
dos bases (desarrollo y pruebas) en tu cliente de MySQL (por ejemplo,
`mysql -u root -p`):

```sql
CREATE DATABASE biblioteca CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE biblioteca_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

- `biblioteca`: base de datos de desarrollo (la usa `php artisan migrate --seed`).
- `biblioteca_test`: base de datos dedicada para `php artisan test`
  (configurada en `phpunit.xml`; se recrea automáticamente en cada ejecución).

## Comando de inicio

```bash
php artisan serve
```

La aplicación queda disponible en **http://127.0.0.1:8000**, conectada a la
base de datos. Es el único comando necesario para arrancar una vez instalado.

Si la conexión a la base de datos falla (credenciales incorrectas, motor
apagado o base inexistente), la aplicación **no sirve ninguna vista de datos** y
muestra un aviso en español indicando que hay que revisar la configuración de
conexión en el archivo `.env`. El aviso nunca incluye usuario, contraseña, host
ni SQL.

## Verificación

```bash
php artisan migrate:status   # migraciones ejecutadas (100 % «Ran»)
php artisan migrate:fresh --seed   # rehacer el entorno completo
php artisan test             # suite de pruebas (verde)
```

## Diagrama entidad-relación

```text
categorias (1) ──────< (N) libros (1) ──────< (N) prestamos
   nombre UNIQUE           isbn UNIQUE            estado: activo|devuelto|vencido
                         FK categoria_id            FK libro_id → libros
                         → categorias (RESTRICT)    (RESTRICT)
```

- **categorias**: `nombre` único (`varchar(100)`).
- **libros**: `isbn` único de 13 dígitos, ejemplares totales/disponibles,
  `portada` opcional (`NULL` = imagen predeterminada), pertenece a exactamente
  una categoría.
- **prestamos**: estudiante, carnet, fechas de préstamo/devolución prevista y
  real, y estado (`activo`, `devuelto` o `vencido`); pertenece a exactamente un
  libro.

El esquema se crea **exclusivamente con migraciones** versionadas en
`database/migrations/`; nunca con SQL manual.

Los datos de prueba (`php artisan migrate:fresh --seed`) cargan 3 categorías,
16 libros y 3 préstamos (uno por estado), manteniendo siempre la coherencia
`ejemplares_disponibles = ejemplares_totales − ejemplares en préstamos activos`.

## Eliminación de registros relacionados

La eliminación de un registro con hijos está **bloqueada** mientras existan
registros relacionados. La aplicación lo comprueba antes de borrar y la base de
datos lo refuerza con claves foráneas `ON DELETE RESTRICT`:

| Intento | Resultado | Aviso |
|---|---|---|
| Eliminar una categoría que tiene libros | Bloqueado | La categoría tiene libros asociados y no puede eliminarse mientras los tenga |
| Eliminar un libro que tiene préstamos | Bloqueado | El libro tiene préstamos asociados y no puede eliminarse mientras los tenga |
| Eliminar una categoría sin libros | Eliminación correcta | Confirmación de la operación |
| Eliminar un libro sin préstamos | Eliminación correcta | Confirmación de la operación |

Toda eliminación se realiza mediante `DELETE` con confirmación, nunca mediante
`GET`. El aviso de bloqueo se muestra en español y nunca expone credenciales ni
SQL.

## Estructura del proyecto

```text
app/Models/            # Categoría, Libro y Prestamo (Eloquent)
app/Services/          # EliminacionService (comprobación previa de borrado)
app/Exceptions/        # FalloConexion (aviso en español ante error de BD)
database/migrations/   # única fuente del esquema
database/factories/    # datos de prueba deterministas
database/seeders/      # CatalogoSeeder (3 categorías, 16 libros, 3 préstamos)
resources/views/       # layout común, página de inicio y aviso de conexión
tests/Feature/         # pruebas de migraciones, semilla, conexión y borrado
```
