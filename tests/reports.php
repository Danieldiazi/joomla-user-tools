<?php
require __DIR__ . '/../admin/src/Service/ReportStore.php';
use Danieldiazi\Component\Usertools\Administrator\Service\ReportStore;
function check($ok, $why) { if (!$ok) { throw new RuntimeException($why); } }
$dir = __DIR__ . '/../../report-store-test-' . bin2hex(random_bytes(6));
$store = new ReportStore($dir);
check($store->page()['total'] === 0, 'Empty history without an existing directory');
$report = ['status'=>'running','task_id'=>6,'task_title'=>'Prueba','started_at'=>'2026-10-25 12:00:00',
    'dry_run'=>true,'candidates'=>0,'blocked'=>0,'errors'=>0,'users'=>[]];
$id = $store->start($report);
$path = $dir . '/report-' . $id . '.php';
check(str_starts_with(file_get_contents($path), "<?php die('Forbidden.'); ?>\n"), 'Reports protected from direct PHP requests');
$report['status']='completed';
$report['candidates']=1;
$report['users']=[['id'=>1,'username'=>'OTRO','action'=>'simulated','reason'=>'Plazo superado','last_visit'=>null]];
$store->save($id,$report);
check($store->get($id)['users'][0]['username']==='OTRO', 'Atomic update and detail retrieval');
check(!isset($store->page()['items'][0]['users']), 'List omits per-user payload');
file_put_contents($dir.'/report-'.$id.'.php.abc123.php','temporary');
check($store->page()['total']===1,'Temporary files excluded from history');
for ($i=0;$i<22;$i++) { $store->start($report); }
check(count($store->page(1)['items'])===20 && count($store->page(2)['items'])===3,'Pagination without dropping reports');
try { $store->get('../../configuration'); throw new RuntimeException('Traversal accepted'); }
catch (InvalidArgumentException $e) {}
// Render a hostile username as escaped text in the superuser-only view.
define('_JEXEC',1);
$step='report'; $taskReport=$store->get($id);
$taskReport += ['finished_at'=>'2026-10-25 12:00:01','days'=>365,'cutoff'=>'2025-10-25 12:00:00','include_never'=>false];
$taskReport['users'][0]['username']='<script>alert(1)</script>';
$reportError='';$e=fn($s)=>htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
ob_start();require __DIR__.'/../admin/tmpl/reports.php';$html=ob_get_clean();
check(!str_contains($html,'<script>alert') && str_contains($html,'&lt;script&gt;'),'Report output escapes usernames');
echo "OK: report persistence, protected files, pagination, path validation and escaped detail\n";
