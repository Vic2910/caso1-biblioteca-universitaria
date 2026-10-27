# Contract: Endpoint HTTP del panel (FR-001, FR-002, FR-003, FR-006)

**Spec**: [../spec.md](../spec.md) FR-001…FR-003, FR-006, FR-007, SC-001 | **Research**: [../research.md](../research.md) D-018, D-020, D-022, D-023 | **Data model**: [../data-model.md](../data-model.md)

Aplicación web Blade servida con `php artisan serve`. La ruta responde **HTML** (navegador). Mensajes visibles: [user-messages.md](user-messages.md); composición de la respuesta: [ui-structure.md](ui-structure.md).

## Rutas

| # | Método y ruta | Nombre | Acción | Éxito |
|---|---|---|---|---|
| 1 | `GET /panel` | `panel.index` | `PanelController@index` | `200 OK` con la vista del panel (tres indicadores) |

- Sin parámetros de consulta y sin parámetros de ruta: la fecha de consulta la determina el servidor (D-022).
- El enlace «Panel» de la navegación común apunta a `panel.index` (D-018/D-025).
- **No hay autenticación propia en esta funcionalidad**: al ser la única vista pública del flujo actual se comporta como `/catalogo`; cuando `008-interfaz-validacion-seguridad` entregue el inicio de sesión global, el panel queda protegido como el resto, sin permisos diferenciados (clarificación 2026-10-26: los tres perfiles pueden consultarlo).

## Parámetros

Ninguno. `GET /panel` no acepta entradas; no existen filtros, búsquedas ni fechas por parte del usuario (FR-006: «poder consultarse sin realizar búsquedas ni filtros previos»).

## Respuesta `200`

- Contenido: la vista `panel/index.blade.php` con las tres secciones y el orden definidos en [ui-structure.md](ui-structure.md) §2.
- Datos calculados en el servidor con los registros vigentes al momento de la petición:
  - Ranking: top 5 de libros con ≥ 1 préstamo, con su cifra (FR-004, [data-model.md](../data-model.md) §Indicador 1).
  - Vencidos: préstamos con `fecha_devolucion_prevista` **<** fecha de consulta y sin `fecha_devolucion_real`, ordenados por días de retraso de mayor a menor (FR-005, §Indicador 2).
  - Disponibilidad: `SUM(ejemplares_disponibles)` por categoría con ≥ 1 libro, en orden alfabético español (FR-003, §Indicador 3).
- Un indicador sin filas muestra «No hay datos disponibles» en su sección; los demás se renderizan con normalidad (FR-008, [user-messages.md](user-messages.md) §1).
- Cabecera, navegación y pie: layout común idéntico al del catálogo (FR-007, D-025).

## Métodos no soportados

| Método | Resultado |
|---|---|
| `POST/PUT/PATCH/DELETE /panel` | `405 Method Not Allowed` (default de Laravel; no se registran rutas de escritura) |

## Restricciones del contrato

- **Solo lectura**: no se aceptan cuerpos de petición, ni métodos distintos de `GET`, ni operaciones de escritura (FR-006; sin formularios, sin CSRF aplicable).
- Consultas exclusivamente vía Eloquent con agregados (`withCount`/`withSum`) y eager loading `with('libro')`; sin SQL concatenado con entradas del usuario —no las hay— (Principio IV, D-023).
- La fecha de comparación es la del servidor en el momento de la petición; no se expone ni se acepta como entrada (D-022).
- El panel **resume** los datos de libros y préstamos de las funcionalidades previas sin sustituirlos: no expone operaciones de catálogo, CRUD ni devoluciones (fuera de alcance: `003`, `004`).
