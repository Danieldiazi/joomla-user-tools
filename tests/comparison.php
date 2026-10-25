<?php
require __DIR__ . '/../admin/src/Service/Comparison.php';
use Danieldiazi\Component\Usertools\Administrator\Service\Comparison;
function check($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
$users = [
    (object)['id'=>1, 'username'=>'admin', 'block'=>0],
    (object)['id'=>2, 'username'=>'ana', 'block'=>0],
    (object)['id'=>3, 'username'=>'baja', 'block'=>0],
    (object)['id'=>4, 'username'=>'servicio', 'block'=>0],
    (object)['id'=>5, 'username'=>'bloqueado', 'block'=>1],
];
check(Comparison::parse("\xEF\xBB\xBFana\r\n ana\n\n0\n") === ['ana', '0'], 'BOM, duplicados, CRLF y nombre numérico');
$r = Comparison::compare($users, ['ana', 'desconocido'], ['servicio'], [1]);
check(array_column($r['candidates'], 'id') === [3], 'Solo bloquear ausentes no protegidos');
check($r['matched'] === 1 && $r['unknown'] === ['desconocido'], 'Coincidencias y desconocidos');
check($r['protected'] === 2 && $r['blocked'] === 1, 'Exclusiones y ya bloqueados');
check(Comparison::compare($users, [], [], [])['candidates'] === [], 'Lista vacía');
$none = Comparison::compare($users, ['AJENO'], ['servicio'], [1]);
check($none['matched'] === 0 && array_column($none['candidates'], 'id') === [2, 3], 'Sin coincidencias: se proponen las no protegidas');
check(count(Comparison::parse(implode("\n", array_map(fn($i) => 'usuario'.$i, range(1, 1800))))) === 1800, '1800 usuarios');
try { Comparison::parse("\xFF"); throw new RuntimeException('UTF-8 inválido aceptado'); } catch (InvalidArgumentException $e) {}
echo "OK: comparación, protecciones, listas vacías y 1800 usuarios\n";
