# Data Model: Rediseño de interfaz a estándares mundiales (WCAG 2.1 AAA aplicable)

**Spec**: [spec.md](spec.md) FR-021, Key Entities | **Research**: [research.md](research.md) D-053, D-054, D-055, D-056, D-060 | **Plan**: [plan.md](plan.md)

## Cambios de esquema

**Ninguno.** Tabla vacía por diseño (FR-021, gate G1):

- No se crean, modifican ni eliminan migraciones, tablas, columnas, índices ni claves foráneas.
- No se añaden atributos a `libros`, `categorias`, `prestamos` ni `users`.
- No se crean entidades nuevas: la única pieza backend nueva es el endpoint `POST /sesion/extender` ([contracts/session.md](contracts/session.md)), que solo renueva la sesión de Laravel y no escribe en base de datos.

## Entidades existentes: qué cambia (solo presentación)

| Entidad | Atributos que la interfaz muestra | Cambio en 009 |
|---|---|---|
| **Libro** | portada (o imagen predeterminada), título, autor, editorial, año de publicación, ISBN, ejemplares totales/disponibles, categoría | Rediseño visual de tarjeta y ficha; la disponibilidad se comunica **con texto + icono**, nunca solo con color (FR-003) |
| **Categoría** | nombre, número de libros asociados (si se muestra) | Rediseño de listado y tarjeta; misma regla de estado sin color |
| **Préstamo** | libro (título + ISBN), nombre y carné del estudiante, fecha de préstamo, devolución prevista, devolución real («—» si `NULL`), estado `activo`/`devuelto`/`vencido` | El estado pasa de «badge de color» a **texto + icono + color**; el botón de devolución y el de registro son ahora acción principal visible |
| **Usuario (cuenta)** | nombre, correo | Sin cambios; sin roles ni permisos diferenciados (heredado de `008`) |

Reglas de negocio **intactas** (no se tocan): ISBN único de 13 dígitos; año 1900…año actual; devolución prevista posterior al préstamo; disponibilidad obligatoria; máximo tres préstamos activos por estudiante; cálculo de `vencido`; portadas JPG/PNG/WebP ≤ 2 MB con reemplazo y borrado correctos.

## Estados de sesión (FR-005)

```text
[Activa] --(inactividad >= 115 min: quedan 5 min)--> [Aviso] --("Extender sesión")--> [Activa]
    |                                                     |
    |                                                     +--(sin respuesta hasta 120 min)--> [Caducada]
    +--(inactividad >= 120 min)----------------------------------------------------------> [Caducada]

[Caducada] --(POST con operación)--> no se ejecuta; se pide login; valores conservados con old() cuando es posible
[Caducada] --(usuario inicia sesión)--> [Activa]
```

- La ventana de aviso se calcula en el cliente a partir de `data-sesion-expira` (recalculada en cada petición).
- La extensión no altera datos de negocio: solo renueva la sesión conservando su contenido.
- **Sin JavaScript**: no hay estado [Aviso]; se mantiene la caducidad a las 2 horas (degradación documentada en [contracts/session.md](contracts/session.md)).

## Estados de un archivo de portada (FR-017, FR-018)

| Fase | Estado | Resultado |
|---|---|---|
| Cliente, sin elegir archivo | vacío | sin validación; el campo es opcional como hoy |
| Cliente, archivo válido | pre-validado | vista previa + nombre y tamaño; envío permitido |
| Cliente, fuera de límites | rechazado | input limpiado, mensaje en español junto al campo, envío bloqueado, formulario intacto |
| Servidor, válido | almacenado | ruta guardada en BD (regla de `005` sin cambios) + toast de éxito |
| Servidor, fuera de límites o inválido | rechazado | error en español junto al campo con `old()` conservado, resto del formulario operable |
| Portada ausente o fallo de carga | predeterminada | `images/libro-sin-portada.svg` (regla de `002`/`005`) |

Límites idénticos en ambos lados: **JPG, PNG o WebP hasta 2 MB**.

## Estados de un listado (FR-010, FR-013)

| Estado | Señal | Acción visible |
|---|---|---|
| Con datos | filas/tarjetas | acción principal de cabecera + paginación (10 por página, heredada) |
| Vacío | mensaje «No hay …» | acción principal junto al mensaje (`partials/estado-vacio`) |
| Vacío con criterio (búsqueda) | mensaje propio de `006` | «Limpiar» (heredado) |
| Cargando (solo región de búsqueda) | «Cargando…» anunciado | la región conserva `aria-busy` |

## Estados del mensaje de éxito (FR-022)

```text
[Operación correcta + session('success')] --> [Toast visible, role="status"]
    +--(con JS: 6 s o "Cerrar")--> [Oculto]        (el resultado sigue visible en la página)
    +--(sin JS)--------------------> [Persiste hasta navegar]
[Error de validación/operación] --> [Mensaje persistente, role="alert"] (no se auto-oculta)
```

## Inventario de formularios (sin cambios de campos ni validación)

| Formulario | Ruta | Validación servidor (intacta) | Añadido en 009 |
|---|---|---|---|
| Login | `POST /login` | `LoginRequest` (008) | — |
| Libro (crear/editar) | `POST/PUT /libros` | `LibroRequest` (003) | pre-validación de portada en cliente (D-054) |
| Categoría (crear/editar) | `POST/PUT /categorias` | `CategoriaRequest` (003) | — |
| Préstamo (registro) | `POST /prestamos` | `PrestamoRequest` (004) | **acceso visible** desde listado y home |
| Devolución | `POST /prestamos/{id}/devolver` | reglas de `PrestamoService` (004) | acción visible en fila |
| Portada (borrar) | `DELETE /libros/{id}/portada` | confirmación previa (005) | confirmación conservada |
| Extensión de sesión | `POST /sesion/extender` | `auth` + `@csrf` (nuevo, D-053) | — |

## Inventario de pantallas (FR-010)

| # | Pantalla | Ruta | Acción principal visible |
|---|---|---|---|
| 1 | Home | `GET /` | «Registrar préstamo» + accesos a las 5 secciones |
| 2 | Catálogo | `GET /catalogo` | «Registrar préstamo» (+ búsqueda heredada) |
| 3 | Libros | `GET /libros` | «Añadir libro» |
| 4 | Categorías | `GET /categorias` | «Nueva categoría» |
| 5 | Préstamos | `GET /prestamos` | «Registrar préstamo» |
| 6 | Panel | `GET /panel` | «Ir al catálogo» (solo consulta; no es listado) |
| 7 | Login | `GET /login` | «Iniciar sesión» |
| 8 | Errores 401 / 404 / conexión | `errors/*` | «Volver al catálogo» / «Reintentar» / «Iniciar sesión» |

Detalle completo por pantalla: [contracts/screens.md](contracts/screens.md).

## Casos límite

- **Archivo fuera de límites** (cliente y servidor), vacío, corrupto o con extensión falsa → rechazo con mensaje; el formulario nunca se rompe ni pierde el resto de datos.
- **Sesión que caduca con formulario abierto** → la operación no se ejecuta a medias, se explica en español, `old()` conserva lo recuperable.
- **Aviso de sesión no respondido** → caducidad controlada; el diálogo no roba el foco de forma irreversible ni cierra el formulario.
- **JavaScript desactivado** → navegación completa (menús y colapsables nativos), formularios operables, toast persistente, sin pre-validación ni aviso de sesión.
- **Sin color** (escala de grises / altísimo contraste) → estados de préstamo, disponibilidad, errores y éxito comprensibles por texto/icono.
- **Zoom 200 % y 360 px** → sin scroll horizontal, sin recortes ni solapamientos.
- **Listas vacías** → mensaje + acción principal, nunca un área vacía.
- **Títulos largos, ISBN y carné** → sin desbordamiento; el texto se ajusta o trunca con título accesible.
- **Foco bajo cabecera fija** → el indicador de foco permanece visible (2.4.11).
- **Caracteres del español** (á, é, í, ó, ú, ñ, ¿, ¡) → correctos en formularios, listados y mensajes.
- **Movimiento reducido** (`prefers-reduced-motion`) → sin animaciones esenciales; la información no depende de ellas.
