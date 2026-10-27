# Feature Specification: Gestión de portadas de libros

**Feature Branch**: `feature/portadas`

**Created**: 2026-10-26

**Status**: Draft

**Input**: User description: "# Especificación funcional: Gestión de portadas. Como usuario encargado, quiero agregar, reemplazar y eliminar portadas de libros para identificarlos visualmente."

**Phase**: 2 (la imagen predeterminada en el listado corresponde a la Fase 1)

## Clarifications

### Session 2026-10-26

- Q: Cuando un usuario edita otros datos de un libro (por ejemplo, corregir el año de publicación) sin seleccionar un archivo de imagen nuevo, ¿qué debe ocurrir con la portada que el libro ya tiene? → A: La portada existente se conserva; solo la elección de un archivo nuevo la reemplaza.
- Q: ¿Dónde deben ubicarse los controles para reemplazar y eliminar la portada? → A: Únicamente en el formulario de edición del libro (el reemplazo mediante la elección de un archivo nuevo y la eliminación mediante un botón con confirmación en ese mismo formulario); la vista de detalle no recibe controles de gestión.
- Q: ¿Deben ser las imágenes de portada accesibles solo con sesión iniciada o alcanzables por cualquiera con la URL directa del archivo? → A: Solo usuarios con sesión iniciada pueden ver las imágenes de portada; acceder a la URL de una portada sin sesión no debe servir la imagen.
- Q: Si dos usuarios reemplazan la portada del mismo libro casi al mismo tiempo, ¿qué debe hacer el sistema? → A: Predomina la última escritura; el archivo desplazado se elimina siempre para no dejar huérfanos.
- Q: ¿Deben llevar las imágenes de portada mostradas en listado y detalle un texto alternativo para lectores de pantalla? → A: Sí: el texto alternativo de una portada es el título del libro y el de la imagen predeterminada es un texto genérico.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Agregar o reemplazar una portada (Priority: P1)

Como usuario encargado, quiero agregar o reemplazar la portada de un libro para identificarlo visualmente en el listado y en el detalle.

**Why this priority**: La gestión de imágenes es parte de la entrega completa de la Fase 2 y afecta a la presentación principal del catálogo.

**Independent Test**: Agregar una imagen válida a un libro sin portada y sustituirla por otra válida desde el formulario de edición, verificando ambas presentaciones (listado y detalle) y la eliminación del archivo anterior.

**Acceptance Scenarios**:

1. **Given** un libro sin portada, **When** se guarda una imagen JPG, PNG o WebP de hasta 2 MB, **Then** la portada queda asociada al libro y se muestra en el listado y en el detalle.
2. **Given** un libro con portada existente, **When** la portada se reemplaza por una imagen válida, **Then** se muestra la nueva imagen en ambas vistas y el archivo anterior deja de existir.
3. **Given** un libro con portada existente, **When** se intenta guardar un archivo de tipo no permitido o mayor a 2 MB, **Then** la operación se rechaza con un mensaje de error en español junto al campo y la portada existente se conserva sin cambios.

---

### User Story 2 - Eliminar una portada (Priority: P2)

Como usuario encargado, quiero retirar la portada de un libro para que el libro siga mostrándose con la imagen predeterminada.

**Why this priority**: Completa el ciclo de vida de las imágenes; sin ella, un error de imagen no podría corregirse eliminándola.

**Independent Test**: Eliminar una portada existente mediante el botón de eliminación con confirmación del formulario de edición y comprobar que el archivo desaparece y que el libro vuelve a mostrar la imagen predeterminada en listado y detalle.

**Acceptance Scenarios**:

1. **Given** un libro con portada existente, **When** se elimina la portada mediante el botón de eliminación con confirmación del formulario de edición, **Then** el archivo se elimina, la referencia se retira del libro y ambas vistas muestran la imagen predeterminada.

---

### Edge Cases

- Archivo de exactamente 2 MB: se acepta si el formato es válido (el límite es inclusivo).
- Formatos distintos de JPG, PNG o WebP (por ejemplo, GIF, SVG o TIFF): se rechazan con error junto al campo.
- Archivo con extensión válida pero contenido que no corresponde a una imagen: se rechaza con error junto al campo.
- Fallo del almacenamiento al guardar, reemplazar o eliminar el archivo: el usuario ve un mensaje en español junto al campo ("No se pudo guardar la imagen. Inténtelo de nuevo.") y la portada existente se conserva sin cambios.
- Eliminación del libro con portada asociada: al eliminar un libro, su archivo de portada se elimina también automáticamente; cada libro tiene su propia portada y no existen portadas compartidas entre libros.
- Puerto o red interrumpidos durante la subida: el libro conserva su estado previo y el usuario recibe un mensaje de error claro.
- Edición de otros datos del libro sin seleccionar un archivo de imagen nuevo: la portada existente se conserva sin cambios; solo una elección explícita de archivo nuevo reemplaza la portada.
- Acceso sin sesión a la URL directa de una portada: la imagen no se sirve y se solicita el inicio de sesión.
- Reemplazos concurrentes de la misma portada: predomina la última escritura; el archivo desplazado se elimina siempre, por lo que no queda ninguno huérfano y cada libro sigue teniendo como máximo una portada.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El usuario debe poder asociar, reemplazar y eliminar una portada de libro desde el formulario de edición del libro (el reemplazo al elegir un archivo nuevo y la eliminación mediante un botón con confirmación en ese mismo formulario); la vista de detalle no ofrece controles de gestión de portada. Guardar la edición de otros datos del libro sin seleccionar un archivo nuevo no modifica ni elimina la portada existente.
- **FR-002**: El servidor debe aceptar únicamente imágenes JPG, PNG o WebP de hasta 2 MB (límite inclusivo) y rechazar cualquier otra con un mensaje de error en español mostrado junto al campo, conservando los valores ingresados.
- **FR-003**: El sistema debe almacenar las imágenes en el almacenamiento definido por la constitución (Laravel Storage) y registrar la ruta del archivo asociada al libro en la base de datos.
- **FR-004**: Al reemplazar o eliminar una portada, el sistema debe eliminar correctamente el archivo anterior; al eliminar un libro, debe eliminar también automáticamente el archivo de su portada; cuando un libro no tenga portada propia, el listado y el detalle deben mostrar la imagen predeterminada.
- **FR-005**: La eliminación de una portada debe ser una operación explícita de eliminación (método `DELETE`, nunca mediante enlaces GET) y debe solicitar confirmación al usuario antes de ejecutarse.
- **FR-006**: Un intento de subida fallido (almacenamiento no disponible o error de escritura) no debe alterar la portada existente ni el registro del libro, y debe mostrar al usuario un mensaje en español junto al campo ("No se pudo guardar la imagen. Inténtelo de nuevo.").
- **FR-007**: Las imágenes de portada solo deben ser accesibles para usuarios con sesión iniciada; sin sesión, la URL directa de una portada no debe servir la imagen y debe solicitarse el inicio de sesión.
- **FR-008**: Toda portada mostrada en el listado y en el detalle debe incluir un texto alternativo cuyo valor sea el título del libro, y la imagen predeterminada debe incluir un texto alternativo genérico.

### Key Entities

- **Libro**: registro del catálogo al que pertenece la portada; conserva la referencia (ruta) a la imagen de portada cuando existe.
- **Portada**: imagen asociada a un libro (JPG, PNG o WebP hasta 2 MB); cada libro tiene como máximo una portada propia. Cuando el libro no tiene portada, se muestra una imagen predeterminada fija del sistema.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: En las pruebas realizadas, los tres formatos permitidos (JPG, PNG, WebP) dentro del límite de 2 MB se aceptan en el 100 % de los intentos, y los formatos no permitidos o los archivos mayores a 2 MB se rechazan en el 100 % de los intentos con un mensaje visible junto al campo.
- **SC-002**: En el 100 % de los reemplazos, eliminaciones de portada y eliminaciones de libro probados, el archivo correspondiente deja de existir y el libro (o el catálogo, si el libro fue eliminado) muestra la portada correspondiente o, en su defecto, la imagen predeterminada en listado y detalle.
- **SC-003**: Tras una operación rechazada (tipo no permitido, tamaño excesivo o fallo de almacenamiento), el 100 % de los libros conserva intacta su portada previa y el usuario ve un mensaje de error en español junto al campo.
- **SC-004**: Ninguna eliminación de portada ocurre sin confirmación previa del usuario ni mediante un enlace o lectura automática.
- **SC-005**: Tras eliminar un libro con portada y tras reemplazos concurrentes de la misma portada, no queda ningún archivo de portada huérfano en el almacenamiento.
- **SC-006**: El 100 % de las solicitudes de una URL de portada sin sesión iniciada no sirven la imagen y redirigen o solicitan el inicio de sesión.
- **SC-007**: El 100 % de las imágenes de portada mostradas en listado y detalle tienen texto alternativo con el título del libro, y el 100 % de las imágenes predeterminadas tienen un texto alternativo genérico.

## Assumptions

- Existe al menos un libro en el sistema al que asociar la imagen y el almacenamiento local está disponible.
- La constitución (v1.0) no define dimensiones, relación de aspecto ni tamaño mínimo de imagen; por tanto, cualquier imagen que cumpla formato y tamaño se acepta sin recorte ni redimensión.
- Cada libro tiene como máximo una portada propia; no existen portadas compartidas entre libros.
- El límite de 2 MB es inclusivo (un archivo de exactamente 2 MB con formato válido se acepta).
- La imagen predeterminada ya fue implementada en la Fase 1 y se reutiliza en esta funcionalidad.
- El alcance se limita a la gestión de portadas (asociar, reemplazar, eliminar); la edición de otros datos del libro pertenece al CRUD de libros.
