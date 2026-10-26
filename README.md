# Joomla User Tools

Herramientas para administrar cuentas en **Joomla 6**: comparar una lista de usuarios vigentes, crear cuentas nuevas, revisar la inactividad y programar bloqueos con informes.

| Herramienta | Qué hace |
|---|---|
| Comparar una lista | Propone bloquear las cuentas ausentes de la lista completa, salvo las protegidas, y permite crear los usuarios nuevos que selecciones. |
| Revisar usuarios inactivos | Busca cuentas inactivas para que selecciones cuáles bloquear. |
| Eliminar usuarios inactivos | Permite seleccionar y borrar definitivamente cuentas inactivas. Por defecto lista solo las ya bloqueadas. Disponible desde 2026.10.5. |
| Tarea programada | Bloquea cuentas por inactividad; empieza en modo simulación y genera informes. |

**Bloquear conserva la cuenta y sus datos; eliminar la borra y no se puede deshacer.** Solo la herramienta manual de eliminación borra cuentas. Ninguna herramienta reactiva cuentas automáticamente.

**Requisitos:** Joomla 6 y PHP 8.3 o posterior. Compatible con MySQL/MariaDB y PostgreSQL.  
**Descarga:** [última release](https://github.com/Danieldiazi/joomla-user-tools/releases/latest), paquete `pkg_usertools-VERSIÓN.zip`.  
**Licencia:** [GPL-2.0-or-later](LICENSE).

## Índice

- [Instalación y actualizaciones](#instalación-y-actualizaciones)
- [Comparar una lista de usuarios](#comparar-una-lista-de-usuarios)
- [Revisar usuarios inactivos](#revisar-usuarios-inactivos)
- [Eliminar usuarios inactivos](#eliminar-usuarios-inactivos)
- [Tarea programada e informes](#tarea-programada-por-inactividad)
- [Seguridad](#seguridad)
- [Opciones](#opciones)
- [Capturas de las herramientas](#capturas-de-las-herramientas)
- [Desarrollo](#desarrollo)
- [Construcción y publicación](#construcción-y-publicación)

¿Dudas concretas? Consulta las [preguntas frecuentes](FAQ.md).

## Instalación y actualizaciones

Descarga de la [última release](https://github.com/Danieldiazi/joomla-user-tools/releases/latest) el fichero **pkg_usertools-VERSIÓN.zip** e instálalo desde Sistema → Instalar → Extensiones. El paquete instala el componente y el plugin de tareas, y en una instalación nueva habilita el plugin «Tarea - User Tools». Las tareas que crees empiezan siempre en modo simulación.

A partir de la 2026.10.3, Joomla avisa de las versiones nuevas en Sistema → Actualizar → Extensiones, como con cualquier otra extensión: el paquete declara un servidor de actualizaciones que apunta a la última release de GitHub. Para actualizar, instala el paquete nuevo encima; no desinstales el componente. Al actualizar desde versiones anteriores, las exclusiones guardadas se migran solas.

El instalador comprueba las versiones mínimas, registra la extensión en el registro de acciones de usuarios y crea la plantilla de correo «User Tools: cuenta creada». Al desinstalar avisa de que los informes de tareas se conservan en la carpeta de registros.

## Comparar una lista de usuarios

**Ejemplo:** en Joomla existen las cuentas `ana`, `luis` y `marta`. Si pegas una lista con `ana` y `marta`, se propone bloquear a `luis`, salvo que esté protegido o ya bloqueado. Si añades `pedro` y no existe, podrás seleccionarlo para crear su cuenta; no se crea automáticamente.

1. Abre Componentes → User Tools → «Empezar comparación».
2. Pega la lista **completa** de nombres de usuario vigentes, uno por línea, y revisa las exclusiones.
3. En la revisión verás las cuentas que se bloquearán, con nombre, correo, último acceso y grupos, y un filtro si la lista es larga. También verás los nombres que no existen en Joomla, para darlos de alta si quieres.
4. Confirma. El resultado enlaza cada cuenta con el gestor de usuarios. Para deshacer un bloqueo, abre la cuenta y cambia su estado a Habilitado.

Los nombres se comparan sin distinguir mayúsculas y minúsculas: OTRO y otro son la misma cuenta. Una lista vacía no se admite. Si **ningún nombre coincide** con una cuenta existente, la revisión lo avisa en rojo arriba del todo, porque se propondría bloquear todas las cuentas no protegidas.

Una lista incompleta puede producir bloqueos incorrectos: revisa siempre la previsualización.

### Altas de cuentas nuevas

Las cuentas nuevas se crean en el grupo **Registrados** (se comprueba que exista y no tenga acceso a la administración), con un correo distinto para cada una. Hay dos modos, que se eligen en cada operación:

- **Enviar un enlace para elegir la contraseña** (por defecto). La cuenta se crea con una contraseña aleatoria que nadie ve, y la persona recibe un correo con un enlace a la página estándar de Joomla para elegir la suya. El texto del correo se edita en Sistema → Plantillas de correo → «User Tools: cuenta creada». Requiere que el envío de correo esté activado en la configuración global.
- **Mostrar una contraseña temporal.** Se genera una contraseña aleatoria que cumple la política de contraseñas de Joomla (longitud y número mínimo de dígitos, símbolos, mayúsculas y minúsculas) y se muestra **una sola vez**, en la misma respuesta que crea la cuenta. No se guarda en la sesión, en la base de datos ni en los registros. La persona debe cambiarla en su primer acceso.

La herramienta no envía el correo de bienvenida del plugin de usuarios de Joomla.

## Revisar usuarios inactivos

En la portada, «Revisar cuentas inactivas». Elige el plazo en días, si se incluyen quienes nunca accedieron (con el plazo contado desde su registro) y las exclusiones. Busca, marca solo las cuentas que quieres suspender y confirma. No hay cuentas marcadas de entrada, y «Seleccionar todos» solo afecta a las filas visibles con el filtro. Se omiten las cuentas ya bloqueadas y las que tienen una sesión reciente.

## Eliminar usuarios inactivos

En la portada, «Revisar cuentas para eliminar». Es la única herramienta que **borra** cuentas, y el borrado no se puede deshacer; para impedir el acceso sin borrar, usa la revisión de inactivos.

1. Elige el plazo en días (730 por defecto), si se incluyen quienes nunca accedieron y qué cuentas se listan: **solo bloqueadas** (opción por defecto desde 2026.10.6; las que ya bloqueó la revisión de inactivos o la tarea programada), **activas y bloqueadas** o **solo activas**.
2. Configura sus exclusiones, que son propias e iguales que en el resto de herramientas: excluir administradores, nombres de usuario y grupos (con sus subgrupos). Los superusuarios y tu propia cuenta nunca se listan.
3. En la revisión verás nombre, correo, estado, último acceso, fecha de registro y grupos de cada cuenta, con un filtro si la lista es larga. Ninguna cuenta viene marcada.
4. Marca las que quieras eliminar, confirma la casilla y **escribe el número de cuentas**. En esta herramienta se pide siempre, aunque sea una.

Antes de borrar se vuelve a comprobar cada cuenta. Las que hayan cambiado después de la revisión (porque accedieron, pasaron a estar excluidas o tienen una sesión reciente) se omiten y no se sustituyen por otras.

El plazo se cuenta desde el último acceso o, si nunca accedió y se incluye esa opción, desde la fecha de registro. **730 días es una duración fija, no dos años de calendario.**

La cuenta se elimina con la API de usuarios de Joomla, igual que desde el gestor de usuarios. Se borran sus grupos, su perfil, sus sesiones y sus mensajes privados, y otras extensiones pueden limpiar sus propios datos. Los artículos y demás contenidos que creó se conservan, como al borrar desde Joomla.

Cada eliminación queda en el **registro de acciones de usuarios** con el ID, el nombre de usuario, el nombre y quién la hizo («… eliminó con User Tools la cuenta inactiva …»). Joomla añade además su propia entrada de borrado de usuario.

## Tarea programada por inactividad

1. Comprueba que el plugin «Tarea - User Tools» está habilitado; la portada indica su estado.
2. Ve a Sistema → Tareas programadas → Nueva y elige **User Tools: bloquear usuarios inactivos**.
3. Configura el plazo (365 días por defecto), sus exclusiones y el límite de seguridad.
4. La **simulación está activada por defecto**. Ejecútala y revisa el informe en Componentes → User Tools → Resultados de tareas.
5. Cuando los candidatos sean correctos, desactiva la simulación y programa la frecuencia; con una vez al día suele bastar.

El último acceso debe ser **estrictamente anterior** a la fecha límite, calculada en UTC. El plazo admite de 1 a 36.500 días. La tarea actúa sin identidad de usuario: no importa qué visitante dispare el programador de Joomla.

### Límite de seguridad contra bloqueos masivos

Si una ejecución fuera a bloquear **más del porcentaje configurado de las cuentas activas** (20 % por defecto), no bloquea ninguna. El informe la muestra como «Detenida por seguridad» y la tarea termina con error, de modo que Joomla envía su aviso habitual de tarea fallida. Así, una configuración equivocada o unas fechas de acceso erróneas no pueden dejar a casi todos los usuarios sin acceso.

El límite solo se comprueba a partir de un número mínimo de candidatos (10 por defecto), para que en webs pequeñas unas pocas cuentas no detengan la tarea. En simulación solo se avisa en el informe.

Ejemplo con los valores por defecto: en una web con 1.000 cuentas activas, la tarea se detiene si encuentra más de 200 candidatos. En una con 30 cuentas activas, 7 candidatos (23 %) no la detienen porque no llegan al mínimo de 10. Con 0 % o 100 % el límite se desactiva.

Para ejecutar las tareas aunque no haya visitas, configura el cron del alojamiento según la [guía oficial de Joomla](https://guide.joomla.org/user-guide/scheduled-tasks/how-to-choose-a-scheduler).

### Resultados de tareas

Componentes → User Tools → Resultados de tareas muestra cada ejecución con fecha UTC, tarea, modo, estado y cantidades, con filtros por tarea, modo y estado. El detalle lista las cuentas bloqueadas, simuladas, protegidas (con su motivo) o con error, y resume las omitidas por motivo. Cada informe se puede **exportar a CSV** (UTF-8, separado por punto y coma, protegido contra fórmulas al abrirlo en una hoja de cálculo).

Los informes se guardan en la subcarpeta usertools-reports de la carpeta de registros de Joomla, con una cabecera PHP que impide leerlos desde la web. Se borran al terminar cada ejecución según los días configurados en Opciones.

## Seguridad

- **Los superusuarios están siempre protegidos**, en todas las herramientas y aunque se desactive «Excluir administradores». User Tools no bloquea ni elimina cuentas de superusuario.
- **La cuenta que ejecuta una operación manual siempre queda protegida.**
- **Cada bloqueo cierra las sesiones abiertas** de la cuenta, como hace el gestor de usuarios de Joomla (requiere «Metadatos de sesión», activado por defecto).
- **Las altas, los bloqueos y las eliminaciones quedan en el registro de acciones de usuarios** (Usuarios → Registro de acciones de usuarios): quién bloqueó, creó o eliminó cada cuenta y con qué herramienta, o qué tarea programada lo hizo.
- **Permisos propios**, delegables sin dar superusuario (Opciones → Permisos):
  - *Comparar lista y bloquear*, *Crear cuentas*, *Revisar usuarios inactivos*, *Eliminar usuarios inactivos* y *Ver resultados de tareas*.
  - Quien no es superusuario nunca puede bloquear ni eliminar cuentas con acceso a la administración.
- **Confirmación reforzada configurable**: a partir de N cuentas (10 por defecto) hay que escribir el número exacto de cuentas afectadas, además de marcar la casilla.
- **El borrado exige siempre escribir el número de cuentas**, aunque solo se elimine una; el umbral configurable no desactiva esta confirmación.
- **Límite de seguridad en las tareas programadas** contra bloqueos masivos (ver [límite de seguridad](#límite-de-seguridad-contra-bloqueos-masivos)).
- Previsualizaciones de un solo uso que caducan a los 15 minutos, revalidación de cada cuenta justo antes de guardarla, protección CSRF y páginas que no se guardan en caché.

## Opciones

Componentes → User Tools → **Opciones** (botón de la barra de herramientas):

| Pestaña | Ajustes |
|---|---|
| Seguridad | Confirmación reforzada a partir de N cuentas (0 la desactiva); modo de alta por defecto (enlace por correo o contraseña temporal); días que se conservan los informes (90 por defecto, 0 = siempre) |
| Comparación | Excluir administradores, nombres excluidos y grupos excluidos de la comparación manual |
| Revisión de inactivos | Plazo, incluir quien nunca accedió y exclusiones de la revisión manual |
| Eliminación de inactivos | Plazo, cuentas que se listan (solo bloqueadas por defecto, ambas o solo activas), incluir quien nunca accedió y exclusiones de la eliminación |
| Permisos | Permisos de cada herramienta |

Las exclusiones de las herramientas manuales también se guardan al usarlas. Cada tarea programada tiene las suyas en su propia configuración.

## Capturas de las herramientas

Estas dos capturas se incorporaron con la herramienta de eliminación en 2026.10.5, en Joomla 6.2.0.

![Eliminar inactivos: plazo, cuentas listadas y exclusiones](docs/screenshots/11-eliminar-plazo.jpg)

![Eliminar inactivos: selección de cuentas y confirmación](docs/screenshots/12-eliminar-seleccion.jpg)

Consulta la [galería completa](docs/capturas.md) para recorrer las demás herramientas; incluye capturas de versiones anteriores.

## Desarrollo

Estructura:

- `admin/`: componente con controladores, modelos, vistas MVC, plantillas y ficheros de idioma es-ES y en-GB.
- `admin/src/Service/`: lógica de dominio compartida con el plugin.
- `media/`: CSS y JS cargados con el gestor de recursos web (`joomla.asset.json`).
- `plugins/task/usertools/`: plugin de tareas.
- `package/`: manifiesto del paquete, script de instalación e idiomas.

Pruebas:

~~~sh
composer install && npm ci
vendor/bin/phpunit                    # pruebas unitarias de los servicios
npm test                              # comportamiento del JavaScript (jsdom)
JOOMLA_PATH=/ruta/a/joomla-6 vendor/bin/phpstan analyse   # análisis estático, nivel 6
~~~

La prueba de extremo a extremo `tests/integration/run.php` recorre todos los flujos contra un Joomla 6 real y desechable: comparación, altas por correo hasta elegir la contraseña y entrar, contraseñas temporales, permisos delegados, revisión y eliminación de inactivos, tarea con límite de seguridad, informes, CSV y registro de acciones. Instrucciones de uso en la cabecera del fichero; usa `tests/integration/smtp-sink.php` como servidor de correo de pruebas.

GitHub Actions ejecuta en cada push y pull request las pruebas unitarias (PHP 8.3 y 8.4), JavaScript, PHPStan y la prueba de extremo a extremo con PostgreSQL y con MySQL.

## Construcción y publicación

La numeración sigue **año.mes.revisión**, sin cero inicial en el mes: 2026.10.0, 2026.10.1; el siguiente mes empieza en 2026.11.0. Las etiquetas llevan el prefijo v.

~~~sh
php scripts/build.php --output=dist                     # versión del manifiesto
php scripts/build.php --version=v2026.10.5 --output=dist
php tests/verify-build.php 2026.10.5 dist
~~~

Genera los tres ZIP instalables (`pkg_usertools`, `com_usertools` y `plg_task_usertools`), `SHA256SUMS.txt` y `updates.xml`. En Windows también vale `scripts\build.ps1`, que llama al mismo script.

Para publicar, crea una release en GitHub con una etiqueta **vAAAA.M.REVISIÓN** sobre `main` (Releases → Draft a new release → Choose a tag → Publish release). GitHub Actions pasa las pruebas, genera los paquetes con esa versión en los manifiestos y los adjunta a la release junto con `SHA256SUMS.txt` y `updates.xml`. Las instalaciones existentes ven la actualización a través del servidor de actualizaciones. No se admiten sufijos de preversión.

## Referencias

Implementación propia basada en las APIs de Joomla 6 y en el patrón de los plugins oficiales de tareas, como [DeleteActionLogs](https://github.com/joomla/joomla-cms/tree/6.0.0/plugins/task/deleteactionlogs).

Proyecto independiente, sin afiliación oficial a Joomla.
