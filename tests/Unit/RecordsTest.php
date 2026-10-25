<?php

namespace Danieldiazi\Tests;

use Danieldiazi\Component\Usertools\Administrator\Service\CsvExport;
use Danieldiazi\Component\Usertools\Administrator\Service\PasswordGenerator;
use Danieldiazi\Component\Usertools\Administrator\Service\Registration;
use Danieldiazi\Component\Usertools\Administrator\Service\ReportStore;
use Danieldiazi\Component\Usertools\Administrator\Service\UserFacingException;
use PHPUnit\Framework\TestCase;

final class RecordsTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/ut-reports-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->dir)) {
            rmdir($this->dir);
        }
    }

    public function testRegistrationSelectionIsValidatedAsAWhole(): void
    {
        $unknown = ['Nuevo', 'otro'];
        $key     = Registration::key('nuevo');
        $draft   = Registration::draft(json_encode([$key => ['checked' => true, 'email' => 'n@example.test']]), $unknown);
        $this->assertSame(['1'], array_values($draft['checked']));
        $selection = Registration::select($unknown, $draft['checked'], $draft['emails'], fn () => false, fn () => false);
        $this->assertSame([['username' => 'Nuevo', 'email' => 'n@example.test']], $selection);

        $cases = [
            'COM_USERTOOLS_ERROR_EMAIL_INVALID'  => [[$key => '1'], [$key => 'no-es-correo'], fn () => false],
            'COM_USERTOOLS_ERROR_EMAIL_IN_USE'   => [[$key => '1'], [$key => 'n@example.test'], fn () => true],
            'COM_USERTOOLS_ERROR_REGISTRATIONS_CHANGED' => [['otra' => '1'], [], fn () => false],
        ];
        foreach ($cases as $expected => [$checked, $emails, $exists]) {
            try {
                Registration::select($unknown, $checked, $emails, $exists, fn () => false);
                $this->fail('Accepted ' . $expected);
            } catch (UserFacingException $e) {
                $this->assertSame($expected, $e->key());
            }
        }
        $twice = [Registration::key('nuevo') => '1', Registration::key('otro') => '1'];
        $this->expectException(UserFacingException::class);
        Registration::select($unknown, $twice, [Registration::key('nuevo') => 'X@example.test', Registration::key('otro') => 'x@EXAMPLE.test'], fn () => false, fn () => false);
    }

    public function testDraftRejectsForeignKeys(): void
    {
        $this->expectException(UserFacingException::class);
        Registration::draft(json_encode(['abc' => ['checked' => true, 'email' => 'a@b.c']]), ['nuevo']);
    }

    public function testCreateModes(): void
    {
        $selection = [['username' => 'uno', 'email' => 'uno@example.test'], ['username' => 'dos', 'email' => 'dos@example.test']];
        $sent      = [];
        $email     = Registration::create($selection, fn () => Fixtures::joomlaUser(), 2, 'email', new PasswordGenerator(), function (object $u) use (&$sent) {
            $sent[] = $u->username;
            return $u->username === 'uno';
        });
        $this->assertSame(['uno', 'dos'], $sent);
        $this->assertSame([true, false], array_column($email['created'], 'mailed'));
        $this->assertArrayNotHasKey('password', $email['created'][0], 'email mode never returns a password');

        $users    = [];
        $password = Registration::create($selection, function () use (&$users) {
            return $users[] = Fixtures::joomlaUser();
        }, 2, 'password', new PasswordGenerator(), fn () => $this->fail('no mail in password mode'));
        $this->assertSame(1, $users[0]->bound['requireReset']);
        $this->assertSame([2], $users[0]->bound['groups']);
        $this->assertSame($users[0]->bound['password'], $password['created'][0]['password']);
        $this->assertNotSame($password['created'][0]['password'], $password['created'][1]['password']);

        $failed = Registration::create([$selection[0]], fn () => Fixtures::joomlaUser(0, '', 0, false), 2, 'password', new PasswordGenerator(), fn () => true);
        $this->assertSame([], $failed['created']);
        $this->assertSame('COM_USERTOOLS_ERROR_CREATE_FAILED', $failed['errors'][0]['message']);
        $this->assertStringNotContainsString('password', json_encode($failed['errors']));
    }

    public function testReportStoreFiltersPrunesAndProtectsFiles(): void
    {
        $store = new ReportStore($this->dir);
        $real  = $store->start(['task_id' => 4, 'task_title' => 'Noche', 'dry_run' => false, 'status' => 'completed', 'users' => [['id' => 1]]]);
        $dry   = $store->start(['task_id' => 6, 'task_title' => 'Prueba', 'dry_run' => true, 'status' => 'guarded']);
        $this->assertStringStartsWith("<?php die('Forbidden.'); ?>", (string) file_get_contents($this->dir . '/report-' . $real . '.php'));
        $page = $store->page(1, 20, []);
        $this->assertSame(2, $page['total']);
        $this->assertArrayNotHasKey('users', $page['items'][0], 'lists do not carry per-user rows');
        $this->assertSame([4 => 'Noche', 6 => 'Prueba'], $page['tasks']);
        $this->assertSame([$real], array_column($store->page(1, 20, ['mode' => 'real'])['items'], 'id'));
        $this->assertSame([$dry], array_column($store->page(1, 20, ['task' => 6, 'status' => 'guarded'])['items'], 'id'));
        $this->assertSame([['id' => 1]], $store->get($real)['users']);

        $old = '20200101000000-' . str_repeat('a', 24);
        $store->save($old, ['task_id' => 1]);
        $this->assertSame(1, $store->prune(90));
        $this->assertNull($store->get($old));
        $this->assertSame(0, $store->prune(0), '0 keeps everything');
        $this->expectException(UserFacingException::class);
        $store->get('../../configuration');
    }

    public function testCsvProtectsAgainstFormulas(): void
    {
        $csv = CsvExport::build(['Usuario', 'Motivo'], [['=HYPERLINK("x")', 'ok;sí'], ['+1', '@a'], ['-2', "\tb"]]);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString("'+1;'@a", $csv);
        $this->assertStringContainsString('"ok;sí"', $csv);
        $this->assertSame('ana', CsvExport::cell('ana'));
    }
}
