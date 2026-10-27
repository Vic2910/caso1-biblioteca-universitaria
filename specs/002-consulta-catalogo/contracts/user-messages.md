# Contract: Mensajes visibles al usuario (FR-001, FR-003, FR-004)

**Spec**: [../spec.md](../spec.md) FR-001, FR-003, FR-004, edge cases, SC-003, SC-004 | **Research**: [../research.md](../research.md) D-013, D-014 | **Structure**: [ui-structure.md](ui-structure.md)

Todos los mensajes son **en español**, visibles para estudiantes, docentes y personal administrativo.

## 1. Catálogo vacío (FR-001, edge cases)

| Elemento | Contrato |
|---|---|
| **Disparador** | `GET /catalogo` con total de libros = 0 (página 1) |
| **Texto requerido** | «No hay libros registrados aún» |
| **Presentación** | En la zona del listado, sin tarjetas y sin controles de paginación |

## 2. Errores 404 (Historias 1 y 2, edge cases)

| Caso | Texto requerido | Elementos adicionales |
|---|---|---|
| Libro inexistente (`/catalogo/{id}` sin resultado) | «Libro no encontrado» | Enlace «Volver al catálogo» hacia `catalogo.index` |
| Página fuera de rango o no numérica (`?page=…`) | «Página no válida» | Enlace «Volver al catálogo» hacia `catalogo.index` |

- La vista de error usa el layout común; el mensaje se muestra escapado (variable `$exception` de la vista de errores de Laravel) con texto por defecto «Página no encontrada» si el mensaje estuviera vacío (D-013).

## 3. Textos de imagen y accesibilidad (FR-003, SC-003)

| Elemento | Texto |
|---|---|
| `alt` de portada predeterminada | «Sin portada» |
| `alt` de portada registrada | «Portada de {título}» |
| Enlace de tarjeta del listado | Destino `catalogo.show`; el texto accesible de la tarjeta incluye al menos título y autor |
| Enlace de vuelta en la ficha | «Volver al catálogo» |
| Enlace de navegación | «Catálogo» |

## 4. Títulos de sección

| Vista | Título |
|---|---|
| Listado | «Catálogo de libros» |
| Detalle | «Ficha del libro» |

## 5. Restricciones del contrato

- Ningún mensaje expone trazas SQL, credenciales, rutas internas ni detalles técnicos (Principio IV; coherente con el contrato de `001`: [../../001-entorno-datos/contracts/user-messages.md](../../001-entorno-datos/contracts/user-messages.md)).
- Textos fijos en el marcado (no dependen de la base de datos) salvo título/autor/categoría, que son datos.
