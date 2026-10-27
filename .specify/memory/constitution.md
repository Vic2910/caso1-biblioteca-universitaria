<!--
Sync Impact Report (material temporal de revisión — eliminar antes del commit)

- Cambio de versión: sin versión (plantilla con marcadores sin resolver) → 1.0
- Principios modificados (título antiguo → título nuevo):
  - PRINCIPLE_1_NAME → I. Modelo de datos primero
  - PRINCIPLE_2_NAME → II. Validación en el servidor
  - PRINCIPLE_3_NAME → III. Reglas de préstamo coherentes
  - PRINCIPLE_4_NAME → IV. Seguridad y operaciones explícitas
  - PRINCIPLE_5_NAME → V. Aplicación local e interfaz clara
- Secciones añadidas: "Restricciones adicionales" (SECTION_2), "Flujo de
  desarrollo" (SECTION_3) y "Gobernanza" (GOVERNANCE_RULES), con contenido
  completo, incluida una política de versionado nueva.
- Secciones eliminadas: ninguna. Se eliminaron únicamente los comentarios de
  ejemplo del scaffold al resolver cada marcador.
- TODOs diferidos: ninguno. Todos los marcadores se resolvieron con la entrada
  del usuario (Versión 1.0; ratificación y última enmienda 2026-10-26).
- Tokens entre corchetes retenidos intencionalmente: `[CONCEPTO]`, marcador
  literal de comentarios en el código del proyecto citado por la constitución.
-->

# Constitución del Proyecto Caso 1 - Biblioteca Universitaria

## Core Principles

### I. Modelo de datos primero

La base de datos se define mediante migraciones para Libro, Categoría y
Préstamo, con claves foráneas, restricciones de unicidad y comportamiento de
eliminación documentado. Los modelos incluyen `$fillable`, casts y relaciones;
una categoría puede tener muchos libros.

Razón: un esquema único y versionado evita divergencias entre la base de datos,
los modelos y las vistas, y hace que cada regla de negocio sea verificable
sobre datos consistentes.

### II. Validación en el servidor

El servidor es la fuente de verdad para validar datos y reglas de negocio
mediante Form Requests y `$request->validated()`. Los formularios muestran
errores en español junto a cada campo y conservan los valores con `old()`.

Razón: la validación del lado del cliente puede eludirse; centralizarla en el
servidor garantiza que toda entrada cumpla las reglas antes de tocar la base de
datos y ofrece un mensaje de error uniforme en cada formulario.

### III. Reglas de préstamo coherentes

Las reglas de disponibilidad, fechas y límite de préstamos se implementan de
forma centralizada en servicios o modelos. El sistema descuenta un ejemplar al
prestar y lo suma al devolver, e impide préstamos sin disponibilidad o que
superen tres préstamos activos por estudiante.

Razón: la lógica de préstamo debe existir en un solo lugar para que ningún
controlador o vista la contradiga y el inventario de ejemplares se mantenga
coherente en todo el sistema.

### IV. Seguridad y operaciones explícitas

Los formularios incorporan `@csrf` y las salidas se escapan con `{{ }}`; las
consultas utilizan Eloquent o Query Builder sin concatenar entradas del usuario.
La eliminación de registros se realiza mediante `DELETE`, nunca mediante GET, y
se solicita confirmación.

Razón: CSRF, inyección SQL y borrados disparados por GET son fallos clásicos;
exigir operaciones explícitas, escapado de salida y consultas parametrizadas
previene que un enlace o una lectura maliciosa alteren o destruyan datos.

### V. Aplicación local e interfaz clara

La aplicación web se ejecuta localmente con Laravel y sin aplicaciones o
servicios de pago obligatorios. La interfaz utiliza un layout Blade común y
presenta un diseño profesional, coherente, adaptable y claro para estudiantes,
docentes y personal administrativo.

Razón: el entorno debe ser reproducible sin costes ni dependencias externas, y
un layout único y claro reduce errores de uso en los tres perfiles de usuario.

## Restricciones adicionales

- **Alcance y datos:** gestionar libros, categorías y préstamos a estudiantes.
  Libro incluye ISBN, título, autor, editorial, año de publicación, número de
  ejemplares, ejemplares disponibles, categoría y portada; Préstamo incluye
  libro, nombre y carné del estudiante, fechas de préstamo, devolución prevista
  y real, y estado activo, devuelto o vencido.
- **Reglas:** ISBN único de 13 dígitos; año de publicación entre 1900 y el año
  actual; devolución prevista posterior al préstamo; disponibilidad obligatoria
  y máximo tres préstamos activos por estudiante.
- **Tecnologías:** PHP 8.3 o superior, Laravel 13 y Composer 2.x; ejecución
  mediante `php artisan serve`. La base de datos puede ser MySQL, MariaDB o
  Supabase/PostgreSQL y sus tablas se crean exclusivamente con migraciones.
- **Presentación:** Blade con layout común y CSS propio; Bootstrap o Tailwind
  son opcionales y, si se utilizan, se declaran en el README.
- **Seguridad:** utilizar Eloquent o Query Builder sin SQL concatenado;
  validar en el servidor; proteger los formularios con `@csrf`, escapar la
  salida y excluir credenciales del repositorio. Toda eliminación utiliza
  `DELETE`.
- **Imágenes:** almacenar las portadas con Laravel Storage y guardar su ruta en
  la base de datos. Validar JPG, PNG o WebP hasta 2 MB, reemplazar y eliminar
  correctamente los archivos y mostrar una imagen predeterminada cuando no
  exista portada.

## Flujo de desarrollo

- **Equipo y repositorio:** equipo de 2 a 4 integrantes, con un único
  repositorio público y aportes distribuidos. Usar Conventional Commits y, como
  convención recomendada, una rama `feature/` por funcionalidad; el primer
  commit contiene el proyecto Laravel recién creado.
- **Fase 1 — base y consulta (aproximadamente 30 % del sistema):** configurar el
  proyecto y la conexión; crear todas las migraciones, modelos y relaciones;
  incorporar seeders y factory con al menos 15 libros y tres registros por
  catálogo; implementar listado paginado y detalle con layout común e imagen
  predeterminada. Completar el README inicial y los comentarios `[CONCEPTO]`
  correspondientes. Registrar al menos 12 commits, con al menos 3 por
  integrante, en 2 o más días, y cerrar con la etiqueta `v1.0`.
- **Fase 2 — sistema completo:** continuar sobre el mismo repositorio e
  implementar el CRUD de libros, registro y listado de préstamos, validaciones y
  reglas del caso, gestión de portadas, búsqueda o filtro, interfaz adaptable y
  panel con libros más prestados, préstamos vencidos y ejemplares disponibles
  por categoría. Completar el README y los comentarios `[CONCEPTO]`; registrar
  al menos 20 commits nuevos, con al menos 5 por integrante, en 3 o más días, y
  cerrar con la etiqueta `v2.0`.
- **Evaluación:** cada fase se califica sobre 100 puntos. En cada criterio,
  excelente corresponde al 100 % de sus puntos, aceptable al 60–80 % e
  insuficiente al 0–40 %.
  - Fase 1: entorno y configuración 10; modelo de datos y migraciones 25;
    modelos y relaciones 15; seeders y factories 10; rutas y controladores 10;
    vistas y diseño base 15; documentación 5; Git y GitHub 10.
  - Fase 2: CRUD de la entidad principal 20; entidad relacionada 15;
    validación de formularios 15; reglas de negocio 10; imágenes 10; interfaz y
    panel/reporte 10; seguridad y buenas prácticas 5; documentación 5; Git y
    GitHub 10.
- **Documentación y revisión:** el README es la documentación oficial; incluye
  instalación, motor de base de datos, diagrama entidad-relación, reglas,
  capturas y distribución del trabajo. En la Fase 2 documenta los cambios
  respecto a la Fase 1; no se entrega informe escrito. Cada fase se revisa
  frente a sus entregables y a las reglas de negocio y calidad aplicables; las
  reglas completas se validan en el servidor en la Fase 2.

## Governance

Esta constitución prevalece sobre cualquier otra práctica del proyecto.

- **Procedimiento de enmienda:** toda enmienda debe documentarse (qué cambia,
  por qué y bajo qué versión) y contar con el acuerdo del equipo antes de
  considerarse vigente.
- **Política de versionado:** la versión de la constitución sigue la semántica
  MAJOR.MINOR.PATCH. MAJOR cuando se elimina o redefine un principio de forma
  incompatible; MINOR cuando se añade un principio o sección o se amplía
  materialmente su orientación; PATCH para aclaraciones, correcciones de
  redacción y mejoras no semánticas.
- **Revisión de cumplimiento:** cada entrega debe verificar el cumplimiento de
  las reglas de negocio y de calidad aplicables a la fase. Las reglas completas
  se validan en el servidor en la Fase 2 y la Fase 1 se revisa frente a sus
  entregables; el README es la evidencia documental de cada entrega.

**Versión**: 1.0 | **Ratificada**: 2026-10-26 | **Última enmienda**: 2026-10-26
