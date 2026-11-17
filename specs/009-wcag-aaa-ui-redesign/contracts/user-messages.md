# Contract: Textos visibles en español (FR-013, FR-014, FR-017, FR-022, FR-005)

**Spec**: [../spec.md](../spec.md) | **Research**: [../research.md](../research.md) D-060 | **Data model**: [../data-model.md](../data-model.md)

Inventario de textos. Los marcados **(heredado)** son textos ya acordados en especificaciones anteriores que **se conservan literalmente** (D-060); el resto es nuevo de `009`.

## 1. Estados vacíos (heredados, FR-013)

| Pantalla | Texto | Acción |
|---|---|---|
| Catálogo | «No hay libros registrados aún» | «Registrar préstamo» (nuevo) |
| Libros | «No hay libros registrados aún» | «Añadir libro» |
| Categorías | «No hay categorías registradas aún» | «Nueva categoría» |
| Préstamos | «No hay préstamos registrados aún» | «Registrar préstamo» |
| Panel | «No hay datos disponibles» | «Ir al catálogo» |
| Búsqueda sin coincidencias | «No se encontraron libros que coincidan con la búsqueda» | «Limpiar» (heredados de `006`) |

## 2. Acciones principales (nuevas, FR-011)

- «Registrar préstamo», «Añadir libro», «Nueva categoría», «Ir al catálogo», «Iniciar sesión», «Volver al catálogo», «Reintentar».

## 3. Sesión (FR-005)

- «Quedan 5 minutos antes de que finalice su sesión.» / «Quedan {n} minutos antes de que finalice su sesión.»
- «Extender sesión» · «Cerrar sesión»
- Caducidad (heredado de `008`): mensaje genérico de sesión caducada pidiendo iniciar de nuevo; el fallo de login sigue siendo el mensaje genérico en español sin revelar la existencia de la cuenta.

## 4. Éxito y carga (FR-022, FR-010)

- Éxito (heredados): «Préstamo registrado correctamente.», «Devolución registrada correctamente.», «Libro guardado correctamente.», «Categoría guardada correctamente.», «Portada actualizada correctamente.», «Sesión extendida.» (nuevo)
- Carga (nuevo): «Cargando…»
- Cierto del toast: botón «Cerrar mensaje» con etiqueta accesible.

## 5. Portadas (FR-017, FR-018)

- Rechazo de cliente/servidor (nuevo): **«El archivo debe ser una imagen JPG, PNG o WebP de hasta 2 MB.»**
- Aceptado (nuevo): «Archivo listo para enviar: {nombre} ({tamaño}).»
- «Sin portada» (heredado, `alt` de la imagen predeterminada).

## 6. Errores (FR-014)

- 401: «Debe iniciar sesión para acceder a esta sección.» + «Iniciar sesión»
- 404: «Página no encontrada» (heredado de `002`) + «Volver al catálogo»
- Conexión (heredado de `001`): mensaje sin exponer usuario, contraseña, host ni SQL + «Reintentar»

## 7. Navegación y accesibilidad

- «Saltar al contenido principal» (nuevo, 2.4.1)
- «Menú principal» / «Cerrar menú» (etiquetas accesibles de la hamburguesa)
- «Navegación principal» (`aria-label` heredado)
- Agrupación «Gestión» para el menú desplegable (Libros, Categorías, Préstamos)

> Regla transversal: todo texto visible se emite con `{{ }}`; los caracteres del español (á, é, í, ó, ú, ñ, ¿, ¡) se muestran correctamente.
