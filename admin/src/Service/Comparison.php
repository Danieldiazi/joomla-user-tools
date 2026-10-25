<?php
namespace Danieldiazi\Component\Usertools\Administrator\Service;

final class Comparison
{
    public static function normalise(string $name): string
    {
        return mb_strtolower($name, 'UTF-8');
    }

    /** Preserve original spelling for display and account creation. */
    public static function index(array $names): array
    {
        $indexed = [];
        foreach ($names as $name) {
            $key = self::normalise((string) $name);
            if (!isset($indexed[$key])) { $indexed[$key] = (string) $name; }
        }
        return $indexed;
    }

    public static function contains(array $names, string $name): bool
    {
        return isset(self::index($names)[self::normalise($name)]);
    }

    public static function parse(string $text): array
    {
        if (strlen($text) > 2000000 || !preg_match('//u', $text)) {
            throw new \InvalidArgumentException('Lista demasiado grande o texto UTF-8 no válido.');
        }
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
        $names = array_filter(array_map('trim', preg_split('/\R/u', $text)), fn($s) => $s !== '');
        return array_values(self::index($names));
    }

    public static function compare(array $users, array $names, array $excluded, array $protectedIds): array
    {
        $valid = self::index($names);
        $exceptions = self::index($excluded);
        $protected = array_fill_keys($protectedIds, true);
        $remaining = $valid;
        $result = ['candidates' => [], 'matched' => 0, 'protected' => 0, 'blocked' => 0, 'unknown' => []];
        foreach ($users as $user) {
            $key = self::normalise((string) $user->username);
            if (isset($valid[$key])) { $result['matched']++; unset($remaining[$key]); }
            if (isset($exceptions[$key]) || isset($protected[(int) $user->id])) {
                $result['protected']++;
            } elseif ((int) $user->block === 1) {
                $result['blocked']++;
            } elseif (!isset($valid[$key])) {
                $result['candidates'][] = $user;
            }
        }
        $result['unknown'] = array_values($remaining);
        if ($result['matched'] === 0) { $result['candidates'] = []; }
        return $result;
    }
}
