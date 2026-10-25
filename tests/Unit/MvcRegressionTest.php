<?php

namespace Danieldiazi\Tests;

use PHPUnit\Framework\TestCase;

final class MvcRegressionTest extends TestCase
{
    public function testControllerPermissionsAndChangedPreviews(): void
    {
        // A separate PHP process prevents the Joomla stand-ins from leaking into other tests.
        $process = proc_open([PHP_BINARY, __DIR__ . '/../regression/mvc.php'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $output . $errors);
        self::assertStringContainsString('OK:', $output);
    }
}
