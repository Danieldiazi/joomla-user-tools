# AGENTS.md

Guía para agentes de código (Copilot, Codex, Claude y similares) y para cualquier persona que contribuya. La documentación de usuario está en [README.md](README.md) y [FAQ.md](FAQ.md).

## Qué es

User Tools es un paquete para **Joomla 6** (PHP ≥ 8.3, MySQL/MariaDB y PostgreSQL) que gestiona cuentas de usuario. Tiene cinco herramientas y una pantalla de consulta:

- **Comparar una lista**: bloquea las cuentas que no están en una lista vigente y da de alta las nuevas.
- **Revisar usuarios inactivos**: bloquea las cuentas seleccionadas.
- **Eliminar usuarios inactivos**: borra las cuentas seleccionadas.
- **Tarea programada**: bloquea por inactividad.
- **Quitar de grupos**: saca una cuenta de los grupos que se desmarquen y, en los que se marque «y sus subgrupos», de todos los que cuelgan de ellos (se decide grupo a grupo). Una cuenta cada vez; la lógica está en `GroupRemoval` y la escritura en `AccountRegrouper`.
- **Grupos y niveles de acceso** (solo lectura): qué niveles tiene cada grupo, asignados o heredados, con exportación a CSV. La lógica está en `AccessMatrix`.

Además, un módulo de administración resume en el panel de Usuarios las cuentas pendientes, el estado de las tareas y los avisos, y un módulo del sitio lista los grupos de un usuario o los miembros de un grupo.

Se distribuye como un paquete con el componente `com_usertools`, el plugin `plg_task_usertools`, el módulo de administración `mod_usertools` y el módulo del sitio `mod_usertools_groups`.

## Estructura

| Ruta | Contenido |
|---|---|
| `admin/src/Service/` | Lógica de dominio sin dependencias de la interfaz: `Exclusions`, `InactivityPolicy`, `Comparison`, `UserDirectory` (carga por lotes), `AccountBlocker`, `AccountDeleter`, `ActionLogger`, `Settings`, `Toolkit` (acceso a servicios de Joomla)… La comparten el componente y el plugin. |
| `admin/src/Model/` | Un modelo por herramienta (`CompareModel`, `InactiveModel`, `DeleteModel`, `ReportsModel`) y `ToolStateTrait`, con las previsualizaciones de un solo uso y los resultados en sesión. |
| `admin/src/Controller/` | `DisplayController` comprueba el permiso de cada vista. Los controladores de cada herramienta **heredan de él** y usan `ToolControllerTrait` (token CSRF, permiso, avisos). |
| `admin/src/View/`, `admin/tmpl/`, `admin/layouts/usertools/` | Vistas MVC, plantillas y layouts compartidos (exclusiones, pasos, confirmación). |
| `admin/access.xml`, `admin/config.xml` | Permisos propios (`usertools.*`) y Opciones. |
| `admin/language/{es-ES,en-GB}/` | Idiomas del componente. |
| `media/` | CSS y JS, registrados en `joomla.asset.json`. |
| `plugins/task/usertools/` | Plugin de tarea programada. |
| `modules/mod_usertools/` | Módulo del panel de Usuarios. Solo presenta datos: las cifras y avisos los calculan `DashboardSummary` y `Toolkit::dashboard()`, con caché que se limpia con `Toolkit::clearDashboardCache()` cuando cambian cuentas u Opciones. |
| `modules/mod_usertools_groups/` | Módulo del sitio de solo lectura. No depende del componente: `GroupTree` (reglas puras del árbol de grupos, con pruebas unitarias) y `MembershipHelper` (consultas). Nunca muestra correos ni cuentas bloqueadas, y por defecto oculta las cuentas con acceso a la administración. |
| `package/` | Manifiesto del paquete, `script.php` de instalación (comprobaciones, migración de opciones, registro de acciones, plantilla de correo, colocación inicial del módulo) e idiomas. |
| `scripts/build.php` | Construye los ZIP, `SHA256SUMS.txt` y `updates.xml`. |
| `tests/` | `Unit/` (PHPUnit), `regression/` (controladores y modelos con sustitutos de Joomla), `js/` (jsdom), `integration/` (Joomla real), `verify-build.php`. |

## Reglas de seguridad que no se pueden romper

Cualquier cambio debe mantener estas garantías. Hay pruebas que las cubren.

1. **Los superusuarios nunca se bloquean ni se eliminan**, en ninguna herramienta, aunque se desactive «Excluir administradores». Lo decide `Exclusions::reason()`.
2. **La cuenta que ejecuta una operación manual siempre queda protegida.** La tarea programada actúa sin identidad (`Operator::system()`).
3. **Quien no es superusuario nunca puede bloquear, eliminar ni quitar de grupos cuentas con acceso a la administración.** Un superusuario solo puede perder ese estado si queda otro superusuario activo.
4. **Nada se modifica sin revisión previa.** Las previsualizaciones son de un solo uso, caducan a los 15 minutos y se consumen antes de escribir. Cada cuenta se revalida con un objeto `User` recién cargado justo antes de cambiarla, y la que haya cambiado se omite, nunca se sustituye por otra.
5. **Los cambios en cuentas pasan por la API de usuarios de Joomla** (`User::save()`, `User::delete()`) para que se ejecuten los eventos de los plugins. No se toca `#__users` con SQL para modificar cuentas.
6. **Cada bloqueo o cambio de grupos cierra las sesiones de la cuenta**, y cada bloqueo, alta, eliminación o cambio de grupos se anota en el registro de acciones de usuarios (`ActionLogger`).
7. **Las contraseñas temporales nunca se guardan**: ni en sesión, ni en base de datos, ni en registros. Solo se muestran en la respuesta que crea la cuenta.
8. **Confirmaciones**: la comparación y la revisión de inactivos piden escribir el número de cuentas a partir del umbral configurado. La eliminación lo pide siempre.
9. **La tarea programada tiene un límite de seguridad**: si los candidatos superan el porcentaje configurado de cuentas activas, no bloquea ninguna.
10. **Cada POST comprueba el token CSRF y el permiso de la herramienta**, y las páginas se sirven con `Cache-Control: no-store`.

Si una petición choca con alguna de estas reglas, se señala el conflicto antes de cambiar nada.

## Convenciones

- **Idioma**: el código, los comentarios, los mensajes de commit y los nombres en inglés. La documentación de usuario (README, FAQ) y los textos de la interfaz en español, con su traducción al inglés.
- **Textos de la interfaz**: siempre con claves de idioma (`COM_USERTOOLS_…`, `PLG_TASK_USERTOOLS_…`, `MOD_USERTOOLS_…`, `MOD_USERTOOLS_GROUPS_…`), nunca escritos en el código. Cada clave existe **en es-ES y en-GB a la vez**, sin duplicados. Los servicios lanzan `UserFacingException` con una clave y sus argumentos, y la traducción se hace en controladores y vistas.
- **Salida HTML**: todo valor variable se escapa (`$this->escape()` en las plantillas).
- **Estilos**: clases de Bootstrap y Atum (`card`, `alert`, `btn`, `table`) y variables CSS de la plantilla, sin colores fijos, para que el modo oscuro funcione. El CSS propio es solo de maquetación.
- **Base de datos**: consultas con el query builder de Joomla y parámetros enlazados, compatibles con MySQL y PostgreSQL. Nada de consultas por cuenta dentro de bucles grandes: se usa `UserDirectory`.
- **Exclusiones**: cada herramienta tiene las suyas, guardadas en Opciones con su prefijo (`compare_`, `inactive_`, `delete_`). Cada tarea programada guarda las suyas en sus parámetros.
- **Panel**: todo lo que cambie cuentas u Opciones llama a `Toolkit::clearDashboardCache()`. Las cifras del módulo deben usar las mismas reglas que la herramienta a la que enlazan.
- **Herramienta nueva**: añade su permiso en `access.xml`, la vista en `DisplayController::PERMISSIONS`, su pestaña en `config.xml` si tiene opciones, la tarjeta en la portada, el controlador en `tests/regression/mvc.php`, una sección en `tests/integration/run.php` y la documentación en README y FAQ.

## Documentación bilingüe

- El español sigue siendo el idioma predeterminado del repositorio. Mantén estas parejas completas y sincronizadas en la misma rama y pull request:
  - `README.md` ↔ `README.en.md`.
  - `FAQ.md` ↔ `FAQ.en.md`.
  - `docs/arquitectura.md` ↔ `docs/architecture.en.md`.
  - `docs/capturas.md` ↔ `docs/screenshots.en.md`.
- Todo cambio de instrucciones, comportamiento, requisitos, ejemplos, límites o capturas en uno de estos documentos debe reflejarse en su pareja antes de dar el trabajo por terminado. Una traducción debe conservar el contenido completo, las advertencias y los ejemplos; no sustituirlos por un resumen.
- Inmediatamente después del título principal, ambas versiones llevan un selector **Español · English**: el idioma actual se destaca y el otro enlaza al documento equivalente mediante una ruta relativa.
- Los enlaces entre documentos deben mantenerse en el idioma que se está leyendo, incluidos los fragmentos de sección. Los enlaces al código y a documentación externa conservan su destino. Si un documento solo existe en español, indícalo al enlazarlo desde inglés.
- Las capturas se pueden compartir entre idiomas. Traduce sus textos alternativos y pies, e indica en la versión inglesa que muestran la interfaz en español; no inventes capturas de una interfaz inglesa.
- Al crear documentación nueva destinada a usuarios o colaboradores, añade ambas versiones y sus selectores, y registra la pareja aquí. Los archivos de instrucciones para agentes (`AGENTS.md`, `CLAUDE.md`) no necesitan una copia traducida.
- Al verificar un cambio documental, comprueba en ambas versiones los enlaces relativos, los fragmentos del índice, los selectores recíprocos, las rutas de imágenes y la coherencia de versiones, cifras y comandos. Mantén también las reglas generales de pruebas y CI de esta guía.

## Versiones

- La numeración es **año.mes.revisión**, sin cero inicial en el mes: 2026.10.5; el mes siguiente empieza en 2026.11.0.
- Cada versión nueva cambia el número en **seis sitios**:
  - `com_usertools.xml`
  - `plugins/task/usertools/usertools.xml`
  - `modules/mod_usertools/mod_usertools.xml`
  - `modules/mod_usertools_groups/mod_usertools_groups.xml`
  - `package/pkg_usertools.xml`
  - `media/joomla.asset.json`
- No se reutiliza un número que ya tenga release.

## Pruebas

Antes de proponer un cambio, deben pasar todas estas:

~~~sh
composer install && npm ci
vendor/bin/phpunit                                          # unitarias y regresión MVC
npm test                                                    # JavaScript con jsdom
JOOMLA_PATH=/ruta/a/joomla-6 vendor/bin/phpstan analyse     # nivel 6, sin errores
php scripts/build.php --output=dist && php tests/verify-build.php VERSIÓN dist
~~~

La prueba de extremo a extremo (`tests/integration/run.php`) necesita un Joomla 6 **desechable**, porque crea, bloquea y borra cuentas. También necesita el servidor de correo de pruebas `tests/integration/smtp-sink.php`. Las variables de entorno están en la cabecera del fichero. La CI de GitHub la ejecuta con PostgreSQL y con MySQL en cada push y pull request.

Todo cambio de comportamiento lleva su prueba. Los cambios que afecten a bloqueos, borrados o altas llevan también su comprobación en la prueba de extremo a extremo.

## Flujo de trabajo

- Trabaja en una rama y abre una pull request contra `main`. No hagas push directo a `main`.
- La CI debe estar en verde antes de fusionar.
- La release la publica una persona: crea en GitHub una release con la etiqueta `vAAAA.M.REVISIÓN` sobre `main`. GitHub Actions construye y adjunta los paquetes y el `updates.xml`.
- Actualiza README y FAQ cuando cambie lo que ve o configura el usuario. Si cambian las pantallas, renueva las capturas de `docs/screenshots/`.
