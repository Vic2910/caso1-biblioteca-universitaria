# Contract: Estructura de interfaz transversal (FR-001, FR-002, FR-004, FR-011, FR-012)

**Spec**: [../spec.md](../spec.md) FR-001, FR-002, FR-004, FR-008, FR-011, FR-012, SC-001, SC-002, SC-007, SC-008 | **Research**: [../research.md](../research.md) D-036, D-041, D-044, D-045, D-046 | **Data model**: [../data-model.md](../data-model.md)

Contrato de composición compartido por **todas** las vistas del sistema (catálogo, CRUD de libros y categorías, préstamos, portadas, búsqueda, panel y login). Textos visibles: [user-messages.md](user-messages.md).

## 1. Layout común (FR-001, SC-001)

Todas las vistas extienden `resources/views/layouts/app.blade.php` (creado en `001`, ampliado por `008`):

| Región | Contenido obligatorio |
|---|---|
| **Cabecera** | Nombre de la aplicación (Biblioteca Universitaria) |
| **Navegación** | Enlaces de la aplicación con la página activa distinguible; desde `008` incluye el **bloque de sesión** (§2) |
| **Zona de contenido** | `@yield` con el título y el cuerpo de la vista |
| **Pie** | Pie común idéntico en todas las vistas |

- Cabecera, navegación y pie son **idénticos** en todas las vistas (verificable en `AccesibilidadUiTest`).
- Estilos **propios** del proyecto en `public/css/`; si se usara alguna librería (Bootstrap/Tailwind) debe declararse en el README (FR-001). El diseño de partida no usa ninguna (D-044).

## 2. Bloque de sesión (FR-009, FR-010)

| Elemento | Regla |
|---|---|
| Sin sesión | Solo es visible `GET /login` (formulario de inicio de sesión) |
| Con sesión | En la navegación: saludo con el nombre del usuario + botón/enlace **«Cerrar sesión»** (`POST /logout` con `@csrf`) |
| Fallo de login | El error se muestra **dentro del formulario de login**, junto al campo; el correo se conserva (`old()`) |

## 3. Estructura de campo de formulario (FR-003, FR-004, FR-011, SC-002)

Orden obligatorio para **cada** campo visible, en todos los formularios del sistema (§Inventario de [../data-model.md](../data-model.md)):

| # | Elemento | Regla |
|---|---|---|
| 1 | Etiqueta | `<label for>` visible y asociada al campo, en español |
| 2 | Campo | `<input>`/`<select>`/`<textarea>` con `name` e `id` coincidentes; valor conservado con `old()` |
| 3 | Error | Texto en español **inmediatamente después del campo** cuando la validación del servidor lo rechaza; el campo lleva `aria-invalid="true"` y `aria-describedby` apuntando al error |

- Tras un rechazo se muestran **todos** los campos inválidos con su error; los válidos conservan sus valores.
- Ninguna operación depende de validación en el cliente; el servidor es la única fuente de verdad (Principio II).

### Confirmación de eliminación (FR-007)

- Botón/enlace «Eliminar» → `<form>` con `@csrf`, `@method('DELETE')` y **confirmación previa** del usuario (diálogo `confirm()` en español) antes del envío.
- Nunca existe un enlace que dispare una eliminación por `GET` (contrato [http-endpoints.md](http-endpoints.md) §4).

## 4. Estado vacío de listados (FR-012, SC-008)

Parcial compartido `partials/estado-vacio.blade.php`:

| Elemento | Regla |
|---|---|
| Mensaje | En español, tipo **«No hay registros»** (o el mensaje propio del listado, p. ej. «No hay libros registrados aún») |
| Acción | Enlace/botón a la acción disponible para crear el primer registro (p. ej. «Añadir libro», «Nueva categoría», «Registrar préstamo») |
| Prohibido | Un área vacía sin explicación |

- Aplicable a los listados de libros, categorías y préstamos; el catálogo de `002` conserva su mensaje propio (ya indica que no hay datos) y añade la acción cuando aplique; el panel de `007` conserva «No hay datos disponibles».

## 5. Accesibilidad — WCAG 2.1 nivel AA (FR-011, SC-007)

| Criterio | Regla verificable |
|---|---|
| Etiquetas | El 100 % de los campos visibles tiene `<label for>` asociado |
| Teclado | Navegación, envío de formularios y confirmación de eliminación operables con teclado; orden de tabulación lógico; foco visible |
| Contraste | Texto normal ≥ **4,5:1** sobre su fondo en toda la paleta (verificación con herramienta de contraste, documentada en [../quickstart.md](../quickstart.md)) |
| Errores | `aria-invalid` + `aria-describedby`; mensaje asociado al campo |
| Documento | `lang="es"`, títulos jerárquicos (`h1` único por vista), alternativa de texto (`alt`) en imágenes |

## 6. Diseño adaptable (FR-002, FR-008, SC-001)

| Dispositivo | Tamaño de verificación | Reglas |
|---|---|---|
| Teléfono | **360 × 640 px** | Una columna; formularios apilados; tablas con desplazamiento propio o apiladas; sin recortes ni desbordamiento horizontal |
| Tableta (vertical) | **768 × 1024 px** | Rejilla intermedia; navegación legible sin solapamientos |
| Escritorio | **≥ 1024 px** | Diseño de referencia de `001`–`007` sin cambios |

- Implementado con media queries en el CSS propio (breakpoints ≤ 640 / 641–1023 / ≥ 1024 px, D-044); aplica a las vistas de la Fase 2 según FR-008.
- Ningún contenido se pierde, recorta ni se superpone en los tres tamaños (SC-001).

## 7. Escapado de salida (FR-005)

- Todo texto de usuario (títulos, nombres, carnés, búsquedas, mensajes) se renderiza con `{{ }}`: se muestra **como texto** y no se ejecuta como código.
- `{!! !!}` prohibido salvo salidas generadas por Laravel (p. ej. paginador), verificado por `EscapadoSalidaTest` (D-042).
- Los caracteres especiales del español (á, é, í, ó, ú, ñ, ¿, ¡) se muestran correctamente en formularios y listados (UTF-8 heredado de `001`).
