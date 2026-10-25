<?php
defined('_JEXEC') or die;
$states = ['running'=>'Sin finalizar','completed'=>'Completada','failed'=>'Con errores','unreadable'=>'No legible'];
$actions = ['blocked'=>'Bloqueado','simulated'=>'Se bloquearía (simulación)','protected'=>'Protegido','omitted'=>'Omitido','error'=>'Error'];
?>
<h2>Resultados de tareas</h2>
<p class="ut-intro">Consulta las ejecuciones registradas desde esta actualización, sin acceder a archivos del servidor. Las fechas se muestran en UTC.</p>
<?php if ($reportError): ?><div class="ut-note"><?= $e($reportError) ?></div>
<?php elseif ($step === 'reports'): ?>
  <?php if (!$reportPage['items']): ?><section class="ut-card"><h3>Aún no hay resultados</h3><p>Ejecuta una tarea de tipo «User Tools: bloquear usuarios inactivos». El informe se guardará aunque el registro individual de Joomla esté desactivado.</p><p>Las ejecuciones anteriores a esta actualización no aparecen aquí.</p><a class="ut-button ut-secondary" href="index.php?option=com_scheduler&amp;view=tasks">Abrir tareas programadas</a></section>
  <?php else: ?><section class="ut-card"><div class="ut-table-scroll"><table class="ut-table"><thead><tr><th>Fecha UTC</th><th>Tarea</th><th>Modo</th><th>Estado</th><th>Candidatos</th><th>Bloqueados</th><th>Errores</th><th>Detalle</th></tr></thead><tbody>
  <?php foreach ($reportPage['items'] as $item): ?>
    <tr><td><?= $e($item['started_at']??'—') ?></td><td><?= $e($item['task_title']??'User Tools') ?> <small>#<?= (int)($item['task_id']??0) ?></small></td><td><?= !empty($item['dry_run']) ? 'Simulación' : 'Real' ?></td><td><?= $e($states[$item['status']]??'Desconocido') ?></td><td><?= (int)($item['candidates']??0) ?></td><td><?= (int)($item['blocked']??0) ?></td><td><?= (int)($item['errors']??0) ?></td><td><a href="index.php?option=com_usertools&amp;step=report&amp;report_id=<?= $e($item['id']) ?>">Ver informe</a></td></tr>
  <?php endforeach; ?>
  </tbody></table></div></section>
  <footer class="ut-actions"><span><?= (int)$reportPage['total'] ?> ejecuciones · Página <?= (int)$reportPageNumber ?></span><div>
    <?php if ($reportPageNumber>1): ?><a class="ut-button ut-secondary" href="index.php?option=com_usertools&amp;step=reports&amp;page=<?= $reportPageNumber-1 ?>">Anterior</a><?php endif; ?>
    <?php if ($reportPageNumber*20<$reportPage['total']): ?><a class="ut-button ut-secondary" href="index.php?option=com_usertools&amp;step=reports&amp;page=<?= $reportPageNumber+1 ?>">Siguiente</a><?php endif; ?>
  </div></footer>
  <?php endif; ?>
<?php elseif ($taskReport): ?>
  <section class="ut-card"><div class="ut-section-heading"><h3><?= $e($taskReport['task_title']) ?> · Tarea #<?= (int)$taskReport['task_id'] ?></h3><span class="ut-tag"><?= !empty($taskReport['dry_run']) ? 'SIMULACIÓN' : 'EJECUCIÓN REAL' ?></span></div>
    <p><strong><?= $e($states[$taskReport['status']]??'Desconocido') ?></strong> · Inicio: <?= $e($taskReport['started_at']) ?> UTC · Fin: <?= $e($taskReport['finished_at']??'Sin finalizar') ?></p>
    <p>Plazo: <?= $e($taskReport['days']) ?> días · Fecha límite: <?= $e($taskReport['cutoff']??'No calculada') ?> UTC · Incluir usuarios que nunca accedieron: <?= !empty($taskReport['include_never']) ? 'Sí' : 'No' ?></p>
    <?php if ($taskReport['status']==='running'): ?><p class="ut-note">Esta ejecución no ha registrado su finalización. Puede seguir en curso o haberse interrumpido; los datos mostrados son parciales.</p><?php endif; ?>
    <?php if (!empty($taskReport['message'])): ?><p class="ut-note"><?= $e($taskReport['message']) ?></p><?php endif; ?>
  </section>
  <div class="ut-stats">
  <?php foreach (['Candidatos'=>'candidates','Bloqueados'=>'blocked','Protegidos'=>'protected','Omitidos'=>'omitted','Errores'=>'errors'] as $label=>$key): ?>
    <div class="ut-card"><strong><?= (int)($taskReport[$key]??0) ?></strong><span><?= $e($label) ?></span></div>
  <?php endforeach; ?>
  </div>
  <section class="ut-card"><h3>Detalle por usuario</h3>
    <?php if (!$taskReport['users']): ?><p class="ut-muted">No hay usuarios procesados en este informe.</p>
    <?php else: ?><div class="ut-table-scroll"><table class="ut-table"><thead><tr><th>Usuario</th><th>ID</th><th>Resultado</th><th>Motivo</th><th>Último acceso UTC</th></tr></thead><tbody>
    <?php foreach ($taskReport['users'] as $row): ?><tr><td><?= $e($row['username']) ?></td><td><?= (int)$row['id'] ?></td><td><?= $e($actions[$row['action']]??'Desconocido') ?></td><td><?= $e($row['reason']) ?></td><td><?= !$row['last_visit'] || str_starts_with($row['last_visit'],'0000-00-00') ? 'Nunca' : $e($row['last_visit']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
  </section>
<?php endif; ?>
<footer class="ut-actions"><a class="ut-button ut-secondary" href="index.php?option=com_usertools&amp;step=reports">Actualizar lista de resultados</a><a class="ut-button ut-secondary" href="index.php?option=com_scheduler&amp;view=tasks">Abrir tareas programadas</a></footer>
