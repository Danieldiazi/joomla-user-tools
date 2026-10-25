<?php
namespace Danieldiazi\Component\Usertools\Administrator\Service;
final class Comparison {
    public static function parse(string $text): array {
        if (strlen($text) > 2000000 || !preg_match('//u', $text)) {
            throw new \InvalidArgumentException('Lista demasiado grande o texto UTF-8 no válido.');
        }
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
        return array_values(array_unique(array_filter(array_map('trim', preg_split('/\R/u', $text)), fn($s) => $s !== '')));
    }
    public static function compare(array $users, array $names, array $excluded, array $protectedIds): array {
        $valid = array_fill_keys($names, true);
        $exceptions = array_fill_keys($excluded, true);
        $protected = array_fill_keys($protectedIds, true);
        $remaining = $valid;
        $result = ['candidates' => [], 'matched' => 0, 'protected' => 0, 'blocked' => 0, 'unknown' => []];
        foreach ($users as $user) {
            $name = (string) $user->username;
            if (isset($valid[$name])) { $result['matched']++; unset($remaining[$name]); }
            if (isset($exceptions[$name]) || isset($protected[(int) $user->id])) {
                $result['protected']++;
            } elseif ((int) $user->block === 1) {
                $result['blocked']++;
            } elseif (!isset($valid[$name])) {
                $result['candidates'][] = $user;
            }
        }
        $result['unknown'] = array_keys($remaining);
        if ($result['matched'] === 0) { $result['candidates'] = []; }
        return $result;
    }
}
