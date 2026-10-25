<?php
require __DIR__ . '/../admin/src/Service/Comparison.php';
require __DIR__ . '/../admin/src/Service/Exclusions.php';
require __DIR__ . '/../plugins/task/usertools/src/Service/InactivityPolicy.php';

use Danieldiazi\Component\Usertools\Administrator\Service\Exclusions;
use Danieldiazi\Plugin\Task\Usertools\Service\InactivityPolicy;

function expect(bool $value, string $message): void {
    if (!$value) { throw new RuntimeException($message); }
}
class PolicyUser {
    public function __construct(public int $id = 10, public string $username = 'ana',
        public int $block = 0, public ?string $lastvisitDate = '2024-01-01 00:00:00',
        public string $registerDate = '2023-01-01 00:00:00',
        public bool $admin = false, public array $groups = [1, 2]) {}
    public function authorise($permission): bool { return $this->admin; }
    public function getAuthorisedGroups(): array { return $this->groups; }
}

$now = new DateTimeImmutable('2026-10-25 12:00:00', new DateTimeZone('UTC'));
$cutoff = InactivityPolicy::cutoff(365, $now);
expect($cutoff->format('Y-m-d H:i:s') === '2025-10-25 12:00:00', '365 días exactos');
$u = new PolicyUser();
expect(InactivityPolicy::eligible($u, $cutoff, false), 'Último acceso antiguo');
$u->lastvisitDate = '2025-10-25 12:00:00';
expect(!InactivityPolicy::eligible($u, $cutoff, false), 'Igualdad no significa más de un año');
$u->lastvisitDate = '2025-10-25 11:59:59';
expect(InactivityPolicy::eligible($u, $cutoff, false), 'Un segundo más antiguo');
$u->lastvisitDate = '2026-10-24 12:00:00';
expect(!InactivityPolicy::eligible($u, $cutoff, false), 'Acceso reciente');
foreach ([null, '', '0000-00-00 00:00:00'] as $missing) {
    $u->lastvisitDate = $missing;
    expect(!InactivityPolicy::eligible($u, $cutoff, false), 'Nunca accedió: excluido por defecto');
    expect(InactivityPolicy::eligible($u, $cutoff, true), 'Nunca accedió: registro antiguo');
}
$u->registerDate = '2026-01-01 00:00:00';
expect(!InactivityPolicy::eligible($u, $cutoff, true), 'Registro reciente');
foreach (['fecha inválida', '2025-02-30 00:00:00'] as $invalid) {
    $u->lastvisitDate = $invalid;
    expect(!InactivityPolicy::eligible($u, $cutoff, true), 'Fecha inválida: no bloquear');
}
$u = new PolicyUser(block: 1);
expect(!InactivityPolicy::eligible($u, $cutoff, true), 'Ya bloqueado');
foreach ([0, -1, 36501, 'foo', '1.5'] as $bad) {
    try {
        InactivityPolicy::cutoff($bad, $now);
        throw new RuntimeException('Plazo inválido aceptado');
    } catch (InvalidArgumentException $e) {}
}

$admin = new PolicyUser(admin: true);
expect((new Exclusions())->protects($admin), 'Administración protegida por defecto');
expect(!(new Exclusions(false))->protects($admin), 'Protección administradores configurable');
expect((new Exclusions(false))->protects($admin, 10), 'El operador siempre protegido');
$u = new PolicyUser();
expect(Exclusions::fromArray(['excluded_names' => "ana\nana\n"])->protects($u), 'Lista de nombres');
expect(Exclusions::fromArray(['excluded_names' => 'ANA'])->protects($u), 'Coincidencia sin distinguir mayúsculas');
expect(Exclusions::fromArray(['excluded_groups' => ['2']])->protects($u), 'Grupo directo');
expect(Exclusions::fromArray(['excluded_groups' => ['1']])->protects($u), 'Grupo antecesor');
expect(!Exclusions::fromArray(['excluded_groups' => ['8']])->protects($u), 'Otro grupo');
$manual = Exclusions::fromArray(['excluded_names' => 'ana']);
$task = Exclusions::fromArray(['excluded_names' => 'servicio', 'protect_admins' => 0]);
expect($manual->protects($u) && !$task->protects($u), 'Configuraciones independientes');
expect(Exclusions::fromArray($manual->toArray())->toArray() === $manual->toArray(), 'Persistencia');
echo "OK: plazos, límites, fechas, exclusiones y configuración independiente\n";
