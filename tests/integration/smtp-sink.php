<?php

/**
 * Minimal SMTP server for tests: stores every message as a .eml file.
 *   php tests/integration/smtp-sink.php 2525 /tmp/mails
 */

declare(strict_types=1);

$port = (int) ($argv[1] ?? 2525);
$dir  = $argv[2] ?? sys_get_temp_dir() . '/usertools-mails';
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}
$server = stream_socket_server('tcp://127.0.0.1:' . $port, $errno, $error);
if (!$server) {
    fwrite(STDERR, "Cannot listen on $port: $error\n");
    exit(1);
}
while ($client = stream_socket_accept($server, -1)) {
    $send = fn (string $line) => fwrite($client, $line . "\r\n");
    $send('220 usertools-test ESMTP');
    $data   = null;
    $buffer = '';
    while (($line = fgets($client)) !== false) {
        if ($data !== null) {
            if (rtrim($line, "\r\n") === '.') {
                file_put_contents($dir . '/' . microtime(true) . '-' . bin2hex(random_bytes(3)) . '.eml', $buffer);
                $data   = null;
                $buffer = '';
                $send('250 OK queued');
                continue;
            }
            $buffer .= str_starts_with($line, '..') ? substr($line, 1) : $line;
            continue;
        }
        $command = strtoupper(substr(trim($line), 0, 4));
        match ($command) {
            'EHLO' => $send("250-usertools-test\r\n250 8BITMIME"),
            'HELO' => $send('250 usertools-test'),
            'DATA' => ($data = '') === '' ? $send('354 End data with <CR><LF>.<CR><LF>') : null,
            'QUIT' => $send('221 Bye'),
            default => $send('250 OK'),
        };
        if ($command === 'QUIT') {
            break;
        }
    }
    fclose($client);
}
