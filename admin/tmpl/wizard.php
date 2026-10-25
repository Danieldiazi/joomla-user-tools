<?php
defined('_JEXEC') or die;
use Danieldiazi\Component\Usertools\Administrator\Service\Exclusions;
use Danieldiazi\Component\Usertools\Administrator\Service\Registration;
use Joomla\CMS\HTML\HTMLHelper;
$e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$url = fn($s) => 'index.php?option=com_usertools&amp;step=' . $s;
$draftSettings = Exclusions::fromArray($draft['settings']);
?>
<div class="ut-app">
  <header class="ut-header"><div><span class="ut-eyebrow">ADMINISTRACIÓN DE CUENTAS</span><h1>User Tools</h1></div>
    <?php if ($step !== 'home'): ?><a class="ut-link" href="<?= $url('home') ?>">Volver al inicio</a><?php endif; ?>
  </header>
<?php if ($step === 'home'): ?>
  <p class="ut-intro">Mantén al día los accesos a tu web. Elige cómo quieres gestionar las cuentas.</p>
  <div class="ut-grid ut-tools">
    <section class="ut-card"><span class="ut-tag">MANUAL</span><h2>Comparar una lista de usuarios</h2><p>Pega los nombres que deben conservar acceso. Revisa los bloqueos y elige qué usuarios nuevos quieres añadir.</p>
      <ol><li>Pega la lista y elige las exclusiones.</li><li>Revisa los cambios, sin modificar cuentas.</li><li>Confirma y consulta el resultado.</li></ol>
      <a class="ut-button" href="<?= $url('edit') ?>">Empezar comparación</a>
    </section>
<section class="ut-card"><span class="ut-tag">MANUAL</span><h2>Revisar usuarios inactivos</h2><p>Elige un plazo y unas exclusiones propias. Revisa las cuentas sin acceso y marca solo las que quieres suspender.</p><a class="ut-button ut-secondary" href="index.php?option=com_usertools&amp;task=inactive.display">Revisar cuentas inactivas</a></section>
    <section class="ut-card"><span class="ut-tag">PROGRAMADO</span><h2>Bloquear por inactividad</h2><p>Programa el bloqueo de las cuentas que lleven un tiempo sin acceder, con su propio plazo y sus propias exclusiones.</p>
      <p class="ut-status"><?= $pluginEnabled ? 'Plugin habilitado' : ($pluginInstalled ? 'Plugin instalado, pendiente de habilitar' : 'Plugin pendiente de instalar') ?></p>
      <a class="ut-button ut-secondary" href="<?= $url('schedule') ?>">Configurar tarea programada</a>
    </section>
  </div>
  <section class="ut-card" style="margin-top:22px"><h2>Resultados de tareas</h2><p>Revisa las ejecuciones, los usuarios bloqueados y el motivo de las exclusiones.</p><a class="ut-button ut-secondary" href="<?= $url('reports') ?>">Ver resultados de tareas</a></section>
  <aside class="ut-note">Las exclusiones de cada herramienta son independientes. Tu propia cuenta siempre queda protegida en las operaciones manuales.</aside>
<?php elseif (in_array($step, ['reports', 'report'], true)): ?>
  <?php require __DIR__ . '/reports.php'; ?>
<?php elseif ($step === 'schedule'): ?>
  <section class="ut-card ut-narrow"><span class="ut-tag">BLOQUEO POR INACTIVIDAD</span><h2>Configura tu tarea programada</h2>
    <div class="ut-note"><?= $pluginEnabled ? 'El plugin está habilitado y listo para crear una tarea.' : ($pluginInstalled ? 'El plugin está instalado, pero debes habilitarlo para que aparezca en las tareas programadas.' : 'Instala el paquete completo pkg_usertools para añadir el plugin de tareas.') ?></div>
    <ol class="ut-instructions">
      <li><strong>Habilita «Tarea - User Tools».</strong><p>En Plugins, busca User Tools. Su tipo es Tarea.</p><a class="ut-button ut-secondary" href="index.php?option=com_plugins&amp;view=plugins&amp;filter%5Bsearch%5D=usertools">Abrir plugins</a></li>
      <li><strong>Crea «User Tools: bloquear usuarios inactivos».</strong><p>En Tareas programadas, pulsa Nueva y selecciona este tipo.</p><a class="ut-button ut-secondary" href="index.php?option=com_scheduler&amp;view=tasks">Abrir tareas programadas</a></li>
      <li><strong>Indica el plazo y las exclusiones.</strong><p>El valor inicial es 365 días. Puedes excluir administradores, nombres concretos y grupos, sin cambiar la comparación manual.</p></li>
      <li><strong>Prueba con la simulación y programa la frecuencia.</strong><p>Revisa el registro. Cuando los candidatos sean correctos, desactiva la simulación para aplicar los bloqueos.</p></li>
    </ol>
  </section>
<?php else: ?>
  <nav aria-label="Progreso de la comparación"><ol class="ut-steps">
  <?php foreach (['edit'=>'Lista y exclusiones','review'=>'Revisar cambios','result'=>'Resultado'] as $key=>$label): ?>
    <li<?= $step === $key ? ' aria-current="step" class="ut-current"' : '' ?>><?= $e($label) ?></li>
  <?php endforeach; ?>
  </ol></nav>
<?php if ($step === 'edit'): ?>
  <h2>¿Quién debe conservar acceso?</h2><p class="ut-intro">Pega la lista completa de nombres vigentes, uno por línea. En el siguiente paso podrás revisar los cambios.</p>
  <form method="post" action="index.php?option=com_usertools" class="ut-form" data-ut-exclusions>
    <div class="ut-grid ut-edit-grid">
      <section class="ut-card"><label for="ut-names"><strong>Lista de usuarios vigentes</strong></label><p class="ut-muted" id="ut-names-help">Puedes copiar una columna de Excel. No se distinguen mayúsculas y minúsculas.</p>
        <textarea id="ut-names" name="names" rows="10" required aria-describedby="ut-names-help" placeholder="usuario1&#10;usuario2&#10;usuario3"><?= $e($draft['text']) ?></textarea>
        <div class="ut-note">Las cuentas ausentes de esta lista se propondrán para bloqueo, salvo las excluidas. Aún no se aplicará ningún cambio.</div>
      </section>
      <section class="ut-card"><h3>Excluir de los bloqueos</h3>
        <input type="hidden" name="protect_admins" value="0">
        <label class="ut-check"><input type="checkbox" name="protect_admins" value="1" data-ut-protect-admins<?= $draftSettings->protectAdmins ? ' checked' : '' ?>> Administradores y usuarios con acceso a la administración</label>
        <label for="ut-excluded"><strong>Nombres excluidos</strong></label><textarea id="ut-excluded" name="excluded" rows="3" placeholder="Un nombre por línea"><?= $e(implode("\n",$draftSettings->names)) ?></textarea>
        <fieldset class="ut-groups"><legend>Grupos excluidos</legend><div class="ut-group-list">
        <?php foreach ($groups as $group): ?>
          <?php $manual = in_array((int)$group->id,$draftSettings->groups,true); $adminGroup = !empty($group->adminProtected); $locked = $adminGroup && $draftSettings->protectAdmins; ?>
          <div class="ut-group-option<?= $locked ? ' ut-is-protected' : '' ?>" data-ut-group-option>
            <label class="ut-check"><input id="ut-group-<?= (int)$group->id ?>" type="checkbox" name="excluded_groups[]" value="<?= (int)$group->id ?>" data-ut-group data-ut-admin-group="<?= $adminGroup ? '1' : '0' ?>" data-ut-manual="<?= $manual ? '1' : '0' ?>" aria-describedby="ut-group-note-<?= (int)$group->id ?>"<?= ($manual || $locked) ? ' checked' : '' ?><?= $locked ? ' disabled' : '' ?>><?= $e($group->title) ?></label>
            <input type="hidden" name="excluded_groups[]" value="<?= (int)$group->id ?>" data-ut-group-preserve<?= ($manual && $locked) ? '' : ' disabled' ?>>
            <small id="ut-group-note-<?= (int)$group->id ?>" data-ut-group-note<?= $locked ? '' : ' hidden' ?>>Protegido por la exclusión de administradores</small>
          </div>
        <?php endforeach; ?>
        </div></fieldset><p class="ut-muted">Se incluyen los subgrupos. Estas exclusiones se guardan al continuar.</p>
      </section>
    </div>
    <footer class="ut-actions"><button class="ut-button ut-secondary" name="operation" value="save_exclusions" formnovalidate>Guardar exclusiones</button>
      <button class="ut-button" name="operation" value="compare">Siguiente: revisar cambios →</button></footer>
    <?= HTMLHelper::_('form.token') ?>
  </form>
<?php elseif ($step === 'review'): ?>
  <h2>Revisa antes de aplicar</h2><p class="ut-intro">Comprueba las bajas y selecciona las altas que necesitas. Puedes volver atrás sin perder tus datos.</p>
  <div class="ut-stats">
    <?php foreach (['Coincidencias'=>$comparison['matched'],'Protegidos'=>$comparison['protected'],'Se bloquearán'=>count($comparison['candidates']),'Nombres nuevos'=>count($comparison['unknown'])] as $label=>$number): ?>
    <div class="ut-card"><strong><?= (int)$number ?></strong><span><?= $e($label) ?></span></div>
    <?php endforeach; ?>
  </div>
  <form method="post" action="index.php?option=com_usertools" data-ut-review class="ut-form">
    <input type="hidden" name="preview" value="<?= $e($preview['nonce']) ?>">
    <input type="hidden" name="registrations" data-ut-registrations value="{}">
    <input type="hidden" name="review_ready" data-ut-ready value="0">
    <section class="ut-card"><div class="ut-section-heading"><h3>Cuentas que se bloquearán</h3><span class="ut-count"><?= count($comparison['candidates']) ?></span></div>
      <?php if (!$comparison['matched']): ?><p class="ut-note">No hay coincidencias con Joomla: se han desactivado los bloqueos. Puedes seleccionar altas de usuarios nuevos.</p><?php endif; ?>
      <?php if ($comparison['candidates']): ?><div class="ut-table-scroll"><table class="ut-table"><thead><tr><th>Nombre de usuario</th><th>Acción</th></tr></thead><tbody>
        <?php foreach ($comparison['candidates'] as $user): ?><tr><td><?= $e($user->username) ?></td><td><span class="ut-tag ut-danger-tag">Bloquear acceso</span></td></tr><?php endforeach; ?>
      </tbody></table></div><?php else: ?><p class="ut-muted">No se bloqueará ninguna cuenta.</p><?php endif; ?>
    </section>
    <section class="ut-card"><div class="ut-section-heading"><h3>Usuarios nuevos que puedes añadir</h3><span class="ut-count"><?= count($comparison['unknown']) ?></span></div>
      <p>Marca solo las cuentas que quieres crear e indica su correo. Se añadirán al grupo <strong>Registrados</strong>, con una contraseña distinta para cada una y cambio obligatorio en el primer acceso.</p>
      <?php if ($comparison['unknown']): ?><div class="ut-table-scroll"><table class="ut-table"><thead><tr><th>Añadir</th><th>Nombre de usuario</th><th>Correo electrónico</th></tr></thead><tbody>
      <?php foreach ($comparison['unknown'] as $name): $key=Registration::key($name); $checked=(string)($draft['checked'][$key]??'0')==='1'; ?>
        <tr data-ut-user="<?= $e($key) ?>"><td><input type="checkbox" data-ut-add aria-label="Añadir <?= $e($name) ?>"<?= $checked ? ' checked' : '' ?>></td>
          <td><?= $e($name) ?></td><td><input type="email" data-ut-email aria-label="Correo de <?= $e($name) ?>" value="<?= $e($draft['emails'][$key]??'') ?>" placeholder="nombre@ejemplo.com"<?= $checked ? ' required' : ' disabled' ?>></td></tr>
      <?php endforeach; ?>
      </tbody></table></div><p class="ut-muted">Las contraseñas se mostrarán una sola vez en el resultado. La herramienta no enviará correos de bienvenida.</p>
      <?php else: ?><p class="ut-muted">Todos los nombres de la lista ya existen en Joomla.</p><?php endif; ?>
    </section>
    <noscript><p class="ut-note">Activa JavaScript para seleccionar y guardar las altas de esta revisión.</p></noscript>
    <div class="ut-confirm"><label class="ut-check"><input type="checkbox" name="confirm" value="1" required> He revisado los bloqueos y las altas seleccionadas y quiero aplicar estos cambios.</label></div>
    <footer class="ut-actions"><button class="ut-button ut-secondary" name="operation" value="back" formnovalidate>← Volver y editar</button><button class="ut-button ut-danger" name="operation" value="apply">Confirmar y aplicar cambios</button></footer>
    <?= HTMLHelper::_('form.token') ?>
  </form>
<?php elseif ($step === 'result'): ?>
  <h2>Operación finalizada</h2><p class="ut-intro">Estos son los cambios que se han aplicado.</p>
  <div class="ut-stats">
    <?php foreach (['Cuentas bloqueadas'=>count($result['blocked']),'Cuentas creadas'=>count($result['created']),'Errores'=>count($result['errors'])] as $label=>$number): ?>
      <div class="ut-card"><strong><?= (int)$number ?></strong><span><?= $e($label) ?></span></div>
    <?php endforeach; ?>
  </div>
  <?php if ($result['created']): ?><section class="ut-card"><h3>Accesos de las cuentas nuevas</h3><p class="ut-note">Copia las contraseñas ahora. No volverán a mostrarse al salir o recargar esta página.</p><div class="ut-table-scroll"><table class="ut-table"><thead><tr><th>Usuario</th><th>Correo</th><th>Contraseña temporal</th></tr></thead><tbody>
  <?php foreach ($result['created'] as $row): ?><tr><td><?= $e($row['username']) ?></td><td><?= $e($row['email']) ?></td><td><code><?= $e($row['password']??'Ya mostrada. Utiliza la recuperación de contraseña de Joomla.') ?></code></td></tr><?php endforeach; ?>
  </tbody></table></div></section><?php endif; ?>
  <?php if ($result['blocked']): ?><section class="ut-card"><details><summary>Cuentas bloqueadas (<?= count($result['blocked']) ?>)</summary><ul><?php foreach ($result['blocked'] as $name): ?><li><?= $e($name) ?></li><?php endforeach; ?></ul></details></section><?php endif; ?>
  <?php if ($result['errors']): ?><section class="ut-card"><h3>Cambios que no se pudieron aplicar</h3><ul><?php foreach ($result['errors'] as $row): ?><li><strong><?= $e($row['username']) ?></strong>: <?= $e($row['message']) ?></li><?php endforeach; ?></ul></section><?php endif; ?>
  <footer class="ut-actions"><a class="ut-button ut-secondary" href="<?= $url('home') ?>">Volver al inicio</a><a class="ut-button" href="<?= $url('edit') ?>">Nueva comparación</a></footer>
<?php endif; endif; ?>
</div>
