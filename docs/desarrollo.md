# Guía de incorporación para desarrolladores

**Español** · [English](development.en.md)

[Volver al README](../README.md#desarrollo) · [Arquitectura completa](arquitectura.md) · [Normas del proyecto](../AGENTS.md)

## Propósito

Este documento es una ruta de entrada para una persona que no conoce el código. No sustituye la documentación de arquitectura, el código, las pruebas ni las APIs públicas de Joomla. Trabaja sobre `main` o la rama de tu cambio y comprueba que lo descrito siga vigente; no reconstruyas reglas a partir de conversaciones con agentes de IA.

## Primera lectura (en este orden)

1. **[AGENTS.md](../AGENTS.md)**: invariantes de seguridad, convenciones, accesibilidad, documentación bilingüe y pruebas obligatorias.
2. **[README.md](../README.md)** y **[FAQ.md](../FAQ.md)**: comportamiento observable, permisos y límites de cada herramienta.
3. **[Arquitectura](arquitectura.md)**: mapa de capas, estados de sesión, persistencia, fallos parciales y matriz de archivos/pruebas.
4. **[admin/src/Controller/DisplayController.php](../admin/src/Controller/DisplayController.php)** y **[admin/src/Service/Navigation.php](../admin/src/Service/Navigation.php)**: acceso a vistas, categorías y permisos efectivos.
5. **[admin/src/Model/ToolStateTrait.php](../admin/src/Model/ToolStateTrait.php)**: previsualizaciones, identidad, caducidad, confirmaciones y resultados.
6. **[admin/src/Service/Toolkit.php](../admin/src/Service/Toolkit.php)**: construcción de servicios; **[admin/src/Service/UserDirectory.php](../admin/src/Service/UserDirectory.php)**: carga de usuarios, grupos y sesiones.

## Ejemplo de recorrido: comparar una lista y bloquear/crear cuentas

1. **Entrada y autorización**: `CompareController` usa las comprobaciones compartidas de `ToolControllerTrait` y los permisos de `DisplayController`. Las solicitudes que cambian estado deben validar token CSRF y autorización en el servidor.
2. **Preparación y revisión**: `CompareModel` interpreta el borrador, calcula candidatos mediante `Comparison`, `Exclusions` y `UserDirectory`, y utiliza `ToolStateTrait` para guardar una revisión de un solo uso asociada al operador.
3. **Aplicación por tandas**: antes de escribir se revalida la revisión. `CompareModel::apply()` la consume y crea un trabajo en sesión. `CompareModel::step()` opera por lotes, guarda checkpoints por cuenta y permite continuar, sin repetir una alta existente si se corta la petición tras crearla.
4. **Escritura**: `AccountBlocker` y `Registration` emplean las APIs de usuario de Joomla; las reglas y permisos se comprueban de nuevo. `ActionLogger` registra las acciones y el bloqueo cierra sesiones. La creación puede enviar un enlace por correo o devolver una contraseña temporal solo en esa respuesta.
5. **Salida**: la vista y las plantillas de `admin/src/View/` y `admin/tmpl/compare/` presentan revisión, progreso y resultado. `media/js/admin.js` mejora la interfaz; ninguna comprobación del navegador sustituye las del servidor.

Sigue el mismo itinerario en otras herramientas: **Controller → Model → Service → API de Joomla** y, por otra rama, **View → plantilla/layout → JavaScript**. El plugin de tareas no utiliza modelos de pantalla: entra por **[plugins/task/usertools/src/Extension/Usertools.php](../plugins/task/usertools/src/Extension/Usertools.php)** y reutiliza servicios.

## Garantías que debes localizar antes de cambiar algo

- **Identidades y privilegios**: `Exclusions`, `Operator` y `DisplayController`. Los superusuarios y la identidad que ejecuta una acción manual tienen protecciones específicas; los cambios de grupos exigen reglas adicionales.
- **Revisión ≠ autorización permanente**: `ToolStateTrait` guarda la previsualización temporal, pero las escrituras revalidan permisos, estado del usuario y pertenencia al conjunto ofrecido. No se añaden silenciosamente candidatos nuevos.
- **Persistencia y reintentos**: los trabajos manuales por tandas viven en sesión y los cambios ya aplicados no se revierten automáticamente. `ReportStore` guarda informes programados y tandas de bloqueo para deshacer; `ActionLogger` es otra evidencia distinta.
- **Datos personales y anonimización**: `AccountAnonymizer` separa identidad verificada, limpieza adicional y acciones de plugins cuya eficacia no puede verificarse completamente. El alcance no equivale al borrado completo de datos externos, copias de seguridad o contenido.
- **Seguridad de la presentación**: comprobaciones CSRF, ACL y revalidación en el servidor; escape HTML, exportación CSV segura y respuestas sensibles sin caché.

Lee la sección «Reglas de seguridad que no se pueden romper» de [AGENTS.md](../AGENTS.md) antes de editar cualquier operación con cuentas.

## Pruebas y revisión

Desde la raíz del repositorio:

```sh
composer install && npm ci
vendor/bin/phpunit
npm test
JOOMLA_PATH=/ruta/a/joomla-6 vendor/bin/phpstan analyse
```

Las pruebas de extremo a extremo están en [tests/integration/run.php](../tests/integration/run.php); consulta su cabecera antes de ejecutarlas. **Crean, bloquean, modifican y eliminan datos**: usa exclusivamente un Joomla de pruebas desechable. La CI cubre MySQL y PostgreSQL. Para cambios documentales puros, comprueba enlaces relativos y coherencia es-ES/en-GB; no afirmes que una prueba se ejecutó si no la has ejecutado.

Antes de abrir una PR: confirma el alcance funcional, localiza la prueba de la regla cambiada, revisa los efectos secundarios, y actualiza comentarios, PHPDoc, arquitectura y documentación de usuario que hayan quedado incorrectos. Evita explicar PHP evidente; registra el porqué y las limitaciones.

## Dónde pedir más detalle

Consulta [la arquitectura completa](arquitectura.md), la matriz de archivos y pruebas que incluye, [AGENTS.md](../AGENTS.md) y las pruebas del módulo. La [auditoría de documentación interna](documentation-audit.md) registra hallazgos y oportunidades de mejora sin tratar la ausencia de PHPDoc en un método trivial como defecto automático.
