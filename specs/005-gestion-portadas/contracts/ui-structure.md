# Contract: Estructura de interfaz de portadas (FR-001, FR-005, FR-008)

**Spec**: [../spec.md](../spec.md) FR-001, FR-005, FR-008, SC-004, SC-007 | **Research**: [../research.md](../research.md) D-021, D-023, D-024 | **Structure base**: [../../002-consulta-catalogo/contracts/ui-structure.md](../../002-consulta-catalogo/contracts/ui-structure.md)

Todas las piezas extienden el layout común `resources/views/layouts/app.blade.php` (Principio V). CSS propio; sin frameworks.

## 1. Formulario de edición del libro (`libros/edit.blade.php`, ampliación de `003`)

Único lugar con controles de gestión de portada (clarificación de sesión):

| Elemento | Contrato |
|---|---|
| Etiqueta del campo | «Portada» asociada al input (`<label for>`, WCAG de `008`) |
| Input de archivo | `type="file" name="portada" accept="image/jpeg,image/png,image/webp"` — ayuda al usuario; **la validación real es del servidor** (D-019) |
| Leyenda bajo el input | «JPG, PNG o WebP, hasta 2 MB. Al guardar un archivo nuevo se reemplaza la portada actual.» |
| Vista previa actual | Si el libro tiene portada: la imagen servida por `route('portadas.show', $libro)` con `alt` = título; si no: la imagen predeterminada con `alt` «Sin portada» |
| Error junto al campo | `<span>`/`<p>` de error del campo `portada`, inmediatamente bajo el input, en español (§2 de [user-messages.md](user-messages.md)) |
| Botón «Eliminar portada» | Solo se muestra si el libro tiene portada; es un formulario anidado/propio con `@csrf`, `@method('DELETE')` hacia `libros.portadas.destroy` y `onsubmit="return confirm('…')"` con el texto de [user-messages.md](user-messages.md) §3 |
| Guardar cambios | Botón del formulario de edición (de `003`); el envío con archivo nuevo displace T1/T2 |

- Guardar el formulario **sin** elegir archivo conserva la portada (FR-001): el botón «Eliminar portada» es el único camino de borrado.
- Tras un rechazo, los campos de texto conservan `old()` y el error aparece junto a `portada`.

## 2. Vistas sin controles (límite de alcance)

| Vista | Portada mostrada | Controles de portada |
|---|---|---|
| Listado `catalogo/index` | `partials/portada` (ruta autenticada o predeterminada) | **Ninguno** |
| Detalle `catalogo/show` | `partials/portada` | **Ninguno** (excluido por clarificación) |
| Vistas de gestión de categorías/préstamos | — | Ninguno |

## 3. Parcial `partials/portada.blade.php` (ampliación del D-014 de `002`)

| Condición | `src` | `alt` |
|---|---|---|
| `portada` con ruta | `route('portadas.show', $libro)` | **Título del libro** (FR-008, SC-007) |
| `portada = NULL` | `asset('images/libro-sin-portada.svg')` | «Sin portada» (texto genérico, §4) |
| Cualquiera, con archivo caído o sin sesión (401) | `onerror` → sustituye por `asset('images/libro-sin-portada.svg')` | El de la predeterminada tras el fallback |

- Una sola definición para listado y detalle: no hay marcado de imagen duplicado.

## 4. Vista de error 401 (`errors/401.blade.php`, nueva)

- Extiende el layout común; muestra el mensaje de [user-messages.md](user-messages.md) §5 y, si existe la ruta `login` (entregada por `008`), un enlace «Iniciar sesión» hacia ella.
- No revela si la portada existía ni ningún detalle del archivo.

## 5. Accesibilidad y textos

- Toda `<img>` de portada (incluida la predeterminada) tiene `alt` no vacío (SC-007).
- Los botones de guardar y eliminar son operables con teclado y tienen texto visible (WCAG 2.1 AA, heredado de `008`).
- Contrato textual completo: [user-messages.md](user-messages.md).
