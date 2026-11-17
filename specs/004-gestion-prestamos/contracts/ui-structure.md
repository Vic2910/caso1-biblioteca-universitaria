# Contract: Estructura de interfaz de préstamos (FR-001, FR-006, SC-004)

**Spec**: [../spec.md](../spec.md) FR-001, FR-006, SC-003, SC-004 | **Research**: [../research.md](../research.md) D-018, D-024, D-027 | **Data model**: [../data-model.md](../data-model.md)

Contrato de composición visual compartido por el listado, el formulario de registro y el formulario de devolución. Textos visibles: [user-messages.md](user-messages.md).

## 1. Layout común (FR-006, SC-004)

Las tres vistas extienden `resources/views/layouts/app.blade.php` (creado en `001`, ampliado en `002`):

| Región | Contenido obligatorio |
|---|---|
| **Cabecera** | Nombre de la aplicación (Biblioteca Universitaria) |
| **Navegación** | Enlaces de la aplicación, incluido «Catálogo» y el nuevo «Préstamos» → `route('prestamos.index')`; la página activa se distingue visualmente |
| **Zona de contenido** | `@yield` con el título y el cuerpo de la vista |
| **Pie** | Pie común idéntico en todas las vistas |

Cabecera, navegación y pie son **idénticos** entre las tres vistas de préstamos y el catálogo (verificable en `PrestamoListadoTest`).

## 2. Listado (`/prestamos`)

Zona de contenido, en orden:

1. **Título** de la sección («Préstamos registrados»).
2. **Estado vacío** (solo si no hay préstamos): «No hay préstamos registrados aún» en la zona del listado, sin tabla ni controles de paginación.
3. **Tabla de préstados** — columnas, en este orden:

   | # | Columna | Contenido |
   |---|---|---|
   | 1 | Libro | `titulo` + `isbn` del préstamo (relación `libro`) |
   | 2 | Estudiante | `nombre_estudiante` |
   | 3 | Carné | `carnet_estudiante` |
   | 4 | Fecha de préstamo | `fecha_prestamo` |
   | 5 | Devolución prevista | `fecha_devolucion_prevista` |
   | 6 | Devolución real | `fecha_devolucion_real` o «—» si es `NULL` |
   | 7 | Estado | Badge con el estado actual (`activo`/`devuelto`/`vencido`) tras `sincronizarVencidos()` (D-020) |
   | 8 | Acción | Botón/enlace «Registrar devolución» → `prestamos.form-devolucion` **solo** en filas `activo` o `vencido`; vacío en `devuelto` |

   - Orden determinista: `fecha_prestamo` DESC → `id` DESC (D-024).
4. **Controles de paginación** (solo si hay préstamos): 10 por página, vista propia `pagination/prestamos.blade.php` («Anterior», «Siguiente», números) con CSS propio.

## 3. Formulario de registro (`/prestamos/crear`)

Zona de contenido, en orden:

1. **Título** («Registrar préstamo»).
2. **Formulario** `POST` → `prestamos.store` con `@csrf`, campos en este orden:

   | # | Etiqueta | Control | Detalle |
   |---|---|---|---|
   | 1 | Libro | `<select name="libro_id">` | Opción por libro ordenado por título: «{título} ({ISBN}) — disponibles: {n}»; se muestran también los agotados (D-027); opción por defecto «Seleccione un libro» |
   | 2 | Nombre del estudiante | `<input type="text" name="nombre_estudiante">` | 2–255 caracteres |
   | 3 | Carné | `<input type="text" name="carnet_estudiante">` | hasta 50 caracteres |
   | 4 | Fecha de préstamo | `<input type="date" name="fecha_prestamo">` | Valor por defecto: hoy |
   | 5 | Fecha de devolución prevista | `<input type="date" name="fecha_devolucion_prevista">` | Posterior a la fecha de préstamo |

   - Botón «Registrar préstamo»; enlace «Volver a préstamos».
3. **Errores**: junto a **cada** campo (texto en español, [user-messages.md](user-messages.md) §2); los campos conservan lo ingresado con `old()`, incluido el `<select>` (se re-selecciona el libro enviado).

## 4. Formulario de devolución (`/prestamos/{id}/devolver`)

Zona de contenido, en orden:

1. **Título** («Registrar devolución»).
2. **Resumen del préstamo** (solo lectura): libro (título + ISBN), estudiante, carné, fecha de préstamo, devolución prevista y estado actual.
3. **Campo** «Fecha de devolución real» (`<input type="date" name="fecha_devolucion_real">`), obligatoria y no anterior a la fecha de préstamo.
4. Botón «Registrar devolución» (`POST` → `prestamos.devolver` con `@csrf`); enlace «Volver a préstamos».
5. Si el préstamo ya está `devuelto`, la vista muestra el mensaje «Este préstamo ya fue devuelto.» junto a la acción y **sin** botón de envío (el `POST` directo también es rechazado por el servidor).

## 5. Estados y feedback

| Elemento | Contrato |
|---|---|
| Badge `activo` | Color/estilo diferenciado, texto «Activo» |
| Badge `vencido` | Color de alerta, texto «Vencido» |
| Badge `devuelto` | Color neutro/aprobado, texto «Devuelto» |
| Mensaje flash de éxito | Tras registro o devolución correctos, en el listado (§2), junto al título |
| Consistencia | Una sola aplicación: cabecera, navegación y pie idénticos a `/catalogo` (SC-004) |

## 6. Imágenes

- Esta funcionalidad **no** muestra portadas (el listado y los formularios identifican el libro por título + ISBN); la gestión de imágenes queda en `005-gestion-portadas` (ver gate G8 del [plan.md](../plan.md)).
