# Quickstart: validación de 005-gestion-portadas

**Spec**: [spec.md](spec.md) | **Data model**: [data-model.md](data-model.md) | **Contracts**: [contracts/](contracts/)

Guía de ejecución y validación de extremo a extremo. No contiene implementación: los detalles pertenecen a `tasks.md`.

## Requisitos previos

- Entorno de `001-entorno-datos` funcionando (PHP ≥ 8.3, Composer 2.x, MySQL 8.x local) con migraciones y siembra.
- `002-consulta-catalogo` (parcial de portada y vistas de catálogo) y `003-gestion-libros` (formulario de edición de libros, donde viven los controles) implementados.
- `008-interfaz-validacion-seguridad` **opcional**: solo aporta el enlace «Iniciar sesión» de la vista 401; los escenarios de sesión se prueban con `actingAs()` en PHPUnit y con una sesión iniciada manualmente.

## Puesta en marcha

```bash
composer install
cp .env.example .env        # completar credenciales (contrato: 001 contracts/environment-variables.md)
php artisan key:generate
php artisan migrate --seed  # esquema + ≥15 libros (001)
php artisan test            # suite completa, incluidas las 5 suites de 005
php artisan serve           # → http://127.0.0.1:8000
```

No se requiere `php artisan storage:link`: las portadas se sirven por ruta controlada (D-018, D-023).

## Escenarios de validación

### V-001 — Asociar y reemplazar portada (FR-001, SC-001/SC-002)

1. Iniciar sesión y editar un libro sin portada → elegir un `.jpg` válido < 2 MB → guardar.
2. **Esperado**: mensaje «Portada actualizada correctamente.»; el listado y la ficha muestran la imagen nueva; en `storage/app/public/portadas/{id}/` hay exactamente un archivo; `libros.portada` guarda su ruta.
3. Editar de nuevo eligiendo un `.png` → guardar.
4. **Esperado**: se ve la nueva imagen en ambas vistas y **el archivo anterior ya no existe** (solo queda el nuevo).
   Referencia: [data-model.md](data-model.md) §Transiciones T1/T2.

### V-002 — Formatos y límite de 2 MB (FR-002, SC-001)

1. Con la edición abierta, probar sucesivamente: JPG, PNG y WebP de ~1,9 MB; un archivo de **exactamente 2 MB** en formato válido.
2. **Esperado**: los cuatro se aceptan (100 %).
3. Probar: un GIF, un SVG, un TIFF, un `.jpg` cuyo contenido no sea imagen, y un PNG de 3 MB.
4. **Esperado**: los cinco se rechazan con el mensaje en español **junto al campo**, conservando el resto de valores ingresados y la portada previa intacta.

### V-003 — Edición sin archivo conserva la portada (FR-001, edge case)

1. Con un libro que ya tiene portada, editar solo el año de publicación (sin tocar el campo de archivo) → guardar.
2. **Esperado**: el año cambia y la portada sigue siendo la misma (misma ruta en BD, mismo archivo en disco).

### V-004 — Eliminar portada con confirmación (FR-005, SC-004, Historia 2)

1. En la edición de un libro con portada, pulsar «Eliminar portada».
2. **Esperado**: aparece la confirmación; al cancelar, no cambia nada.
3. Confirmar.
4. **Esperado**: mensaje «Portada eliminada correctamente.»; el archivo desaparece del disco; listado y ficha muestran la imagen predeterminada con `alt` «Sin portada».
5. Intentar `GET /libros/{id}/portada` desde el navegador o `curl` (sin método DELETE).
6. **Esperado**: nada se elimina (la ruta no existe para GET).
   Contrato: [contracts/http-endpoints.md](contracts/http-endpoints.md) §2.

### V-005 — Eliminar libro con portada sin huérfanos (FR-004, SC-005)

1. Asignar portada a un libro sin préstamos registrados y eliminarlo con su confirmación habitual (`003`).
2. **Esperado**: el libro desaparece y el directorio `portadas/{id}/` también; no queda ningún archivo huérfano.

### V-006 — Imagen predeterminada en listado y detalle (FR-004)

1. Con libros sembrados (todos sin portada) revisar `/catalogo` y una ficha.
2. **Esperado**: todos muestran `libro-sin-portada.svg`, con `alt` genérico.

### V-007 — Acceso sin sesión a la URL de portada (FR-007, SC-006)

1. Con una portada existente, abrir `http://127.0.0.1:8000/portadas/{id}` en una ventana **sin sesión** (o `curl -i` sin cookies).
2. **Esperado**: HTTP **401** con la vista en español «Debe iniciar sesión para ver las imágenes de portadas.»; el cuerpo **no** contiene los bytes de la imagen.
3. Repetir con sesión iniciada.
4. **Esperado**: HTTP 200 con la imagen.
5. Sin sesión, abrir el catálogo: las tarjetas muestran la imagen predeterminada (fallback `onerror`), no un icono roto.
   Contrato: [contracts/http-endpoints.md](contracts/http-endpoints.md) §3.

### V-008 — Textos alternativos (FR-008, SC-007)

1. Inspeccionar el HTML del listado y de la ficha con y sin portada.
2. **Esperado**: 100 % de imágenes con `alt` no vacío — título del libro en portadas reales, «Sin portada» en la predeterminada.

### V-009 — Fallo de almacenamiento (FR-006, SC-003)

1. Ejecutar `PortadaFalloAlmacenamientoTest` (disco mockeado para fallar en guardar y en borrar).
2. **Esperado**: en ambos casos, mensaje «No se pudo guardar la imagen. Inténtelo de nuevo.» junto al campo y portada + libro intactos.

### V-010 — Reemplazos concurrentes sin huérfanos (edge case, SC-005)

1. Ejecutar `PortadaConcurrenciaTest` (dos reemplazos superpuestos del mismo libro).
2. **Esperado**: predomina la última escritura (la imagen visible corresponde al último `UPDATE`) y `portadas/{id}` contiene **exactamente un archivo**.

### Automatización

```bash
php artisan test
```

**Esperado**: suites `PortadaSubidaTest`, `PortadaEliminacionTest`, `PortadaAccesoTest`, `PortadaFalloAlmacenamientoTest` y `PortadaConcurrenciaTest` en verde (D-026), junto con las de `001`–`003`.

## Criterios de aceptación cubiertos

| SC | Escenario |
|---|---|
| SC-001 | V-001, V-002 |
| SC-002 | V-001, V-004, V-005 |
| SC-003 | V-002, V-003, V-009 |
| SC-004 | V-004 |
| SC-005 | V-005, V-010 |
| SC-006 | V-007 |
| SC-007 | V-006, V-008 |
