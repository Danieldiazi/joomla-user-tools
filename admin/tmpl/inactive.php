<?php
defined('_JEXEC') or die;
use Joomla\CMS\HTML\HTMLHelper;
$e=fn($s)=>htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
?>
<div class="ut-app">
<header class="ut-header"><div><span class="ut-eyebrow">REVISIÓN MANUAL POR INACTIVIDAD</span><h1>User Tools</h1></div><a class="ut-link" href="index.php?option=com_usertools">Volver al inicio</a></header>
<nav aria-label="Progreso"><ol class="ut-steps"><?php foreach(['setup'=>'Plazo y exclusiones','review'=>'Seleccionar cuentas','result'=>'Resultado'] as $key=>$label): ?><li<?= $stage===$key ? ' class="ut-current" aria-current="step"' : '' ?>><?= $e($label) ?></li><?php endforeach; ?></ol></nav>
<?php if($stage==='setup'): ?>
<h2>¿Qué cuentas quieres revisar?</h2><p class="ut-intro">Busca usuarios que hayan superado el plazo sin acceder y elige después cuáles suspender. Esta herramienta tiene sus propias exclusiones.</p>
<form method="post" action="index.php?option=com_usertools&amp;task=inactive.display" data-ut-exclusions>
<div class="ut-grid ut-edit-grid">
<section class="ut-card"><h3>Periodo de inactividad</h3><label for="inactive-days">Días sin acceder</label><input class="form-control" id="inactive-days" type="number" name="days" value="<?= (int)$days ?>" min="1" max="36500" required>
<p class="ut-muted">365 días por defecto. Se comparará con una fecha límite calculada en UTC.</p>
<label class="ut-check"><input type="checkbox" name="include_never" value="1"<?= $includeNever?' checked':'' ?>> Incluir usuarios que nunca han accedido</label>
<p class="ut-muted">Si lo activas, el plazo se cuenta desde su registro. Las cuentas recientes no se propondrán para suspensión.</p>
<div class="ut-note">Buscar no modifica cuentas. Las que tengan una sesión reciente registrada o estén ya bloqueadas se omiten.</div></section>
<section class="ut-card"><h3>Exclusiones de esta revisión</h3><input type="hidden" name="protect_admins" value="0">
<label class="ut-check"><input type="checkbox" name="protect_admins" value="1" data-ut-protect-admins<?= $settings->protectAdmins?' checked':'' ?>> Excluir administradores</label>
<label for="inactive-excluded">Nombres excluidos, uno por línea</label><textarea id="inactive-excluded" name="excluded" rows="3"><?= $e(implode("\n",$settings->names)) ?></textarea>
<fieldset class="ut-groups"><legend>Grupos excluidos</legend><div class="ut-group-list">
<?php foreach($groups as $group): $manual=in_array((int)$group->id,$settings->groups,true);$adminGroup=!empty($group->adminProtected);$locked=$adminGroup&&$settings->protectAdmins; ?>
<div class="ut-group-option<?= $locked?' ut-is-protected':'' ?>" data-ut-group-option>
<label class="ut-check"><input type="checkbox" name="excluded_groups[]" value="<?= (int)$group->id ?>" data-ut-group data-ut-admin-group="<?= $adminGroup?'1':'0' ?>" data-ut-manual="<?= $manual?'1':'0' ?>"<?= ($manual||$locked)?' checked':'' ?><?= $locked?' disabled':'' ?>><?= $e($group->title) ?></label>
<input type="hidden" name="excluded_groups[]" value="<?= (int)$group->id ?>" data-ut-group-preserve<?= ($manual&&$locked)?'':' disabled' ?>>
<small data-ut-group-note<?= $locked?'':' hidden' ?>>Protegido por la exclusión de administradores</small></div>
<?php endforeach; ?></div></fieldset><p class="ut-muted">Se incluyen los subgrupos. Tu propia cuenta siempre queda protegida.</p></section></div>
<footer class="ut-actions"><a class="ut-button ut-secondary" href="index.php?option=com_usertools">Cancelar</a><button class="ut-button" name="operation" value="search">Buscar cuentas inactivas →</button></footer><?= HTMLHelper::_('form.token') ?></form>
<?php elseif($stage==='review'): ?>
<h2>Elige qué cuentas suspender</h2><p class="ut-intro">Se han encontrado <?= count($candidates) ?> candidatos. No se suspenderá ninguna cuenta que no marques.</p>
<p class="ut-note">Fecha límite: <?= $e($preview['cutoff']) ?> UTC. Las cuentas se comprobarán otra vez al confirmar.</p>
<form method="post" action="index.php?option=com_usertools&amp;task=inactive.display" data-ut-suspension-form>
<input type="hidden" name="preview" value="<?= $e($preview['nonce']) ?>"><input type="hidden" name="selected" value="[]" data-ut-selected><input type="hidden" name="selection_ready" value="0" data-ut-selection-ready>
<section class="ut-card">
<?php if($candidates): ?><label class="ut-check"><input type="checkbox" data-ut-suspend-all> Seleccionar todos los candidatos</label>
<div class="ut-table-scroll"><table class="ut-table"><thead><tr><th>Suspender</th><th>Usuario</th><th>Último acceso UTC</th><th>Registro UTC</th></tr></thead><tbody>
<?php foreach($candidates as $user): ?><tr><td><input type="checkbox" data-ut-suspend-id="<?= (int)$user->id ?>" aria-label="Suspender <?= $e($user->username) ?>"<?= in_array((int)$user->id,$preview['selected'],true)?' checked':'' ?>></td><td><?= $e($user->username) ?></td><td><?= !$user->lastvisitDate||str_starts_with($user->lastvisitDate,'0000-00-00')?'Nunca':$e($user->lastvisitDate) ?></td><td><?= $e($user->registerDate) ?></td></tr><?php endforeach; ?></tbody></table></div>
<?php else: ?><p>No hay cuentas que cumplan el plazo y las exclusiones configuradas.</p><?php endif; ?>
<p class="ut-muted" data-ut-suspension-count aria-live="polite">0 cuentas seleccionadas</p></section>
<?php if($candidates): ?><div class="ut-confirm"><label class="ut-check"><input type="checkbox" name="confirm" value="1" required> Confirmo la suspensión de las cuentas seleccionadas. Sus datos se conservarán.</label></div><?php endif; ?>
<noscript><p class="ut-note">Activa JavaScript para guardar la selección.</p></noscript>
<footer class="ut-actions"><button class="ut-button ut-secondary" name="operation" value="back" formnovalidate>← Volver y editar</button><?php if($candidates): ?><button class="ut-button ut-danger" name="operation" value="suspend" data-ut-suspend-submit disabled>Suspender seleccionados</button><?php endif; ?></footer><?= HTMLHelper::_('form.token') ?></form>
<?php else: ?>
<h2>Revisión finalizada</h2><div class="ut-stats"><?php foreach(['Cuentas suspendidas'=>count($result['blocked']),'Omitidas tras revalidar'=>$result['skipped'],'Errores'=>count($result['errors'])] as $label=>$count): ?><div class="ut-card"><strong><?= (int)$count ?></strong><span><?= $e($label) ?></span></div><?php endforeach; ?></div>
<?php if($result['blocked']): ?><section class="ut-card"><h3>Cuentas suspendidas</h3><ul><?php foreach($result['blocked'] as $name): ?><li><?= $e($name) ?></li><?php endforeach; ?></ul></section><?php endif; ?>
<?php if($result['errors']): ?><section class="ut-card"><h3>No se pudieron suspender</h3><ul><?php foreach($result['errors'] as $name): ?><li><?= $e($name) ?></li><?php endforeach; ?></ul></section><?php endif; ?>
<footer class="ut-actions"><a class="ut-button ut-secondary" href="index.php?option=com_usertools">Volver al inicio</a><a class="ut-button" href="index.php?option=com_usertools&amp;task=inactive.display">Nueva revisión</a></footer>
<?php endif; ?></div>
