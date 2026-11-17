# Contract: Estructura de las pantallas (FR-010, FR-011, FR-012, FR-013, FR-014)

**Spec**: [../spec.md](../spec.md) | **Research**: [../research.md](../research.md) D-055, D-056 | **Data model**: [../data-model.md](../data-model.md) §Inventario de pantallas

Todas las pantallas comparten el layout y el sistema de [ui-structure.md](ui-structure.md). Cada una define: **acción principal visible**, jerarquía, estados y casos límite. Ninguna pantalla cambia rutas ni controladores salvo lo indicado.

## 1. Home — `GET /` (público)

| Elemento | Regla |
|---|---|
| Propósito | Accesos reales al sistema en lugar del aviso de conexión (FR-012) |
| Acción primaria | **«Registrar préstamo»** (con sesión) / **«Iniciar sesión»** (sin sesión) |
| Accesos | Rejilla de tarjetas-enlace: Catálogo, Libros, Categorías, Préstamos, Panel (≥ 44 px, foco visible) |
| Estadísticas | Cifras ya disponibles (`$totalLibros`, `$totalCategorias`, `$totalPrestamos`) presentadas como indicadores con etiqueta textual |
| Sin sesión | El home se ve completo; cada acceso conduce a `GET /login` sin ejecutar operación alguna (clarificación 2026-11-17) |
| Bloques colapsables | Secciones de ayuda/inicio agrupadas con `<details>` (patrón de [ui-structure.md](ui-structure.md) §3) |

## 2. Catálogo — `GET /catalogo`

- **Acción principal visible: «Registrar préstamo»** (cabecera de la zona de contenido); además, el bloque de búsqueda/filtro heredado de `006` (campo + criterios + «Limpiar»).
- Tarjetas de libro (portada o predeterminada, título, autor, año, categoría), enlace completo a la ficha; paginación de 10 con sus textos.
- Estados: con datos / vacío («No hay libros registrados aún» + acción) / sin coincidencias (mensaje de `006` + «Limpiar») / cargando (región de búsqueda, [ui-structure.md](ui-structure.md) §6).
- Estados de disponibilidad comunicados **con texto**, no solo color.

## 3. Libros — `GET /libros`

- **Acción principal: «Añadir libro»** → `GET /libros/crear`.
- Tabla/tarjetas con título, autor, ISBN, categoría, disponibilidad (texto + icono), portada; acciones por fila: «Editar», «Ver portada», «Eliminar» (con confirmación y `DELETE`).
- Estados: con datos / vacío («No hay libros registrados aún» + «Añadir libro»).
- Formulario crear/editar: conserva errores junto al campo y añade la pre-validación de portada ([upload-portada.md](upload-portada.md)).

## 4. Categorías — `GET /categorias`

- **Acción principal: «Nueva categoría»** → `GET /categorias/crear`.
- Listado con nombre, número de libros y acciones «Editar»/«Eliminar» (confirmación + `DELETE`).
- Estados: con datos / vacío («No hay categorías registradas aún» + «Nueva categoría»).
- Error de unicidad mostrado junto al campo (regla de `003`, intacta).

## 5. Préstamos — `GET /prestamos`

- **Acción principal: «Registrar préstamo»** → `GET /prestamos/crear` (hoy inalcanzable desde la interfaz; requisito central de FR-011).
- Tabla con libro (título + ISBN), estudiante, carné, tres fechas (devolución real «—»), **estado como texto + icono + color** (activo/devuelto/vencido) y «Registrar devolución» en filas activas o vencidas.
- Paginación de 10, orden heredado; estados: con datos / vacío («No hay préstamos registrados aún» + «Registrar préstamo»).

## 6. Panel — `GET /panel` (solo consulta)

- Jerarquía: libros más prestados, préstamos vencidos, ejemplares disponibles por categoría; cada bloque con título y unidad de medida textual (no depender del color ni del tamaño de barra solo).
- **Acción principal: «Ir al catálogo»** (no es listado; FR-011 no aplica, pero la pantalla no queda sin acción).
- Estado vacío heredado: «No hay datos disponibles» + acción.

## 7. Login — `GET /login`

- **Acción: «Iniciar sesión»**; error genérico en español dentro del formulario junto al campo; correo conservado con `old()`.
- Sin bloqueos ni esperas ante fallos repetidos (contrato de `008`, intacto).
- Accesible con teclado de principio a fin; autocompletado nativo (`username`, `current-password`).

## 8. Vistas de error — 401, 404, conexión

| Vista | Mensaje (español) | Acción principal |
|---|---|---|
| 401 | Sesión requerida / caducada | «Iniciar sesión» |
| 404 | «Página no encontrada» (texto heredado de `002`) | «Volver al catálogo» |
| Conexión | Mensaje heredado de `001` (sin exponer credenciales ni SQL) | «Reintentar» |

- Mismo layout, misma identidad visual y contraste AAA (FR-014). **Alcance cerrado**: el rediseño no añade 403 ni 500 genéricos (clarificación 2026-11-17).
