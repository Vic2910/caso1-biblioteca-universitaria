# Quickstart: Guía de validación de 009 (WCAG 2.1 AAA aplicable)

**Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md) | **Contracts**: [contracts/](contracts/)

Guía ejecutable para demostrar que el rediseño funciona de extremo a extremo. No contiene implementación: solo pasos, escenarios y resultados esperados.

## Requisitos previos

```bash
composer install
npm install
npm run build            # o `npm run dev` durante el desarrollo (D-048)
cp .env.example .env && php artisan key:generate
php artisan migrate --seed   # seeders existentes (≥ 15 libros, 3 categorías, cuentas del equipo)
php artisan serve            # http://127.0.0.1:8000
```

Comandos de prueba:

```bash
php artisan test                       # toda la batería (las 60 suites existentes deben seguir en verde)
php artisan test --filter=AccionPrincipalTest
vendor/bin/pint --dirty --format agent # PHP tocado
```

## Escenarios

### V-001 — Home con accesos reales (FR-012, SC-004)

1. Abre `GET /` sin sesión.
2. **Esperado**: ves la rejilla de accesos (Catálogo, Libros, Categorías, Préstamos, Panel), las estadísticas y la acción primaria «Iniciar sesión» — **no** un aviso de conexión.
3. Pulsa «Préstamos» sin sesión → llegas a `GET /login`; no se ejecuta ninguna operación.
4. Inicia sesión y repite: llegas directo a `/prestamos` en ≤ 2 pasos desde el home.

### V-002 — Acción principal en cada listado (FR-011, SC-004, SC-009)

1. Con sesión, visita `/catalogo`, `/libros`, `/categorias`, `/prestamos`.
2. **Esperado**: cada pantalla muestra un botón primario visible («Registrar préstamo», «Añadir libro», «Nueva categoría», «Registrar préstamo»), también con la lista llena.
3. Vacía una tabla (o usa datos de prueba sin registros) → mensaje «No hay …» **junto** a la acción; nunca un área vacía.
4. En `/prestamos`, pulsa «Registrar préstamo» → formulario de registro accesible (hoy inalcanzable).

### V-003 — Navegación con teclado (FR-004, FR-007, SC-002)

1. Sin ratón (solo `Tab`/`Shift+Tab`/`Enter`/`Espacio`/flechas/`Escape`), recorre: skip link → navegación → menú «Gestión» → contenido → acciones → pie.
2. **Esperado**: todo es alcanzable; el menú se abre con `Enter`/`Espacio`, anuncia su estado, `Escape` lo cierra devolviendo el foco al disparador; sin trampas de foco; el indicador de foco es siempre visible y no queda bajo la cabecera.

### V-004 — Degradación sin JavaScript (FR-007, SC-005)

1. Desactiva JavaScript en el navegador (o bloquea `public/build/*`).
2. Recorre home, catálogo, listados, un listado de menús/hamburguesa, formularios y borrados.
3. **Esperado**: todas las secciones y pantallas siguen siendo navegables y legibles; los `<details>` abren/cierran nativamente; los formularios se envían; el toast permanece visible; solo se pierden las mejoras de [contracts/ui-structure.md](contracts/ui-structure.md) §7.

### V-005 — Breakpoints (FR-009, SC-006)

1. En 360 × 640, 768 × 1024 y ≥ 1024 px, visita las 8 pantallas y abre el menú.
2. **Esperado**: sin recortes, sin scroll horizontal, sin solapamientos; en 360 px aparece la hamburguesa y expone toda la navegación; en ≥ 1024 px la navegación es horizontal.

### V-006 — Contraste 7:1 y estados sin color (FR-002, FR-003, SC-001)

1. Con la herramienta de contraste (extensión de navegador o calculadora), mide el texto real de las 8 pantallas, incluidos estados hover/foco/error/deshabilitado y los badges de estado.
2. **Esperado**: todo texto normal ≥ **7:1**.
3. Visualiza la interfaz en escala de grises (o con la paleta del SO en alto contraste): los estados `activo`/`devuelto`/`vencido`, la disponibilidad, los errores y el éxito siguen comprensibles por texto/icono.

### V-007 — Pre-validación de portada (FR-017, SC-007)

1. En «Añadir libro», elige un archivo de 3 MB → **esperado**: rechazo inmediato con «El archivo debe ser una imagen JPG, PNG o WebP de hasta 2 MB.», campo limpio, formulario intacto.
2. Elige un `.pdf` o `.gif` → mismo rechazo.
3. Elige un JPG válido → vista previa y envío normal.

### V-008 — Rechazo del servidor de portada (FR-018, SC-007)

1. Repite el envío forzando el rechazo en servidor (p. ej. archivo que supere el límite sin JS activo).
2. **Esperado**: error en español junto al campo, resto del formulario conservado con `old()`, página plenamente operable — nunca una página rota.

### V-009 — Aviso y extensión de sesión (FR-005, SC-008)

1. Reduce el umbral de prueba de la sesión (p. ej. `SESSION_LIFETIME` temporal o avance de reloj en la prueba automatizada).
2. Espera al aviso: **«Quedan 5 minutos antes de que finalice su sesión.»** con «Extender sesión» y «Cerrar sesión».
3. Pulsa «Extender sesión» → el diálogo se cierra, «Sesión extendida.» y **los datos introducidos siguen ahí**.
4. Deja caducar sin responder → se pide iniciar sesión y la operación no se ejecuta a medias.

### V-010 — Confirmación de éxito (FR-022, SC-012)

1. Registra un préstamo, un libro, una categoría y una devolución.
2. **Esperado**: toast en español con `role="status"` (anunciado), auto-ocultado a los ~6 s con JS y **persistente sin JS**; en todos los casos el registro aparece en el listado aunque el mensaje desaparezca. Los errores no se auto-ocultan.

### V-011 — Estados de carga y foco en errores (FR-010, FR-013)

1. En el catálogo, teclea en el buscador: durante el filtro se muestra «Cargando…» anunciado en la región de resultados.
2. Envía un formulario con varios campos inválidos: todos los errores en español junto a sus campos, valores conservados y foco llevado al primer error.

### V-012 — Auditoría AAA (FR-001, FR-023, SC-003)

1. **Herramienta automatizada** (axe o equivalente) sobre las 8 pantallas × 3 tamaños.
2. **Revisión manual** de lo que la herramienta no cubre: teclado completo (V-003), sin JS (V-004), foco (V-003), aviso/extensión de sesión (V-009), estados sin color (V-006), zoom 200 % y `prefers-reduced-motion`.
3. **Esperado**: cero incumplimientos sobre los criterios marcados como aplicables en la matriz de [spec.md](spec.md); resultados tabulados en el README.

### V-013 — Sistema de diseño y README (FR-015, FR-016, SC-010)

1. Compara dos pantallas distintas (p. ej. listado de libros y formulario de préstamo): mismos tokens de tipografía, espaciado, color, radios, botones, campos y estados.
2. Revisa el README: sección «Sistema de diseño» con Tailwind 4 + Vite, tokens, tipografía, espaciado, color, componentes y estados, más capturas de 360 × 640, 768 × 1024 y ≥ 1024 px.

### V-014 — Contratos reemplazados y regresión (FR-020, FR-021, SC-011)

1. Revisa la sección «Contratos de interfaz reemplazados» de [spec.md](spec.md): aparecen 002–008 con su alcance.
2. Ejecuta `php artisan test`: **las suites existentes siguen en verde** y no hay migraciones nuevas (`git status database/migrations` vacío) — evidencia de que no se tocaron reglas de negocio ni datos.
