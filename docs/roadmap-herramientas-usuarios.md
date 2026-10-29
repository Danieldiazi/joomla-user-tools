# Próximas herramientas de User Tools

Este documento define funcionalidades pendientes de integrar en la interfaz. El servicio `BulkGroupAssignments` incorporado en esta rama **solo analiza y planifica** cambios: todavía no permite ejecutarlos desde Joomla.

## 1. Añadir cuentas a grupos en bloque

- Dos entradas: pares `usuario;grupo` (una por línea) y grupo seleccionado + usuarios (uno por línea).
- Resolver nombres de usuario por coincidencia exacta, y grupos por ID o ruta inequívoca. No elegir un grupo ambiguo en silencio.
- Previsualizar cuentas inexistentes, grupos inexistentes o ambiguos, asignaciones existentes, grupos nuevos, y advertencias cuando se añadan permisos de administración.
- Primera versión **solo añade** grupos: nunca elimina membresías existentes. Proteger grupos administrativos y cuentas privilegiadas según permisos; por defecto solo superusuarios.
- Formulario POST con token CSRF, revisión con nonce de un solo uso ligado al operador, confirmación escrita del número de nuevas relaciones.
- Revalidar el usuario, sus grupos y el grupo destino justo antes de cada escritura; ante modificaciones concurrentes, omitir en vez de sobreescribir.
- Guardar con la API de usuarios de Joomla, registrar ID/resultado por cuenta, procesar en tandas con reanudación y advertir cuando no se puedan cerrar sesiones.
- No activar sincronización bidireccional hasta incorporar un modo independiente con análisis explícito de **altas y bajas** y confirmación separada.

## 2. Ficha de una persona (solo lectura)

- Búsqueda de cuenta por usuario/correo, consulta por ID estable y permiso propio de lectura; impedir que usuarios sin permiso de administración puedan inspeccionar cuentas privilegiadas.
- En una pantalla: datos básicos, estado, último acceso UTC, grupos directos y sus ascendientes en árbol y niveles efectivos calculados con las reglas de Joomla.
- Contenido creado por tipo, contabilizado por `created_by` y respetando permisos de visualización de cada componente. No asumir que cualquier tabla de extensión tenga columna `created_by`.
- Entradas del registro de acciones, con paginación, filtros y permiso específico sobre `com_actionlogs`. Distinguir eventos realizados por la persona de cambios hechos **sobre** su cuenta.
- Enlaces a «Quitar de grupos», «Revisar inactivos/bloquear» y «Anonimizar» únicamente si están autorizados. Los enlaces nunca ejecutan cambios ni saltan la revisión/confirmación.
- La ficha no debe incluir operaciones POST de mutación.

## 3. Deshacer bloqueos de una tanda

- Generar un identificador inmutable de tanda para bloqueos manuales y programados; guardar IDs de cuentas **efectivamente bloqueadas** con marca temporal y actor, no solo candidatos/simulaciones.
- Desde el informe, seleccionar «Desbloquear estas cuentas», con lista revisable, exclusiones y confirmación escrita del número.
- No desbloquear cuentas que ya estaban bloqueadas antes de esa tanda, que han sido anonimizadas, eliminadas o bloqueadas de nuevo después. Exigir evidencia de que su bloqueo actual sigue perteneciendo a la tanda seleccionada.
- Revalidar antes de escribir y guardar por cuenta: restaurada, omitida, modificada o error. No intentar restaurar grupos ni contenido.
- Mantener auditoría duradera de quién deshizo la tanda y qué cuentas afectó. Aplicar permisos diferenciados y protección del operador/superusuarios.

## 4. Exportar cuentas a CSV

- Filtros: grupo (opcionalmente subgrupos), inactividad en días, incluir nunca conectados, estado (activo/bloqueado).
- Columnas: ID, usuario, nombre, correo (solo para operadores autorizados), grupos directos y rutas, último acceso UTC, estado, y configuración de verificación en dos pasos.
- Para 2FA, distinguir **habilitada/configurada**, **no configurada** y **dato no disponible** según API de Joomla; no afirmar habilitación por mera disponibilidad de un método.
- Reutilizar `CsvExport` para proteger contra inyección de fórmulas; indicar codificación UTF-8 y formato de grupos consistente.
- Exportación autorizada sin caché, con límite de tamaño o streaming para evitar agotar memoria. No exponer correos o estados 2FA a operadores sin permiso.

## 5. Panel de Usuarios

- Mostrar accesos directos diferenciados únicamente si el visitante está autorizado a acceder a la herramienta.
- Conservar las tarjetas de indicadores actuales y el enlace general a User Tools.
- La primera implementación de los accesos directos se incluye en esta rama con traducciones español/inglés.

## Comprobación antes de publicar

Ejecutar las pruebas de PHP 8.3/8.4, JavaScript, PHPStan e integración Joomla/MySQL/PostgreSQL. Cubrir especialmente permisos, carreras entre revisión/escritura, operaciones parciales, grupos con privilegios y controles CSRF. No fusionar la rama antes de superar las pruebas.
