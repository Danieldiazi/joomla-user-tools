<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

declare(strict_types=1);

/**
 * Builds the Joomla changelog (changelog.xml) of a release from the pull
 * requests merged since the previous release.
 *
 * Joomla shows, for an update or an installed extension, only the
 * <changelog> entry whose version equals that version, and prints each
 * <item> unescaped after splitting it at XML entities. So every item is
 * plain text without markup, ampersands or angle brackets.
 *
 * The entries of the previous release's changelog are kept after the new
 * one, so the published file is cumulative. Not part of the distributed
 * packages: it only runs in the release workflow and in the unit tests.
 */
final class ReleaseChangelog
{
    /** Pull request label => Joomla changelog section; unlabelled pull requests are changes. */
    public const SECTIONS = [
        'security'      => 'security',
        'bug'           => 'fix',
        'enhancement'   => 'addition',
        'language'      => 'language',
        'documentation' => 'note',
    ];

    /**
     * Conventional-commit prefix of a title => section; null leaves the pull
     * request out because it changes nothing an administrator sees.
     */
    public const PREFIXES = [
        'feat'     => 'addition',
        'fix'      => 'fix',
        'docs'     => 'note',
        'refactor' => null,
        'test'     => null,
        'tests'    => null,
        'ci'       => null,
        'build'    => null,
        'chore'    => null,
    ];

    /** Joomla's order of sections inside an entry. */
    private const ORDER = ['security', 'fix', 'addition', 'change', 'remove', 'language', 'note'];

    /**
     * @param  string  $version   Version being released, with or without the leading «v».
     * @param  list<array{number: int, title: string, body?: ?string, labels?: list<string>}>  $pulls
     *         Pull requests merged since the previous release, oldest first.
     * @param  string|null  $previous  changelog.xml of the previous release, if it had one.
     */
    public static function build(string $version, array $pulls, ?string $previous = null): string
    {
        $version  = ltrim($version, 'v');
        $sections = array_fill_keys(self::ORDER, []);

        foreach ($pulls as $pull) {
            $text = self::text($pull);
            if ($text === null) {
                continue;
            }
            [$prefix, $text] = self::prefix($text);
            if ($prefix === false) {
                continue;
            }
            $section = self::section($pull['labels'] ?? [], $prefix);
            $sections[$section][] = $text . ' (#' . (int) $pull['number'] . ')';
        }

        if (!array_filter($sections)) {
            // Joomla would show an empty changelog; say what the release is.
            $sections['note'][] = 'Maintenance release without user-facing changes.';
        }

        $xml = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<changelogs>\n" . self::entry($version, $sections);
        foreach (self::previousEntries($previous, $version) as $entry) {
            $xml .= '  ' . $entry . "\n";
        }

        return $xml . "</changelogs>\n";
    }

    /**
     * The changelog line of a pull request: its title, or the text of a
     * «Changelog:» line in its description, which also accepts «none» to leave
     * it out. Release preparation pull requests are left out.
     *
     * @param array{number: int, title: string, body?: ?string, labels?: list<string>} $pull
     */
    public static function text(array $pull): ?string
    {
        $text = (string) $pull['title'];
        if (preg_match('/^\s*Changelog:\s*(.+?)\s*$/mi', (string) ($pull['body'] ?? ''), $line)) {
            $text = $line[1];
            if (strcasecmp($text, 'none') === 0) {
                return null;
            }
        } elseif (preg_match('/^Release v?\d{4}\.\d+\.\d+$/i', trim($text))) {
            return null;
        }

        $text = str_replace('&', 'and', $text);
        $text = trim((string) preg_replace(['/[<>]/', '/\s+/'], ['', ' '], $text));

        return $text === '' ? null : $text;
    }

    /**
     * Splits a conventional-commit prefix («docs: …», «fix(csv): …») off a title.
     *
     * @return array{0: string|null|false, 1: string}  The prefix's section (null
     *         without a known prefix, false to leave the pull request out) and
     *         the text without the prefix.
     */
    public static function prefix(string $text): array
    {
        if (!preg_match('/^([a-z]+)(?:\([^)]*\))?!?:\s*(.+)$/i', $text, $match) || !array_key_exists(strtolower($match[1]), self::PREFIXES)) {
            return [null, $text];
        }
        $section = self::PREFIXES[strtolower($match[1])];

        return [$section ?? false, mb_strtoupper(mb_substr($match[2], 0, 1)) . mb_substr($match[2], 1)];
    }

    /**
     * Labels decide first; then the title prefix; otherwise it is a change.
     *
     * @param list<string> $labels
     */
    public static function section(array $labels, ?string $prefix = null): string
    {
        foreach (self::SECTIONS as $label => $section) {
            if (in_array($label, $labels, true)) {
                return $section;
            }
        }

        return $prefix ?? 'change';
    }

    /** @param array<string, list<string>> $sections */
    private static function entry(string $version, array $sections): string
    {
        $entry = "  <changelog>\n    <element>pkg_usertools</element>\n    <type>package</type>\n"
            . '    <version>' . htmlspecialchars($version, ENT_XML1) . "</version>\n";
        foreach ($sections as $name => $items) {
            if (!$items) {
                continue;
            }
            $entry .= "    <$name>\n";
            foreach ($items as $item) {
                // text() already removed every character that would need escaping.
                $entry .= "      <item>$item</item>\n";
            }
            $entry .= "    </$name>\n";
        }

        return $entry . "  </changelog>\n";
    }

    /**
     * Entries of the previous changelog, except one for the version being
     * released (a re-run replaces it instead of duplicating it).
     *
     * @return list<string>
     */
    private static function previousEntries(?string $previous, string $version): array
    {
        if ($previous === null || trim($previous) === '') {
            return [];
        }
        $errors   = libxml_use_internal_errors(true);
        $document = simplexml_load_string($previous);
        libxml_clear_errors();
        libxml_use_internal_errors($errors);
        if ($document === false) {
            return [];
        }
        $entries = [];
        foreach ($document->changelog as $entry) {
            if ((string) $entry->version !== $version) {
                $entries[] = (string) $entry->asXML();
            }
        }

        return $entries;
    }
}
