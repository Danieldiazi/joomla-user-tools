<?php
namespace Danieldiazi\Component\Usertools\Administrator\Service;

final class Registration
{
    public static function key(string $name): string
    {
        return hash('sha256', $name);
    }

    public static function draft(string $json, array $unknown): array
    {
        if (strlen($json) > 2000000) { throw new \InvalidArgumentException('La selección es demasiado grande.'); }
        try { $rows = json_decode($json, true, 32, JSON_THROW_ON_ERROR); }
        catch (\JsonException $e) { throw new \InvalidArgumentException('No se pudo leer la selección de altas.'); }
        if (!is_array($rows)) { throw new \InvalidArgumentException('Selección de altas no válida.'); }
        $allowed = array_fill_keys(array_map([self::class, 'key'], $unknown), true);
        $draft = ['checked' => [], 'emails' => []];
        foreach ($rows as $key => $row) {
            if (!isset($allowed[$key]) || !is_array($row) || !is_string($row['email'] ?? null)) {
                throw new \InvalidArgumentException('La selección no coincide con la revisión.');
            }
            if (($row['checked'] ?? false) === true) { $draft['checked'][$key] = '1'; }
            $draft['emails'][$key] = $row['email'];
        }
        return $draft;
    }

    /** Validate the whole selection before either blocking or creating accounts. */
    public static function select(array $unknown, array $checked, array $emails, callable $emailExists, callable $usernameExists): array
    {
        $allowed = [];
        foreach ($unknown as $name) { $allowed[self::key((string) $name)] = (string) $name; }
        $selection = [];
        $used = [];
        foreach ($checked as $key => $value) {
            if ((string) $value !== '1') { continue; }
            if (!isset($allowed[$key])) { throw new \InvalidArgumentException('La selección ha cambiado. Revisa los usuarios nuevos.'); }
            $name = $allowed[$key];
            $email = trim((string) ($emails[$key] ?? ''));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException('Indica un correo válido para ' . $name . '.');
            }
            $normalised = strtolower($email);
            if (isset($used[$normalised]) || $emailExists($email)) {
                throw new \InvalidArgumentException('El correo de ' . $name . ' ya está en uso.');
            }
            if ($usernameExists($name)) {
                throw new \InvalidArgumentException('El usuario ' . $name . ' ya existe. Vuelve a comparar.');
            }
            $used[$normalised] = true;
            $selection[] = ['username' => $name, 'email' => $email];
        }
        return $selection;
    }

    public static function create(array $selection, callable $newUser, int $groupId, int $minimumLength = 24): array
    {
        $result = ['created' => [], 'errors' => []];
        $seenPasswords = [];
        foreach ($selection as $row) {
            // Random material plus all character categories, even with strict Joomla policies.
            $length = max(24, min(128, $minimumLength));
            do {
                $password = substr(bin2hex(random_bytes(ceil($length / 2))), 0, $length - 4) . 'aA1!';
            } while (isset($seenPasswords[$password]));
            $seenPasswords[$password] = true;
            try {
                $user = $newUser();
                $data = [
                    'id' => 0, 'username' => $row['username'], 'name' => $row['username'],
                    'email' => $row['email'], 'password' => $password, 'password2' => $password,
                    'groups' => [$groupId], 'block' => 0, 'activation' => '',
                    'requireReset' => 1, 'sendEmail' => 0,
                ];
                if (!$user->bind($data) || !$user->save()) {
                    throw new \RuntimeException('Joomla no ha podido guardar la cuenta.');
                }
                $result['created'][] = $row + ['id' => (int) $user->id, 'password' => $password];
            } catch (\Throwable $e) {
                // Do not put generated passwords or third-party plugin diagnostics in logs/results.
                $result['errors'][] = ['username' => $row['username'], 'message' => 'No se pudo crear la cuenta. Revisa sus datos y los registros de Joomla.'];
            }
        }
        return $result;
    }
}
