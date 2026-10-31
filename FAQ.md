# Preguntas frecuentes

**Español** · [English](FAQ.en.md)

Dudas habituales sobre **Joomla User Tools**. Para la descripción completa de cada herramienta, consulta el [README](README.md).

**Índice:** [General](#general) · [Ficha de una persona](#ficha-de-una-persona) · [Comparación de listas](#comparación-de-listas) · [Altas y contraseñas](#altas-y-contraseñas) · [Inactividad y tarea programada](#inactividad-y-tarea-programada) · [Resultados e informes](#resultados-e-informes) · [Módulo de grupos en el sitio](#módulo-de-grupos-en-el-sitio) · [Quitar de grupos](#quitar-de-grupos) · [Grupos y niveles de acceso](#grupos-y-niveles-de-acceso) · [Instalación y actualización](#instalación-y-actualización)

### ¿Puedo exportar todas las cuentas con sus grupos y MFA?

Sí, con **Exportar cuentas a CSV**. Se filtra por grupo, subgrupos, estado e inactividad. El correo está desactivado por defecto y su exportación se registra. El indicador MFA no revela claves ni códigos. Se necesita el permiso `usertools.export`. Consulta el [README](README.md#exportar-cuentas-a-csv).

### ¿Por qué algunas celdas del CSV empiezan por un apóstrofo?

Para que la hoja de cálculo no ejecute como fórmula un valor que escribió otra persona, por ejemplo un nombre como `=HYPERLINK(...)`. Todas las exportaciones de User Tools añaden un apóstrofo `'` delante de las celdas que empiezan por `=`, `+`, `-` o `@` (o sus formas de ancho completo), también cuando antes hay espacios, tabulaciones, saltos de línea, caracteres de control o invisibles, y delante de las que empiezan por tabulación o salto de línea. No se quita nada: detrás del apóstrofo está el valor original completo. Los números simples, como `-5` o `+34`, no se tocan; la hoja de cálculo puede mostrarlos como número. Los puntos y coma, comillas y saltos de línea dentro de un valor van entrecomillados y nunca crean columnas ni filas nuevas.

El CSV es un formato de intercambio: si lo vuelves a importar en otro sistema, ten en cuenta ese apóstrofo. Se ha comprobado con LibreOffice Calc 24.2, con y sin la opción de recortar espacios, que ninguna celda protegida se evalúa como fórmula. Excel y Google Sheets no se han probado todavía; la protección sigue las reglas que aplican también esas aplicaciones.

## General

### ¿Borra usuarios?

Solo la herramienta **Eliminar usuarios inactivos**, y únicamente las cuentas que marques una a una, con confirmación y escribiendo el número de cuentas. El resto de herramientas solo **bloquean** (el campo «Habilitado» de Joomla), y las cuentas bloqueadas conservan todos sus datos, grupos e historial. Ninguna herramienta reactiva cuentas.

### ¿Puedo recuperar una cuenta eliminada?

No. La eliminación es definitiva. En el registro de acciones de usuarios quedan su ID, su nombre de usuario y quién la eliminó, pero no sus datos. Si dudas, bloquéala primero con la revisión de inactivos y elimínala más adelante con «Solo bloqueadas».

### ¿Qué pasa con los artículos de una cuenta eliminada?

Se conservan, como cuando se elimina un usuario desde Joomla, pero dejan de estar asociados a una cuenta existente. Si necesitas reasignarlos, hazlo antes de eliminarla.

### ¿Qué diferencia hay entre eliminar y anonimizar una cuenta?

Las dos son permanentes. **Eliminar** borra la cuenta: sus contenidos quedan sin una cuenta asociada. **Anonimizar**, desde la 2026.10.17, la conserva bloqueada, pero sustituye su nombre, usuario y correo y borra su perfil, sus notas y sus campos personalizados: los contenidos siguen apuntando a la misma cuenta, ya sin datos personales. Se pueden anonimizar las cuentas de una lista, todas las inactivas o las de un grupo. Detalles en el [README](README.md#anonimizar-cuentas).

### ¿Qué ocurre si falla la limpieza después de anonimizar una cuenta?

La cuenta **ya está anonimizada y bloqueada**, y esa anonimización consta en el registro de acciones. El resultado indicará **«Limpieza incompleta»** si quedan datos en el perfil, las notas o los campos personalizados. Para borrarlos, elige **«Solo repetir la limpieza de cuentas ya anonimizadas»**, selecciona las cuentas y confirma. Este modo no anonimiza cuentas nuevas y registra la limpieza terminada como una acción independiente.

### ¿Por qué no aparece una solicitud en Usuarios → Privacidad al anonimizar?

Porque nadie la pidió: es una decisión del administrador, no una petición de la persona. User Tools ejecuta los mismos plugins de privacidad que una solicitud de eliminación, pero no inventa la solicitud. Cada anonimización queda en el registro de acciones de usuarios con el ID de la cuenta.

### He bloqueado una cuenta por error, ¿cómo la recupero?

Si la bloqueó User Tools, usa **Deshacer bloqueos** (desde la 2026.10.19): elige la ejecución de la tarea o la revisión manual que la bloqueó, revisa y confirma. Solo se ofrecen las cuentas que siguen bloqueadas y no han cambiado desde entonces. Detalles en el [README](README.md#deshacer-bloqueos).

También puedes hacerlo desde Joomla: **Usuarios → Gestionar**, marca la cuenta y usa la acción **Desbloquear**, o pulsa el icono de la columna «Habilitado». User Tools nunca desbloquea cuentas por sí solo.

### ¿Qué cuentas no se bloquean ni se eliminan nunca?

- Los superusuarios, en todas las herramientas y aunque se desactive «Excluir administradores».
- La cuenta del usuario que ejecuta una operación manual.
- Con «Excluir administradores» activado (opción por defecto), cualquier cuenta con permiso para entrar en la administración.
- Quien no es superusuario nunca puede bloquear ni eliminar cuentas con acceso a la administración.
- Las que coincidan con los nombres o grupos excluidos de esa herramienta (los grupos incluyen sus subgrupos).

Basta con cumplir cualquiera de estas condiciones.

### ¿Las exclusiones son compartidas entre herramientas?

No. La comparación de listas, la revisión manual por inactividad, la eliminación de inactivos y **cada** tarea programada guardan sus propias exclusiones. Cambiar una no afecta a las demás.

### ¿Quién puede usarlo?

Los superusuarios y los usuarios con los permisos delegados correspondientes en **Opciones → Permisos**. Hay permisos separados para consultar fichas, explicar el acceso a contenido, comparar listas y bloquear, crear cuentas, revisar usuarios inactivos, eliminar usuarios inactivos, consultar resultados de tareas, quitar de grupos, ver grupos, niveles y contenido por nivel, ver contenido por antigüedad y ver el diagnóstico de accesos. El de eliminar no se concede a nadie más que a los superusuarios hasta que lo des expresamente. El acceso al componente también requiere `core.manage`.

### ¿Necesita JavaScript?

Sí. Las pantallas de revisión usan JavaScript para gestionar las casillas y enviar las selecciones.

### ¿Cuántos usuarios admite?

Las pruebas automáticas cubren listas de 1800 usuarios. La revisión envía las selecciones en un único campo JSON precisamente para no chocar con el límite `max_input_vars` de PHP en listas grandes.

### ¿Hay recorridos guiados de las herramientas?

Sí, de las que cambian cuentas o grupos. Se abren con el botón **Recorrido guiado**, junto a Ayuda, en la primera pantalla de cada una; solo explican y no aplican nada. Detalles en el [README](README.md#recorridos-guiados).

### ¿Por qué en «Mostrar todos los recorridos» veo recorridos de herramientas que no puedo usar?

Joomla solo limita los recorridos por nivel de acceso, no por los permisos de cada herramienta, así que esa lista muestra todos los de User Tools a quien pueda entrar en el componente. Seguir uno no da acceso: la pantalla responde con un error de permisos. Si quieres ocultarlos, cambia su nivel de acceso o despublícalos en Sistema → Recorridos guiados; las actualizaciones lo respetan.

### ¿Dónde encuentro los accesos directos a cada herramienta?

En el **panel de Usuarios**, el módulo User Tools muestra enlaces individuales a las herramientas para las que tienes permiso, además de los indicadores y del enlace para abrir el componente completo.

### ¿Puedo añadir muchos usuarios a grupos sin quitarles los actuales?

Sí. Usa **Añadir cuentas a grupos** con lista y grupo único, o pares `usuario;#ID`. Se revisan cambios y duplicados antes de confirmar, y no se modifican administradores ni se asignan grupos con acceso a la administración. Los grupos se indican por ID o ruta completa sin ambigüedad.

## Ficha de una persona

### ¿Consultar una ficha cambia algo?

No. Reúne los grupos, accesos, estado, fechas, contenido creado y actividad reciente de una cuenta. Buscar por usuario, nombre, correo o ID y abrir sus enlaces no modifica cuentas. Hace falta el permiso propio `usertools.profile`.

### ¿Qué pasa si lo que escribo coincide con varias cuentas?

No se elige ninguna por su cuenta. Si el texto es, por ejemplo, el usuario de una cuenta y el correo de otra, la ficha, la explicación de acceso y quitar de grupos muestran las coincidencias con `usuario — Nombre (correo) · ID` y esperan a que elijas una. Al elegir una sugerencia mientras escribes se usa su ID, así que dos personas con el mismo nombre no se confunden. Más detalles en [Elegir la cuenta](README.md#elegir-la-cuenta).

### ¿Puedo ver el registro de acciones con ese permiso?

No. Joomla lo reserva a superusuarios y la ficha respeta esa restricción. Cuando puedes verlo, distingue a la cuenta como autora de la acción o como afectada, ofrece hasta 50 coincidencias entre los 1000 candidatos recientes por actor o elemento y enlaza a cada entrada. No muestra mensajes originales que pudieran contener datos privados.

### ¿Por qué algunas cifras no tienen enlace al listado por autor?

Joomla permite filtrar artículos por autor, pero no ofrece ese filtro en los listados de categorías y contactos. La ficha muestra sus cifras y explica esa limitación. Cuenta todos los estados, incluida la papelera, dentro de los tipos y niveles que tienes permiso para consultar.

### ¿El enlace para bloquear aplica el filtro de inactividad?

No: has elegido expresamente una cuenta desde su ficha. El enlace solo abre la revisión de esa cuenta y no la bloquea al pulsarlo. Hay que confirmar y escribir el número desde el umbral configurado. La cuenta se vuelve a comprobar antes de guardar; si cambió, se omite sin sustituirla por otra. Las exclusiones siguen vigentes.

### ¿Por qué no aparecen enlaces para actuar sobre una cuenta?

Se necesita el permiso de la herramienta correspondiente. La propia cuenta y los superusuarios nunca tienen esos enlaces; un operador sin permisos de superusuario tampoco puede actuar sobre cuentas administrativas. El bloqueo no se ofrece si la cuenta ya está bloqueada, y se respetan las exclusiones de bloqueo y anonimización. La ficha solo muestra si hay MFA configurada, nunca sus secretos, métodos ni códigos.

### ¿Cómo averiguo por qué alguien no puede ver o editar un artículo?

Con **Explicar acceso a contenido**, también desde su ficha. Eliges la cuenta, el artículo o la categoría por su ID y si quieres comprobar ver, editar o publicar. Muestra cada condición que aplica Joomla (estado de la cuenta, niveles del artículo y de su categoría, publicación y fechas, permisos con herencia, edición de propios, flujo de trabajo) y marca todas las que fallan a la vez. No cambia nada ni inicia sesión como la cuenta. Detalles en el [README](README.md#explicar-acceso-a-contenido).

### ¿Por qué dice «Sí» y aun así la persona no llega al artículo?

La explicación se refiere al contenido. Un elemento de menú puede tener otro nivel de acceso o estar despublicado, y lo verás en la lista de elementos de menú. Además hay condiciones que la pantalla no modela y que aparecen como **no comprobado**: plugins con restricciones propias, el idioma en sitios multilingües o las condiciones de los plugins de flujo de trabajo.

### ¿Por qué no veo qué grupo tiene la denegación?

Dónde hay una denegación explícita solo lo ven los superusuarios, como la configuración de permisos de Joomla. Los demás ven el resultado y una indicación de dónde se configura. Tampoco se explica un contenido que no podrías ver tú: la respuesta es la misma que para un ID inexistente.

## Comparación de listas

### ¿Qué formato debe tener la lista?

Un nombre de usuario por línea. Sirve una columna copiada de Excel. No se admiten CSV con varias columnas. Se eliminan espacios exteriores, líneas vacías y duplicados.

### ¿Distingue mayúsculas y minúsculas?

No. `OTRO` y `otro` son la misma cuenta, tanto al comparar como en las exclusiones por nombre.

### ¿Qué pasa si pego una lista incompleta?

Todas las cuentas que falten se propondrán para bloqueo (salvo las protegidas). Por eso hay que pegar siempre la lista **completa** y revisar la pantalla de cambios antes de confirmar. Si **ningún** nombre coincide con una cuenta existente, la revisión muestra un aviso en rojo, porque probablemente se ha pegado la lista equivocada.

### ¿Se aplica algo al pulsar «Siguiente: revisar cambios»?

No. Ese paso solo guarda las exclusiones y calcula la vista previa. Los cambios se aplican únicamente al marcar la casilla de confirmación y pulsar «Confirmar y aplicar cambios».

### Me dice que la vista previa ha caducado

La vista previa dura **15 minutos** y solo se puede aplicar una vez. También se invalida si cambias las exclusiones o si cambia el conjunto de cuentas que se había propuesto bloquear. En ese caso debes volver a comparar; la lista introducida se conserva. Vuelve al primer paso (los datos se conservan con «Volver y editar») y genera una revisión nueva.

### ¿Por qué un usuario de la lista no aparece en ningún apartado?

Si existe en Joomla cuenta como coincidencia y no se toca. Las cuentas que ya estaban bloqueadas tampoco se proponen de nuevo.

## Altas y contraseñas

### ¿Se crean automáticamente los nombres nuevos de la lista?

No. Aparecen **desmarcados** en la revisión; solo se crean los que marques, cada uno con un correo distinto que no exista ya en Joomla.

### Hay muchos nombres nuevos, ¿tengo que marcarlos y escribir los correos uno a uno?

No. Desde la 2026.10.11, la casilla de la cabecera **Añadir** los marca todos, y la opción **Generar el correo a partir del nombre de usuario** rellena cada correo vacío como `usuario@dominio` con el dominio que escribas. Los correos que hayas escrito a mano no se tocan, y los nombres que no sirven como correo (con espacios o tildes) se quedan vacíos para que los completes. Detalles en el [README](README.md#altas-de-cuentas-nuevas).

### ¿En qué grupo se crean las cuentas nuevas?

En **Registrados** (grupo ID 2). La herramienta comprueba que el grupo exista y que no dé acceso a la administración. El nombre visible inicial es el propio nombre de usuario.

### ¿Se envía un correo de bienvenida?

Hay dos modos de alta. Por defecto se envía un correo con un enlace para elegir la contraseña, mediante la plantilla editable «User Tools: cuenta creada». También puedes elegir mostrar una contraseña temporal una sola vez y comunicarla tú mismo. En ambos casos se suprime el correo de bienvenida del plugin de usuarios de Joomla y después se restaura su configuración. Otros plugins siguen recibiendo los eventos normales de creación.

### He perdido la contraseña temporal

Las contraseñas se muestran **una sola vez** en la pantalla de resultado y no se guardan ni se registran en logs. Si recargas o sales, ya no se pueden recuperar: usa la recuperación de contraseña de Joomla o asigna una nueva desde **Usuarios → Gestionar**. En cualquier caso, la cuenta deberá cambiarla en su primer acceso.

### He dado de alta cientos de cuentas y ha dado error 500 a mitad

Hasta la 2026.10.10 todo se hacía en una sola petición, y con cientos de altas (cada una guarda la cuenta y envía un correo) se superaba el límite de tiempo del servidor. Las cuentas creadas hasta el corte existen, pero no quedaron anotadas en el registro de acciones. Desde la 2026.10.11 los cambios se aplican por tandas con una barra de progreso y cada alta se anota en cuanto se crea. Para terminar una lista cortada, vuelve a compararla: las cuentas ya creadas coinciden y solo aparecen como nuevas las que faltan. Más detalles en el [README](README.md#comparar-una-lista-de-usuarios).

### Se cerró la pestaña mientras se aplicaban los cambios

Lo hecho hasta entonces se conserva y está anotado. Al abrir «Comparar lista» verás un aviso con cuántas cuentas lleva y los botones **Continuar** y **Detener y descartar lo pendiente**. Al continuar, cada cuenta se vuelve a comprobar antes de cambiarla, se retoma justo donde se quedó (el avance se guarda tras cada cuenta, así que ninguna se repite) y se vuelve a comprobar que sigues teniendo permiso para crear cuentas. En el modo de contraseña temporal, las contraseñas que ya se mostraron no se pueden recuperar: usa la recuperación de contraseña de Joomla para esas cuentas.

### Una alta ha fallado y otras no, ¿se deshace todo?

Si la selección es inválida antes de empezar (correo repetido, nombre existente…), no se aplica nada. Los fallos que ocurran ya durante la aplicación aparecen en el resultado, pero los cambios que ya se hubieran hecho no se revierten.

## Inactividad y tarea programada

### ¿Qué diferencia hay entre la revisión manual y la tarea programada?

La **revisión manual** busca candidatos y tú marcas uno a uno los que se suspenden (ninguno está marcado de inicio). La **tarea programada** bloquea automáticamente todos los candidatos cada vez que se ejecuta, salvo que esté en modo simulación.

### ¿Cómo pruebo la tarea sin riesgo?

La tarea se crea con **Simulación (sin bloquear)** activada. Ejecútala y revisa en **Resultados de tareas** qué cuentas habría bloqueado. Cuando estés conforme, desactiva la simulación.

### ¿Cómo se calcula el plazo?

Se compara el último acceso con una fecha límite en **UTC**: hoy menos el número de días configurado (365 por defecto, entre 1 y 36500). El último acceso tiene que ser estrictamente anterior a ese límite. 365 días es una duración fija, no un año de calendario.

### ¿Por qué no se bloquea un usuario que nunca ha entrado?

Por defecto se excluyen las cuentas sin ningún acceso. Activa «Incluir usuarios que nunca han accedido» para tratarlas; entonces el plazo se cuenta desde su **fecha de registro**, de modo que las cuentas recién creadas tampoco se proponen.

### ¿Qué pasa con alguien que tiene la sesión abierta?

En las herramientas de inactividad, si tiene una sesión reciente en la tabla de sesiones de Joomla (dentro del tiempo de sesión configurado en la configuración global), se omite. Al bloquear una cuenta, User Tools solicita a Joomla cerrar sus sesiones abiertas. Ambas operaciones dependen de los metadatos de sesión de Joomla.

### La tarea no se ejecuta sola

Comprueba que el plugin **Tarea - User Tools** está habilitado y que la tarea tiene una regla de ejecución distinta de «Ejecución manual». El programador de Joomla depende por defecto de que haya visitas a la web; para que funcione sin tráfico, configura el cron del alojamiento según la [guía oficial de Joomla](https://guide.joomla.org/user-guide/scheduled-tasks/how-to-choose-a-scheduler).

### ¿Puedo tener varias tareas con plazos distintos?

Sí. Cada tarea programada tiene su propio plazo, simulación y exclusiones, por ejemplo una de 365 días para todos y otra más estricta solo para un grupo.

### ¿Por qué una tarea se detiene por seguridad?

El límite contra bloqueos masivos detiene la ejecución real si los candidatos superan el porcentaje configurado de cuentas activas y alcanzan el mínimo de candidatos. Por defecto: más del 20 % y al menos 10 candidatos. En simulación solo avisa. Revisa el informe y la configuración antes de cambiar esos límites.

## Resultados e informes

### ¿Dónde veo qué ha hecho una tarea?

En **Componentes → User Tools → Resultados de tareas**. Cada ejecución muestra modo (simulación o real), estado y cantidades. «Ver informe» abre el detalle de cuentas bloqueadas, simuladas, protegidas o con error, y un resumen de las omitidas por motivo. Puedes filtrar ejecuciones y exportar cada informe a CSV.

### ¿Dónde se guardan los informes y ocupan espacio?

En la subcarpeta `usertools-reports` del directorio de registros de Joomla, protegidos contra la lectura directa por web. No guardan contraseñas ni correos. Se eliminan al terminar las ejecuciones según la retención configurada en **Opciones**: 90 días por defecto; 0 los conserva siempre.

### ¿Por qué no veo ejecuciones antiguas?

Los resultados se registran desde la versión que añadió esta funcionalidad; las ejecuciones anteriores no se importan. Para esas, consulta **Sistema → Tareas programadas → Historial de ejecución** o el registro individual de la tarea, si lo tenías activado.

### ¿Hay un resumen sin entrar en el componente?

Sí, desde la 2026.10.6. El panel **Usuarios** de la administración muestra el módulo «User Tools» con las cuentas inactivas sin bloquear, las bloqueadas pendientes de eliminar, el estado de las tareas programadas y avisos (plugin deshabilitado, tarea detenida por seguridad o fallida, tarea en simulación más de 14 días…). Cada cifra y aviso enlaza con la herramienta o el informe correspondiente.

### ¿Qué significa «Bloqueadas para revisar»?

Son cuentas ya bloqueadas que cumplen el plazo y las exclusiones de la herramienta de eliminación. La cifra abre una revisión: no borra cuentas ni significa que deban eliminarse sin comprobarlas.

### ¿Por qué una tarea no muestra próxima ejecución?

El módulo distingue una tarea deshabilitada de una tarea habilitada sin fecha de próxima ejecución. Consulta su configuración para comprobar la regla de ejecución. El resultado anterior no significa que la tarea siga habilitada.

### ¿Cada cuánto se actualiza el resumen del panel?

Se guarda en caché un máximo de 10 minutos. Se recalcula antes si una herramienta o una tarea cambia cuentas, o si guardas las Opciones. La hora de los datos aparece al pie del módulo.

### ¿Cómo muevo o quito el módulo del panel?

En **Contenido → Módulos del sitio de administración**, busca «User Tools». Puedes cambiar su posición (por ejemplo, al panel principal), su título o despublicarlo. Al actualizar el paquete no se vuelve a colocar.

### ¿Por qué un usuario ve el módulo sin cifras?

El módulo solo muestra lo que su permiso permite: las cifras requieren *Revisar usuarios inactivos* o *Eliminar usuarios inactivos*, y las tareas, *Ver resultados de tareas*. Quien no puede acceder a User Tools no ve el módulo.

### Un informe aparece «sin finalizar»

La ejecución se interrumpió a mitad. El informe se guarda al empezar, cada 50 usuarios y al terminar, así que refleja lo que se llegó a procesar; los bloqueos ya aplicados permanecen.

## Módulo de grupos en el sitio

### ¿Cómo muestro en el sitio los grupos de un usuario o los miembros de un grupo?

Con el módulo «User Tools - Grupos de usuarios», incluido desde la 2026.10.9. El instalador no lo publica: créalo en Contenido → Módulos del sitio → Nuevo y elige en «Qué mostrar» entre *Grupos de un usuario* y *Miembros de un grupo*. Los detalles están en el [README](README.md#módulo-de-grupos-de-usuarios-en-el-sitio).

### ¿Por qué un invitado no ve el módulo?

En el modo *Grupos de un usuario* con *Visitante identificado*, el módulo muestra a cada persona sus propios grupos; quien no ha iniciado sesión no tiene grupos que ver, así que no aparece. Para mostrar siempre los de una misma cuenta, elige *Un usuario concreto*.

### ¿Por qué falta alguien en la lista de miembros?

Puede ser una cuenta bloqueada, que nunca se lista, o una cuenta con acceso a la administración, que se oculta mientras esté activada *Ocultar cuentas con acceso a la administración*. Si pertenece a un subgrupo, activa *Incluir subgrupos*. Y si el grupo supera el *Máximo de miembros*, el módulo indica cuántos faltan.

### ¿Expone datos personales?

Muestra nombres o nombres de usuario, nunca correos. Decide quién lo ve con el campo **Acceso** del módulo (por ejemplo, *Registrado*) y mantén ocultas las cuentas con acceso a la administración salvo que tengas un motivo para mostrarlas.

## Quitar de grupos

### ¿En qué se diferencia del lote «Quitar del grupo» de Joomla?

El lote de Joomla quita solo el grupo exacto que eliges, sin enseñarte antes los grupos de la cuenta. **Quitar de grupos** te muestra solo los grupos que tiene, te deja desmarcar varios y, con «y sus subgrupos», quita también todos los que cuelgan de ellos (hijos, nietos…). Antes de aplicar enseña qué pierde la cuenta y, al aplicar, cierra sus sesiones. Los detalles están en el [README](README.md#quitar-de-grupos).

### ¿Qué hace exactamente «y sus subgrupos»?

Aparece al desmarcar un grupo del que cuelgan otros grupos de la cuenta. Si la cuenta no está en ninguno de los grupos que cuelgan de él, no hay nada que quitar en cascada y la opción no aparece; desde la 2026.10.16, los grupos intermedios en los que la cuenta no está se dibujan sin casilla, para que se vea de qué grupo cuelga realmente cada uno. Si lo marcas, la cuenta sale también de todos los grupos que cuelgan de ese y a los que pertenece, aunque estén varios niveles por debajo. Se decide grupo a grupo: puedes usarlo en varios grupos y cada uno conserva su elección. Los grupos padre no se tocan. Sin marcarlo, la cuenta solo sale del grupo que desmarcas.

### ¿Qué pasa si la cuenta se queda sin grupos?

Se le asigna el grupo de los usuarios nuevos de las Opciones de Usuarios. Si ese grupo es uno de los que se quitan, o cuelga de uno de ellos (heredaría sus permisos), no se cambia nada: deja marcado algún grupo o asígnale antes otro en el gestor de usuarios.

### ¿Por qué no puedo elegir una cuenta o aplicar el cambio?

La herramienta explica el motivo: es tu propia cuenta; tiene acceso a la administración y no eres superusuario; es el último superusuario activo; o no has desmarcado ningún grupo.

### ¿Puedo quitar varias cuentas a la vez?

No: la herramienta trabaja con una cuenta cada vez, para que cada cambio de permisos se revise por separado.

### ¿Puedo cambiar el grupo padre de varios grupos a la vez?

Sí, desde la 2026.10.16, con **Mover grupos**, si eres superusuario. Marcas los grupos, eliges el nuevo padre y la revisión te enseña qué niveles de acceso ganan o pierden sus miembros antes de confirmar. No mueve los grupos de superusuarios ni ningún grupo cuyo movimiento cambiaría el acceso a la administración de él o de sus subgrupos: esos cambios se hacen en Usuarios → Grupos, uno a uno. Detalles en el [README](README.md#mover-grupos).

## Grupos y niveles de acceso

### ¿Cómo veo qué niveles de acceso tiene cada grupo?

Con **Grupos y niveles de acceso**, desde la 2026.10.9. Muestra una tabla con los grupos en filas y los niveles en columnas. Los detalles están en el [README](README.md#grupos-y-niveles-de-acceso).

### ¿Por qué un grupo aparece con «Heredado» si no lo he asignado al nivel?

Porque Joomla concede el nivel a todos los grupos que cuelgan de uno asignado. La celda indica de qué grupo padre viene. Para quitárselo hay que sacar del nivel a ese grupo padre o mover el grupo en el árbol; la pantalla de User Tools no cambia nada.

### «Heredado» en la columna *Público* de la fila «Administrator», ¿quiere decir que quien tiene el nivel Público ve lo mismo que los administradores?

No, al revés. Cada celda dice qué niveles tienen los miembros del grupo de la fila, es decir, qué contenido pueden ver. Esa celda significa que los administradores pueden ver el contenido *Público*, porque su grupo cuelga del grupo «Public», asignado a ese nivel. El texto de debajo («del grupo «Public»») nombra el **grupo** del que viene la herencia, no el nivel.

### ¿Puedo exportar la tabla?

Sí, desde la 2026.10.11, en CSV y en dos formas: la tabla tal como se ve (una fila por grupo y una columna por nivel) o una fila por cada grupo y nivel que tiene, más cómoda para filtrar o hacer tablas dinámicas. Se abren bien en Excel en español (UTF-8 con BOM, punto y coma). Detalles en el [README](README.md#grupos-y-niveles-de-acceso).

### No veo la columna «Cuentas asignadas una a una»

Solo aparece cuando algún nivel tiene cuentas asignadas directamente, sin grupo. Joomla no ofrece hacerlo desde su pantalla de niveles, así que lo normal es que no haya ninguna.

### ¿Cómo veo qué contenido tiene cada nivel de acceso?

Con **Contenido por nivel de acceso**, desde la 2026.10.12. Cuenta, para cada nivel, los artículos, categorías, menús, módulos, campos, plugins y demás elementos que lo usan, y cada cifra enlaza al listado de Joomla ya filtrado. Las tablas de otras extensiones con columna `access`, como las de adjuntos, se cuentan enteras. Detalles en el [README](README.md#contenido-por-nivel-de-acceso).

### ¿Puedo borrar un nivel de acceso sin romper nada?

Joomla no deja borrar un nivel que use algún elemento, incluidos los de la papelera. En «Contenido por nivel de acceso» verás qué elementos lo usan, para cambiarles el nivel antes, y bajo la tabla aparecen los niveles que no usa ningún elemento. Recuerda que un nivel también puede servir solo para dar permisos de vista por grupos sin tener contenido propio.

### ¿Por qué no veo la pantalla?

Requiere el permiso *Ver grupos, niveles y contenido por nivel*. Los superusuarios lo tienen; para los demás, concédelo en Componentes → User Tools → Opciones → Permisos.

## Contenido por antigüedad

### ¿Cómo veo los artículos que llevan años sin tocarse?

Con **Contenido por antigüedad**, desde la 2026.10.15. Muestra una tabla por años según la fecha de última modificación, y cada cifra abre la lista de esos artículos con enlace para editarlos. Cuenta también categorías de artículos, contactos y canales de noticias. Detalles en el [README](README.md#contenido-por-antigüedad).

### ¿Por qué no aparecen los módulos, los menús ni los plugins?

Porque Joomla no guarda cuándo se modificaron por última vez, y sin esa fecha no se pueden repartir por años. Solo aparecen los tipos con fecha de creación y de modificación.

### ¿Por qué se usa la fecha de modificación y no la de creación?

Porque lo que interesa es el contenido abandonado: un artículo antiguo que se revisó hace poco no lo está. Si un elemento no tiene fecha de modificación, se usa la de creación.

### ¿Cuentan los archivados y los de la papelera?

Los archivados sí, con su estado visible en la lista, porque suelen ser justo los que se quieren revisar. Los de la papelera no.

### ¿Por qué veo las cifras pero no puedo abrir la lista?

La lista muestra títulos, así que además del permiso *Ver contenido por antigüedad* hace falta poder gestionar ese tipo en Joomla (artículos y categorías en Contenido, contactos en Contactos, canales en Canales de noticias). Además, las cifras, las listas y los CSV respetan los niveles de acceso autorizados del elemento y su categoría (las categorías, solo el suyo, como en Joomla). El permiso de editar propios solo ofrece enlaces para elementos creados por el usuario actual.

## Diagnóstico de accesos

### ¿Qué revisa el diagnóstico de accesos?

Desde la 2026.10.15, cinco cosas: cuentas con acceso a la administración que llevan tiempo sin entrar, si queda un solo superusuario activo, niveles de acceso que no tiene ningún grupo ni ninguna cuenta, contenido con un nivel que no existe y grupos sin miembros. Cada aviso explica qué ha detectado, por qué importa y cómo revisarlo. Detalles en el [README](README.md#diagnóstico-de-accesos).

### ¿Corrige algo?

No. Solo lee. Las correcciones se hacen en Joomla (Usuarios, Niveles de acceso, Grupos o el listado de cada contenido) o con las herramientas de User Tools que enlaza cada aviso.

### ¿Por qué aparecen superusuarios si User Tools nunca los bloquea?

Porque una cuenta de superusuario sin uso es justo la que más conviene revisar. Las herramientas de bloqueo y eliminación los siguen protegiendo; el diagnóstico solo los señala para que decidas tú.

### ¿Cómo cambio el plazo de inactividad del diagnóstico?

En Componentes → User Tools → Opciones → **Diagnóstico**. Son 90 días por defecto y no dependen del plazo de «Revisar usuarios inactivos».

### Me aparecen grupos como Publisher, ¿es un error?

No. «Grupos sin miembros» es informativo: Joomla trae grupos que muchos sitios no usan. Sirve para localizar grupos que ya no hacen falta. Antes de borrar uno, comprueba que no se use en permisos ni en niveles de acceso.

### ¿Por qué no veo el diagnóstico?

Requiere el permiso *Ver el diagnóstico de accesos*. Los superusuarios lo tienen; para los demás, concédelo en Componentes → User Tools → Opciones → Permisos.

## Instalación y actualización

### ¿Cómo abro directamente una categoría de User Tools?

Despliega **Componentes → User Tools**: Inicio conserva la portada completa y las otras cuatro entradas abren cada categoría. Solo aparecen las categorías con herramientas que puedes usar. Los accesos del panel siguen la misma clasificación; una categoría visible no concede permisos adicionales. Consulta la [navegación por categorías](README.md#navegación-por-categorías).

### ¿Por qué falta una categoría o no queda señalada la herramienta?

Una categoría desaparece cuando no tienes permiso para ninguna de sus herramientas. La configuración de tareas exige además acceso al programador de Joomla. Si el filtrado o la categoría activa no funcionan, comprueba que **Sistema - User Tools** esté habilitado en Plugins. El paquete lo activa una vez al incorporarlo; las actualizaciones posteriores respetan que lo deshabilites. Abrir directamente una URL sigue exigiendo sus permisos.

### ¿Qué versión de Joomla necesito?

Joomla 6. Las capturas de la documentación se han tomado en Joomla 6.2.0.

### ¿Qué fichero instalo?

**pkg_usertools-VERSIÓN.zip** de la [última release](https://github.com/Danieldiazi/joomla-user-tools/releases/latest). Instala o actualiza el componente, los plugins de tareas y navegación, el módulo del panel y el módulo de grupos del sitio a la vez. Los ZIP sueltos `com_usertools`, `plg_task_usertools`, `plg_system_usertools`, `mod_usertools` y `mod_usertools_groups` son para instalaciones separadas; deben corresponder a la misma versión. El componente necesita el plugin de sistema para filtrar su menú y señalar la categoría activa.

### ¿Cómo actualizo sin perder la configuración?

Instala el paquete nuevo encima desde **Sistema → Instalar → Extensiones**. No desinstales el componente: perderías sus ajustes y exclusiones.

### ¿Cómo se numeran las versiones?

**año.mes.revisión**, sin cero inicial en el mes (2026.10.0, 2026.10.1, 2026.11.0…). Las etiquetas de GitHub llevan el prefijo `v`.

### He encontrado un error, ¿dónde lo comunico?

Abre una incidencia en [GitHub Issues](https://github.com/Danieldiazi/joomla-user-tools/issues) indicando la versión de Joomla y de la extensión, los pasos para reproducirlo y, si es posible, una captura.
