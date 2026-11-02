<?php

/**
 * @package     UserTools
 * @copyright   Copyright (C) 2026 Danieldiazi
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

/**
 * Writes the Joomla changelog of a release (see ReleaseChangelog):
 *   php scripts/changelog.php v2026.11.2 pulls.json [previous-changelog.xml] > dist/changelog.xml
 *
 * pulls.json is a JSON list of {number, title, body, labels[]}, as collected
 * by .github/workflows/release.yml.
 */

declare(strict_types=1);

require __DIR__ . '/ReleaseChangelog.php';

[$version, $pullsFile, $previousFile] = [$argv[1] ?? '', $argv[2] ?? '', $argv[3] ?? ''];
if ($version === '' || !is_file($pullsFile)) {
    fwrite(STDERR, "Usage: php scripts/changelog.php VERSION pulls.json [previous-changelog.xml]\n");
    exit(1);
}

$pulls = json_decode((string) file_get_contents($pullsFile), true, 512, JSON_THROW_ON_ERROR);
$previous = $previousFile !== '' && is_file($previousFile) ? (string) file_get_contents($previousFile) : null;

echo ReleaseChangelog::build($version, $pulls, $previous);
