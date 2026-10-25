<?php
require __DIR__ . '/../admin/src/Service/Comparison.php';
require __DIR__ . '/../admin/src/Service/Exclusions.php';
require __DIR__ . '/../admin/src/Service/Registration.php';
use Danieldiazi\Component\Usertools\Administrator\Service\Comparison;
use Danieldiazi\Component\Usertools\Administrator\Service\Exclusions;
use Danieldiazi\Component\Usertools\Administrator\Service\Registration;
function check($ok, $why) { if (!$ok) { throw new RuntimeException($why); } }
$users = [
    (object)['id'=>1,'username'=>'OTRO','block'=>0],
    (object)['id'=>2,'username'=>'Baja','block'=>0],
];
$r = Comparison::compare($users, ['otro'], [], []);
check($r['matched'] === 1 && $r['unknown'] === [], 'otro recognises existing OTRO, no proposed registration');
check(array_column($r['candidates'],'id') === [2], 'OTRO is not blocked when otro is current');
check(Comparison::contains(['otro'], 'OTRO'), 'Final revalidation protects the same current account');
$r = Comparison::compare($users, ['otro','NUEVO','nuevo'], ['BAJA'], []);
check(!$r['candidates'] && $r['unknown'] === ['NUEVO'], 'Exclusions ignore case; unknown spelling preserved once');
check(Comparison::parse(" Otro\nOTRO\notro\nÁNGEL\nángel\n0") === ['Otro','ÁNGEL','0'], 'Case-insensitive deduplication, Unicode, numeric username');
$u = new class {
    public $id=5; public $username='SERVICIO';
    public function authorise($s) { return false; }
    public function getAuthorisedGroups() { return []; }
};
check(Exclusions::fromArray(['excluded_names'=>'servicio'])->protects($u), 'Shared manual/task exclusions ignore case');
check(Registration::key('Nuevo') === Registration::key('NUEVO'), 'Stable selection across casing edits');
$unicode = Comparison::compare([(object)['id'=>3,'username'=>'ÁNGEL','block'=>0]], ['ángel'], [], []);
check($unicode['matched'] === 1 && !$unicode['unknown'], 'Unicode case comparison');
echo "OK: OTRO/otro, no duplicate creation or incorrect blocking, exclusions, duplicates and Unicode\n";
