# Joomla User Tools

**Español** · [English](README.en.md)

Herramientas para administrar cuentas en **Joomla 6**: comparar una lista de usuarios vigentes, crear cuentas nuevas, revisar la inactividad y programar bloqueos con informes.

| Herramienta | Qué hace |
|---|---|
| Comparar una lista | Propone bloquear las cuentas ausentes de la lista completa, salvo las protegidas, y permite crear los usuarios nuevos que selecciones. |
| Revisar usuarios inactivos | Busca cuentas inactivas para que selecciones cuáles bloquear. |
| Eliminar usuarios inactivos | Permite seleccionar y borrar definitivamente cuentas inactivas. Por defecto lista solo las ya bloqueadas. Disponible desde 2026.10.5. |
| Tarea programada | Bloquea cuentas por inactividad; empieza en modo simulación y genera informes. |
| Resumen en el panel | Muestra en el panel de Usuarios las cuentas pendientes, el estado de las tareas y avisos. Disponible desde 2026.10.6. |
| Grupos de usuarios en el sitio | Módulo del sitio que lista los grupos de un usuario o los miembros de un grupo. Solo lee. Disponible desde 2026.10.9. |
| Quitar de grupos | Elige una cuenta, desmarca los grupos de los que debe salir y, si quieres, quítala también de todos sus subgrupos. Con revisión previa y confirmación. Disponible desde 2026.10.9. |
| Grupos y niveles de acceso | Tabla de solo lectura con los niveles de acceso que tiene cada grupo, asignados o heredados. Disponible desde 2026.10.9. |
| Contenido por nivel de acceso | Cuántos artículos, categorías, menús, módulos, campos y demás elementos usan cada nivel, con enlace a cada listado filtrado. Solo lee. Disponible desde 2026.10.12. |

**Bloquear conserva la cuenta y sus datos; eliminar la borra y no se puede deshacer.** Solo la herramienta manual de eliminación borra cuentas. Ninguna herramienta reactiva cuentas automáticamente.

La portada agrupa las herramientas en cuatro secciones, con su explicación siempre visible: **Revisar cuentas** (comparar una lista y revisar inactivos), **Grupos y niveles de acceso** (quitar de grupos y las dos pantallas de consulta), **Automatización y seguimiento** (la tarea programada, con el estado de su plugin, y sus resultados) y, al final y aparte, **Eliminar cuentas**, la única marcada como «Permanente». Cada persona ve solo las herramientas que tiene permitidas, y las secciones sin ninguna desaparecen. Desde cualquier otra pantalla, el botón «Inicio de User Tools» de la barra de Joomla vuelve a la portada.

**Requisitos:** Joomla 6 y PHP 8.3 o posterior. Compatible con MySQL/MariaDB y PostgreSQL.  
**Descarga:** [última release](https://github.com/Danieldiazi/joomla-user-tools/releases/latest), paquete `pkg_usertools-VERSIÓN.zip`.  
**Licencia:** [GPL-2.0-or-later](LICENSE).

## Índice

- [Instalación y actualizaciones](#instalación-y-actualizaciones)
- [Comparar una lista de usuarios](#comparar-una-lista-de-usuarios)
- [Revisar usuarios inactivos](#revisar-usuarios-inactivos)
- [Eliminar usuarios inactivos](#eliminar-usuarios-inactivos)
- [Tarea programada e informes](#tarea-programada-por-inactividad)
- [Resumen en el panel de Usuarios](#resumen-en-el-panel-de-usuarios)
- [Módulo de grupos de usuarios en el sitio](#módulo-de-grupos-de-usuarios-en-el-sitio)
- [Quitar de grupos](#quitar-de-grupos)
- [Grupos y niveles de acceso](#grupos-y-niveles-de-acceso)
- [Contenido por nivel de acceso](#contenido-por-nivel-de-acceso)
- [Seguridad](#seguridad)
- [Opciones](#opciones)
- [Capturas de las herramientas](#capturas-de-las-herramientas)
- [Desarrollo](#desarrollo)
- [Mapa de arquitectura](docs/arquitectura.md)
- [Construcción y publicación](#construcción-y-publicación)

¿Dudas concretas? Consulta las [preguntas frecuentes](FAQ.md). Si vas a contribuir, o a trabajar con un agente de código, lee [AGENTS.md](AGENTS.md).

## Instalación y actualizaciones

Descarga de la [última release](https://github.com/Danieldiazi/joomla-user-tools/releases/latest) el fichero **pkg_usertools-VERSIÓN.zip** e instálalo desde Sistema → Instalar → Extensiones. El paquete instala el componente, el plugin de tareas, el módulo de administración «User Tools» y el módulo del sitio «User Tools - Grupos de usuarios». En una instalación nueva habilita el plugin «Tarea - User Tools», y la primera vez que se instala el módulo lo publica en el panel de Usuarios. Las tareas que crees empiezan siempre en modo simulación.

A partir de la 2026.10.3, Joomla avisa de las versiones nuevas en Sistema → Actualizar → Extensiones, como con cualquier otra extensión: el paquete declara un servidor de actualizaciones que apunta a la última release de GitHub. Para actualizar, instala el paquete nuevo encima; no desinstales el componente. Al actualizar desde versiones anteriores, las exclusiones guardadas se migran solas.

El instalador comprueba las versiones mínimas, registra la extensión en el registro de acciones de usuarios y crea la plantilla de correo «User Tools: cuenta creada». Al desinstalar avisa de que los informes de tareas se conservan en la carpeta de registros.

## Comparar una lista de usuarios

**Ejemplo:** en Joomla existen las cuentas `ana`, `luis` y `marta`. Si pegas una lista con `ana` y `marta`, se propone bloquear a `luis`, salvo que esté protegido o ya bloqueado. Si añades `pedro` y no existe, podrás seleccionarlo para crear su cuenta; no se crea automáticamente.

1. Abre Componentes → User Tools → «Comparar lista».
2. Pega la lista **completa** de nombres de usuario vigentes, uno por línea, y revisa las exclusiones.
3. En la revisión verás las cuentas que se bloquearán, con nombre, correo, último acceso y grupos, y un filtro si la lista es larga. También verás los nombres que no existen en Joomla, para darlos de alta si quieres.
4. Confirma. El resultado enlaza cada cuenta con el gestor de usuarios. Para deshacer un bloqueo, abre la cuenta y cambia su estado a Habilitado.

Desde la 2026.10.11, los cambios se aplican **por tandas** de unos segundos, para que ninguna petición choque con el límite de tiempo de PHP o del servidor web (antes, dar de alta cientos de cuentas de golpe podía acabar en un error 500 a mitad). Si caben en una tanda, el resultado aparece directamente. Si no, una barra de progreso muestra cuántas cuentas lleva y la página pide la siguiente tanda sola. Cada cuenta se vuelve a comprobar, se cambia y se anota en el registro de acciones por separado, así que un corte no pierde nada de lo hecho. Si cierras la pestaña o se corta la conexión, «Comparar lista» avisa de la operación a medias y ofrece **continuar** o **descartar** lo pendiente; mientras tanto no se puede aplicar otra comparación. En el modo de contraseña temporal, cada contraseña aparece en la pantalla de progreso al crearse su cuenta (con un botón «Copiar todo») y no se guarda en ningún sitio: cópialas antes de salir.

Los nombres se comparan sin distinguir mayúsculas y minúsculas: OTRO y otro son la misma cuenta. Una lista vacía no se admite. Si **ningún nombre coincide** con una cuenta existente, la revisión lo avisa en rojo arriba del todo, porque se propondría bloquear todas las cuentas no protegidas.

Una lista incompleta puede producir bloqueos incorrectos: revisa siempre la previsualización.

### Altas de cuentas nuevas

Las cuentas nuevas se crean en el grupo **Registrados** (se comprueba que exista y no tenga acceso a la administración), con un correo distinto para cada una. Hay dos modos, que se eligen en cada operación:

- **Enviar un enlace para elegir la contraseña** (por defecto). La cuenta se crea con una contraseña aleatoria que nadie ve, y la persona recibe un correo con un enlace a la página estándar de Joomla para elegir la suya. El texto del correo se edita en Sistema → Plantillas de correo → «User Tools: cuenta creada». Requiere que el envío de correo esté activado en la configuración global.
- **Mostrar una contraseña temporal.** Se genera una contraseña aleatoria que cumple la política de contraseñas de Joomla (longitud y número mínimo de dígitos, símbolos, mayúsculas y minúsculas) y se muestra **una sola vez**, en la misma respuesta que crea la cuenta. No se guarda en la sesión, en la base de datos ni en los registros. La persona debe cambiarla en su primer acceso.

Si la lista trae muchos nombres nuevos, dos ayudas de la revisión ahorran trabajo (desde la 2026.10.11):

- La casilla de la cabecera **Añadir** marca o desmarca todas las cuentas nuevas a la vez.
- **Generar el correo a partir del nombre de usuario**: marca la casilla y escribe el dominio (por ejemplo `correo.com`, con o sin `@`). Cada correo vacío se rellena como `usuario@correo.com`. Los correos escritos a mano no se cambian; si cambias el dominio se actualizan los generados, y si desmarcas la casilla se borran. Los nombres que no sirven como correo (con espacios o tildes, por ejemplo) se quedan vacíos y se avisa de cuántos son, para que escribas su correo. Revisa los correos antes de aplicar: con el modo de enlace, el acceso se envía a ellos.

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

## Resumen en el panel de Usuarios

Desde la 2026.10.6, el panel **Usuarios** de la administración (el icono de panel junto a «Usuarios» en el menú lateral) muestra un módulo «User Tools» con:

- **Cuentas inactivas sin bloquear**, con el plazo y las exclusiones de «Revisar usuarios inactivos». La cifra enlaza con esa herramienta.
- **Cuentas bloqueadas pendientes de eliminar**: las que ofrecería «Eliminar usuarios inactivos» con «Solo bloqueadas», su plazo y sus exclusiones. La cifra abre la herramienta con esa opción ya elegida.
- **Tareas programadas de User Tools**, con modo (simulación o real), última ejecución, resultado (enlazado con su informe) y próxima ejecución.
- **Avisos**: plugin de tareas deshabilitado o no instalado, ninguna tarea habilitada, una tarea detenida por el límite de seguridad, fallida o con un informe sin finalizar desde hace más de una hora, y una tarea que sigue en simulación más de 14 días después de crearla.

Las cifras usan exactamente las mismas reglas que las herramientas, así que coinciden con lo que verás al abrirlas. Se calculan como mucho cada **10 minutos** y se recalculan en cuanto una herramienta o una tarea cambia cuentas, o se guardan las Opciones.

El módulo respeta los permisos: solo lo ve quien puede acceder a User Tools, las cifras solo aparecen a quien tiene permiso de revisar o eliminar inactivos, y las tareas solo a quien puede ver sus resultados. Se configura como cualquier módulo de administración (Contenido → Módulos del sitio de administración, «User Tools»): puedes moverlo a otro panel, cambiarle el título o despublicarlo. Las actualizaciones no vuelven a colocarlo si lo has movido o despublicado.

![Módulo User Tools en el panel de Usuarios](docs/screenshots/13-panel-usuarios.jpg)

## Módulo de grupos de usuarios en el sitio

Desde la 2026.10.9, el paquete incluye el módulo del sitio **«User Tools - Grupos de usuarios»**. Joomla no trae ningún módulo que muestre en el sitio a qué grupos pertenece alguien ni quién está en un grupo; este lo hace en dos modos. El instalador no lo publica: créalo en Contenido → Módulos del sitio → Nuevo.

**Grupos de un usuario.** Muestra los grupos del *visitante identificado* (cada persona ve los suyos; a los invitados no se les muestra nada) o de *un usuario concreto* elegido en la configuración. Opciones:

- *Incluir grupos heredados*: añade los grupos padre de los que hereda permisos, atenuados. Por ejemplo, un miembro de «Autor» verá también «Registrado».
- *Grupos que no se muestran*: grupos que nunca aparecen, como «Público».

**Miembros de un grupo.** Lista las cuentas activas de un grupo, en orden alfabético. Opciones:

- *Incluir subgrupos*: lista también a quien está en los grupos que cuelgan del elegido.
- *Mostrar cada miembro como*: nombre, nombre de usuario o los dos.
- *Ocultar cuentas con acceso a la administración* (activada por defecto): no lista a nadie que esté en un grupo con acceso a la administración o de superusuarios, para no exponer en el sitio las cuentas más sensibles.
- *Máximo de miembros* (50 por defecto, hasta 1000) y *Mostrar el total de miembros*. Si hay más miembros que el máximo, el módulo indica cuántos faltan.

En los dos modos, *Mostrar si la lista está vacía* decide si el módulo se ve cuando no hay nada que listar.

**Privacidad.** El módulo nunca muestra correos electrónicos ni cuentas bloqueadas, y no cambia cuentas ni grupos. Aun así, los nombres de los miembros son datos personales: usa el campo **Acceso** del módulo para decidir quién lo ve (por ejemplo, *Registrado*) en lugar de dejarlo en *Público*. Para los invitados, la lista de miembros sigue la caché de módulos de Joomla; a los usuarios identificados se les calcula siempre al momento.

La lista se obtiene con un número fijo de consultas, sea cual sea el tamaño del grupo. Las cuentas con acceso a la administración se deciden por grupo, con los permisos globales de Joomla, como en el resto de User Tools.

## Quitar de grupos

Desde la 2026.10.9, **Quitar de grupos** saca una cuenta de los grupos que elijas y, si lo pides, también de todos los grupos que cuelgan de ellos: hijos, nietos y siguientes. El lote de Joomla («Quitar del grupo» en Usuarios) solo quita del grupo exacto que eliges y no enseña antes los grupos de la cuenta.

1. **Cuenta**: escribe parte del usuario, el nombre o el correo y elige la cuenta en la lista que aparece mientras escribes. También vale el usuario o el correo completos, sin distinguir mayúsculas.
2. **Grupos**: aparecen solo los grupos a los que pertenece la cuenta, todos marcados y en su árbol. Desmarca los que debe dejar. Al desmarcar un grupo que tiene subgrupos aparece a su lado **«y sus subgrupos»**: si lo marcas, se desmarcan solos los que cuelgan de él y se indica por qué. Cada grupo se decide por separado, así que puedes hacerlo con varios sin que se deshagan entre sí; si desmarcas «y sus subgrupos» o vuelves a marcar el grupo, sus subgrupos recuperan su estado. Los grupos padre no se tocan nunca.
3. **Revisar**: cada grupo de la cuenta con «Se quita» o «Se mantiene», y por qué grupo se quita uno por «y sus subgrupos». No se cambia nada hasta confirmar. La revisión es de un solo uso y caduca a los 15 minutos.
4. **Resultado**: los grupos que conserva la cuenta y enlaces a su ficha y al registro de acciones.

![Quitar de grupos: grupos de la cuenta en su árbol](docs/screenshots/16-quitar-grupos-seleccion.jpg)

![Quitar de grupos: revisión](docs/screenshots/17-quitar-grupos-revision.jpg)

Reglas:

- **Si la cuenta se queda sin grupos**, se le asigna el grupo de los usuarios nuevos de las Opciones de Usuarios (normalmente «Registrado»). Si ese grupo es uno de los que se quitan, o cuelga de uno de ellos (heredaría sus permisos), no se cambia nada y se explica por qué.
- **Nunca cambia tu propia cuenta**: no se puede elegir en el primer paso.
- **Quien no es superusuario nunca cambia cuentas con acceso a la administración** ni superusuarios: tampoco se pueden elegir.
- **Un superusuario puede dejar de serlo** solo si queda al menos otro superusuario activo; la revisión avisa cuando la cuenta pierde ese estado o el acceso a la administración.
- «Y sus subgrupos» lo aplica también el servidor: aunque el navegador no tenga JavaScript, un grupo marcado que cuelga de uno desmarcado con esa opción se quita igualmente, y la revisión lo enseña.
- Justo antes de guardar se comprueba que los grupos de la cuenta siguen siendo los revisados; si han cambiado, no se aplica nada.
- El cambio pasa por la API de usuarios de Joomla, **cierra las sesiones abiertas** de la cuenta para que los permisos nuevos se apliquen enseguida y queda en el **registro de acciones de usuarios** con los grupos quitados y, si lo hubo, el grupo añadido.
- **Se comprueba que no quede ninguna sesión abierta.** Si alguna sobrevive, el cambio de grupos se mantiene, pero el resultado lo avisa en rojo y ofrece cerrarlas de nuevo, porque mientras siga abierta la cuenta conserva los permisos antiguos. Con «Metadatos de sesión» desactivado no se puede comprobar, y también se avisa.
- La lista de cuentas que aparece al escribir se pide al servidor con el token del formulario y el permiso de la herramienta, muestra como mucho 10 cuentas, nunca la tuya, y no cambia nada.

Requiere el permiso propio *Quitar de grupos*: los superusuarios lo tienen; a los demás hay que concedérselo en Opciones → Permisos. Se aplica a una cuenta cada vez.

## Grupos y niveles de acceso

Desde la 2026.10.9, **Grupos y niveles de acceso** muestra en una sola tabla qué niveles de acceso de visualización tiene cada grupo de usuarios. Joomla solo enseña esta relación desde los niveles (Usuarios → Niveles de acceso, columna de grupos), sin la herencia y sin una vista por grupo. Esta pantalla solo lee: no cambia grupos, niveles ni cuentas.

- **Filas**: los grupos, en su árbol, con el número de cuentas activas asignadas directamente (enlazado con el gestor de usuarios filtrado por ese grupo) y etiquetas para superusuarios, grupos con acceso a la administración y el grupo de invitados configurado en las Opciones de Usuarios.
- **Columnas**: los niveles, en su orden. Cada celda dice si el grupo tiene el nivel:
  - **Asignado**: el grupo está asignado al nivel.
  - **Heredado**: lo está uno de sus grupos padre, que se indica. Joomla concede el nivel a todos los grupos que cuelgan de un grupo asignado; por ejemplo, «Editor» tiene el nivel *Registrado* porque cuelga de «Registrado».
  - **Siempre**: el nivel *Público*, que Joomla concede a todos aunque nadie esté asignado.
  - **—**: el grupo no tiene el nivel.
- **Resumen por nivel**: grupos asignados y cuántos grupos tienen el nivel en total. Si algún nivel tiene cuentas asignadas una a una (sin grupo), aparece además la columna «Cuentas asignadas una a una»; si no hay ninguna, se oculta. Un nivel sin grupos ni cuentas se marca «Sin grupos», porque no lo tiene nadie.

Una celda marcada se lee siempre en el mismo sentido: **los miembros del grupo de la fila pueden ver el contenido del nivel de la columna**. «Heredado del grupo «Manager»» en la columna *Registrado* de la fila «Administrator» significa que los administradores ven el contenido *Registrado* porque su grupo cuelga de Manager, que está asignado a ese nivel. No significa que quien tenga el nivel *Registrado* vea lo mismo que los administradores.

![Grupos y niveles de acceso](docs/screenshots/14-grupos-niveles.jpg)

Un cuadro de búsqueda filtra los grupos. Junto a él, dos botones descargan la información en CSV (desde la 2026.10.11):

- **CSV: tabla**: la misma tabla de la pantalla, con una fila por grupo y una columna por nivel. Incluye el ID y la ruta completa de cada grupo (por ejemplo `Public / Manager / Administrator`) y las cuentas activas. Las celdas dicen «Asignado», «Heredado del grupo «…»», «Siempre» o quedan vacías.
- **CSV: una fila por nivel**: una fila por cada grupo y nivel que tiene, con las columnas ID del grupo, Grupo, Ruta, ID del nivel, Nivel de acceso, Tipo y Heredado del grupo. Es la forma más cómoda de filtrar o hacer una tabla dinámica.

Los dos ficheros están en UTF-8 con BOM y separados por punto y coma, para que Excel los abra con las tildes bien, y están protegidos contra fórmulas. Siempre incluyen todos los grupos, aunque haya un filtro escrito. Los textos van en el idioma de la administración. La descarga requiere el mismo permiso que la pantalla y el token del formulario.

Los títulos de grupos y niveles enlazan con su edición en Joomla para quien tiene el permiso de administrar Usuarios (el mismo que exige Joomla para esas pantallas), y las cifras de cuentas enlazan con el gestor de usuarios para quien puede gestionar usuarios. La pantalla requiere el permiso propio *Ver grupos, niveles y contenido por nivel*: los superusuarios lo tienen; a los demás hay que concedérselo en Opciones → Permisos.

## Contenido por nivel de acceso

Desde la 2026.10.12, **Contenido por nivel de acceso** cuenta qué elementos usa cada nivel de acceso. Joomla solo permite verlo listado a listado, con el filtro «Acceso» de cada uno, o intentando borrar el nivel, que dice en qué tablas se usa pero no qué elementos ni cuántos. Esta pantalla solo lee.

- **Filas**: los niveles, en su orden, con el total de elementos de cada uno al final. Un cuadro de búsqueda filtra los niveles, útil si hay muchos.
- **Columnas**: los tipos de elemento que tienen algún nivel asignado:
  - artículos;
  - categorías de cada componente;
  - campos y grupos de campos de cada contexto;
  - elementos de menú del sitio;
  - módulos del sitio y de la administración;
  - plugins, contactos, canales de noticias, etiquetas, idiomas de contenido y visitas guiadas.
- **Enlaces**: cada cifra enlaza al listado de Joomla ya filtrado por ese nivel, si puedes gestionar ese componente. Por ejemplo, «Categorías: Artículos» con el nivel *Socios* abre Contenido → Categorías con el filtro de acceso puesto.
- **Otras extensiones** (adjuntos, galerías…): sus tablas se detectan igual que lo hace Joomla antes de borrar un nivel, por tener una columna `access`. Aparecen como «Tabla #__nombre» y se cuentan enteras y sin enlace, porque User Tools no sabe qué significa cada fila. El índice de Búsqueda inteligente no se cuenta, porque repite los elementos anteriores.
- **Avisos**: si hay elementos con un nivel que no existe (por ejemplo, borrado directamente en la base de datos), aparecen en una fila «ID n (no existe)» y un aviso pide asignarles un nivel existente, porque nadie los ve en el sitio. Los niveles que no usa ningún elemento salen atenuados, con total 0, y se listan también debajo de la tabla.

No se cuentan los elementos en la papelera. Los listados de Joomla ocultan además los archivados salvo que cambies su filtro de estado, así que el listado puede mostrar menos elementos que la cifra. El botón **Exportar CSV** descarga la misma tabla, con una fila por nivel y su ID (UTF-8 con BOM y punto y coma). Usa el mismo permiso que «Grupos y niveles de acceso», que ahora se llama *Ver grupos, niveles y contenido por nivel*, y se llega desde la portada o con el botón «Ver contenido por nivel» de esa pantalla.

![Contenido por nivel de acceso](docs/screenshots/18-contenido-por-nivel.jpg)

## Seguridad

- **Los superusuarios están siempre protegidos**, en todas las herramientas y aunque se desactive «Excluir administradores». User Tools no bloquea ni elimina cuentas de superusuario.
- **La cuenta que ejecuta una operación manual siempre queda protegida.**
- **Cada bloqueo o cambio de grupos cierra las sesiones abiertas** de la cuenta, como hace el gestor de usuarios de Joomla (requiere «Metadatos de sesión», activado por defecto).
- **Las altas, los bloqueos, las eliminaciones y los cambios de grupo quedan en el registro de acciones de usuarios** (Usuarios → Registro de acciones de usuarios): quién bloqueó, creó o eliminó cada cuenta y con qué herramienta, o qué tarea programada lo hizo.
- **Permisos propios**, delegables sin dar superusuario (Opciones → Permisos):
  - *Comparar lista y bloquear*, *Crear cuentas*, *Revisar usuarios inactivos*, *Eliminar usuarios inactivos*, *Ver resultados de tareas*, *Quitar de grupos* y *Ver grupos, niveles y contenido por nivel*.
  - Quien no es superusuario nunca puede bloquear, eliminar ni quitar de grupos cuentas con acceso a la administración.
- **Confirmación reforzada configurable**: a partir de N cuentas (10 por defecto) hay que escribir el número exacto de cuentas afectadas, además de marcar la casilla.
- **El borrado exige siempre escribir el número de cuentas**, aunque solo se elimine una; el umbral configurable no desactiva esta confirmación.
- **El módulo de grupos del sitio solo lee**: nunca muestra correos ni cuentas bloqueadas, y por defecto oculta las cuentas con acceso a la administración (ver [módulo de grupos](#módulo-de-grupos-de-usuarios-en-el-sitio)).
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

Consulta el [mapa de arquitectura](docs/arquitectura.md) para seguir los flujos, localizar responsabilidades, entender la persistencia y elegir las pruebas relacionadas con cada cambio.

Estructura:

- `admin/`: componente con controladores, modelos, vistas MVC, plantillas y ficheros de idioma es-ES y en-GB.
- `admin/src/Service/`: lógica de dominio compartida con el plugin.
- `media/`: CSS y JS cargados con el gestor de recursos web (`joomla.asset.json`).
- `plugins/task/usertools/`: plugin de tareas.
- `modules/mod_usertools/`: módulo de resumen del panel de Usuarios.
- `modules/mod_usertools_groups/`: módulo del sitio que lista grupos y miembros.
- `package/`: manifiesto del paquete, script de instalación e idiomas.

Pruebas:

~~~sh
composer install && npm ci
vendor/bin/phpunit                    # pruebas unitarias de los servicios
npm test                              # comportamiento del JavaScript (jsdom)
JOOMLA_PATH=/ruta/a/joomla-6 vendor/bin/phpstan analyse   # análisis estático, nivel 6
~~~

La prueba de extremo a extremo `tests/integration/run.php` recorre todos los flujos contra un Joomla 6 real y desechable: comparación, altas por correo hasta elegir la contraseña y entrar, contraseñas temporales, permisos delegados, revisión y eliminación de inactivos, tarea con límite de seguridad, informes, CSV, registro de acciones y el módulo de grupos del sitio. Instrucciones de uso en la cabecera del fichero; usa `tests/integration/smtp-sink.php` como servidor de correo de pruebas.

GitHub Actions ejecuta en cada push y pull request las pruebas unitarias (PHP 8.3 y 8.4), JavaScript, PHPStan y la prueba de extremo a extremo con PostgreSQL y con MySQL.

## Construcción y publicación

La numeración sigue **año.mes.revisión**, sin cero inicial en el mes: 2026.10.0, 2026.10.1; el siguiente mes empieza en 2026.11.0. Las etiquetas llevan el prefijo v.

~~~sh
php scripts/build.php --output=dist                     # versión del manifiesto
php scripts/build.php --version=v2026.10.5 --output=dist
php tests/verify-build.php 2026.10.5 dist
~~~

Genera los cinco ZIP instalables (`pkg_usertools`, `com_usertools`, `plg_task_usertools`, `mod_usertools` y `mod_usertools_groups`), `SHA256SUMS.txt` y `updates.xml`. En Windows también vale `scripts\build.ps1`, que llama al mismo script.

Para publicar, crea una release en GitHub con una etiqueta **vAAAA.M.REVISIÓN** sobre `main` (Releases → Draft a new release → Choose a tag → Publish release). GitHub Actions pasa las pruebas, genera los paquetes con esa versión en los manifiestos y los adjunta a la release junto con `SHA256SUMS.txt` y `updates.xml`. Las instalaciones existentes ven la actualización a través del servidor de actualizaciones. No se admiten sufijos de preversión.

## Referencias

Implementación propia basada en las APIs de Joomla 6 y en el patrón de los plugins oficiales de tareas, como [DeleteActionLogs](https://github.com/joomla/joomla-cms/tree/6.0.0/plugins/task/deleteactionlogs).

Proyecto independiente, sin afiliación oficial a Joomla.
