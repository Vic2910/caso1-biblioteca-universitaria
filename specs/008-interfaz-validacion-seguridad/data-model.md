# Data Model: 008-interfaz-validacion-seguridad (Interfaz, validación y seguridad transversales)

**Spec**: [spec.md](spec.md) | **Research**: [research.md](research.md) | **Date**: 2026-10-26

Fuente de verdad: FR-001 a FR-012, sección *Key Entities* de la especificación y Principios II, IV y V de la constitución (Versión 1.0). Esta funcionalidad **no añade entidades de negocio ni reglas propias** (supuesto de la spec): protege y presenta los datos de `libros`, `categorias` y `prestamos` definidos en [../001-entorno-datos/data-model.md](../001-entorno-datos/data-model.md). El único esquema nuevo es la tabla `users`, exigida por el inicio de sesión (FR-009).

---

## Diagrama entidad-relación

```text
users (1) ──── sin relación con el catálogo ──── identifica al usuario autenticado

categorias (1) ──────< (N) libros ──────< (N) prestamos        (sin cambios: 001)
```

- `users` **no** tiene claves foráneas hacia el catálogo: el inicio de sesión solo identifica al usuario (Key Entities: «solo aporta la identificación del usuario y no define roles ni permisos diferenciados»).
- Ninguna tabla de negocio cambia: ni columnas, ni índices, ni claves.

---

## Entidad nueva: `users`

| Campo | Tipo | Restricciones | Uso en esta funcionalidad |
|---|---|---|---|
| `id` | bigint unsigned, PK | — | Identidad de la sesión |
| `name` | varchar(255) | NOT NULL | Nombre visible en el bloque de sesión |
| `email` | varchar(255) | NOT NULL, UNIQUE | Identificador del inicio de sesión (campo «Correo») |
| `password` | varchar(255) | NOT NULL | Solo hash (`Hash::make`); **nunca** en claro ni en el repositorio |
| `remember_token` | char(100), nullable | — | Token estándar de Laravel (no se usa «recordarme» en la UI) |
| `created_at` / `updated_at` | timestamps | — | — |

- **Sin columnas de roles ni permisos**: todos los usuarios autenticados pueden realizar las mismas operaciones (supuesto de la spec).
- Modelo `User` con `$fillable` (`name`, `email`, `password`) y `hidden` (`password`, `remember_token`), sin relaciones hacia el catálogo (D-037).

### Cambios de esquema

| Migración | Cambio | Motivo |
|---|---|---|
| `database/migrations/*_create_users_table.php` | Crear `users` (si el esqueleto de Laravel no la incluye) | Almacenar las cuentas creadas por el equipo para FR-009; siempre con migración, como exige el Principio I |

- **Único** cambio de esquema de `008`. No se crean ni modifican columnas, índices ni claves en `libros`, `categorias` ni `prestamos`.
- Las contraseñas de las cuentas se guardan **hasheadas**; las credenciales de la base de datos siguen fuera del repositorio en `.env` (heredado D-007).

### Datos de prueba

| Requisito | Uso en `008` |
|---|---|
| `UserSeeder` con al menos una cuenta creada por el equipo | Permite ejecutar los escenarios de login y de acceso sin sesión (`php artisan migrate:fresh --seed`) |
| Contraseña de prueba hasheada con `Hash::make` | Verifica que ninguna contraseña aparece en claro en el repositorio |
| Catálogo sembrado de `001` (≥ 15 libros, 3 categorías, 3 préstamos) | Los estados vacíos y los listados protegidos muestran datos reales |

---

## Sesión: estados y transiciones (FR-009, SC-005)

| Estado | Descripción | Transición |
|---|---|---|
| **Sin sesión** | El usuario no ha iniciado sesión o su sesión caducó | `POST /login` con credenciales válidas → **Sesión iniciada**; cualquier operación solicitada redirige a `/login` sin ejecutarse |
| **Sesión iniciada** | Autenticado con `Auth::check()` | 2 horas sin actividad → **Sesión caducada**; `POST /logout` → **Sin sesión** |
| **Sesión caducada** | `SESSION_LIFETIME=120` minutos de inactividad agotados | Se solicita iniciar sesión de nuevo antes de procesar cualquier operación → **Sesión iniciada** |

- **Reglas**:
  - Toda ruta de negocio (salvo `GET|POST /login`) está bajo el middleware `auth` (D-037): sin sesión → redirección a `/login`, **operación no ejecutada** (FR-009, edge case de autenticación).
  - La actividad se actualiza en cada petición, de modo que el límite es de **inactividad**, no de duración absoluta (D-038).
  - El login fallido **no** cambia de estado a «bloqueado»: no existe ese estado (FR-010, D-039).

---

## Inventario transversal de formularios y reglas de validación (FR-003, FR-004)

Patrón único (D-041): Form Request → `$request->validated()` → `back()->withErrors()->withInput()` → error **en español junto al campo** con `old()`. Reglas concretas heredadas de la funcionalidad que las define:

| # | Formulario | Origen de las reglas | Reglas resumidas |
|---|---|---|---|
| 1 | Inicio de sesión (`/login`) | `008` D-037/D-041 | `email` obligatorio con formato de correo; `password` obligatorio. Fallo de credenciales → mensaje genérico (no es error de campo) |
| 2 | Crear/editar libro | `003` D-019 | ISBN único de 13 dígitos; año 1900–año actual; obligatorios y longitudes; categoría existente; portada JPG/PNG/WebP ≤ 2 MB |
| 3 | Crear/editar categoría | `003` D-020 | Nombre obligatorio, único normalizado (tildes, mayúsculas, espacios) |
| 4 | Registrar préstamo | `004` D-021 | Libro existente con disponibilidad; nombre y carné obligatorios; fechas válidas (devolución prevista posterior al préstamo); límite de 3 delegado al servicio |
| 5 | Devolver préstamo | `004` D-021 | Fecha real obligatoria, no anterior al préstamo; préstamo activo o vencido |
| 6 | Subir portada | `005` (validación de constitución) | Archivo JPG/PNG/WebP ≤ 2 MB, validado en el servidor |
| 7 | Búsqueda/filtro | `006` D-029/D-031 | Sin botón de envío; `q` recortado, `categoria` existente; normalización en servidor |

- **Todos los campos inválidos** a la vez muestran **todos** los errores, conservando cada valor en su campo (edge case, SC-002).
- Corregir un solo campo tras el rechazo mantiene los demás valores mientras la operación siga inválida (edge case).
- Las operaciones utilizan **únicamente** `$request->validated()` (FR-003): ningún dato sin validar llega a la base de datos.

---

## Reglas transversales de seguridad (FR-005, FR-006, FR-007)

| Regla | Garantía | Verificación |
|---|---|---|
| CSRF en todo formulario | `@csrf` en cada `<form>`; middleware sin `$except`; fallo → mensaje exacto junto al formulario, sin guardar datos y conservando lo ingresado (D-040) | `CsrfProteccionTest` |
| Escapado de salida | `{{ }}` en toda salida de datos; `{!! !!}` prohibido salvo HTML generado por Laravel (D-042) | `EscapadoSalidaTest` (auditoría de `resources/views`) |
| Sin SQL concatenado | Eloquent/Query Builder parametrizado en todas las consultas (heredado D-003) | suites de cada módulo |
| Eliminaciones | Solo `DELETE` mediante form con `@method('DELETE')` y confirmación previa; `GET` sobre la URL de eliminación → **405**, sin modificar registros (D-043) | `EliminacionDeleteTest` |
| Credenciales | Solo en `.env` (ignorado por Git); contraseñas con hash (heredado D-007, D-037) | revisión del repositorio (supuesto de la spec) |

---

## Casos límite cubiertos

| Caso límite (spec) | Tratamiento en el modelo |
|---|---|
| Token CSRF ausente, caducado o manipulado | Rechazo + mensaje exacto en español junto al formulario; valores conservados; nada guardado (D-040) |
| Sesión caducada tras 2 h de inactividad con formulario abierto | Operación no procesada; se pide iniciar sesión de nuevo (§Sesión) |
| Login fallido repetido | Mensaje genérico único, sin estado de bloqueo ni espera (D-039) |
| `GET` de eliminación, incluidas repetidas | 405 en todos los intentos; registros intactos (D-043) |
| Texto con `<script>` u órdenes SQL en cualquier campo | Se almacena y se muestra como texto escapado (D-042) |
| Varios campos inválidos a la vez | Todos los errores junto a sus campos, sin perder valores (§Inventario) |
| Caracteres especiales del español | `utf8mb4` heredado de `001`; mensajes en español sin pérdidas |
| Fallo de la eliminación tras confirmar | Mensaje en español; el registro permanece intacto (D-043) |
| Lista sin registros | Parcial `estado-vacio` con «No hay registros» + acción (D-046) |

---

## Validación y transiciones de estado (resumen)

- **Entidades de negocio**: sin cambios de esquema ni de reglas; sus reglas siguen centralizadas donde las definieron `001`–`007` (Principio III intacto: `008` no duplica `PrestamoService`).
- **Única transición nueva**: la sesión (§Sesión), controlada por Laravel con `SESSION_LIFETIME=120`.
- **Estados vacíos**: los listados de libros, categorías y préstamos muestran mensaje + acción (FR-012); el panel conserva su «No hay datos disponibles» de `007`.
