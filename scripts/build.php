<?php

/**
 * Builds the installable packages:
 *   php scripts/build.php [--version=v2026.10.3] [--output=dist]
 * Creates com_usertools-VERSION.zip, plg_task_usertools-VERSION.zip, mod_usertools-VERSION.zip,
 * mod_usertools_groups-VERSION.zip, pkg_usertools-VERSION.zip,
 * SHA256SUMS.txt and updates.xml (Joomla update server). The version is stamped only inside the
 * archives; tracked sources are not modified.
 */

declare(strict_types=1);

$options = getopt('', ['version::', 'output::']);
$root    = dirname(__DIR__);
$output  = $options['output'] ?? $root . '/dist';
$version = (string) ($options['version'] ?? '');
if ($version === '') {
    $manifest = simplexml_load_file($root . '/com_usertools.xml');
    $version  = (string) $manifest->version;
}
if (!preg_match('/^v?((20[0-9]{2})\.([1-9]|1[0-2])\.(0|[1-9][0-9]*))$/D', $version, $m)) {
    fwrite(STDERR, "The version must look like 2026.10.3 or v2026.10.3.\n");
    exit(1);
}
$version = $m[1];
if (!is_dir($output) && !mkdir($output, 0777, true)) {
    fwrite(STDERR, "Cannot create $output\n");
    exit(1);
}
$output = realpath($output);

/** @return array<string, string> archive path => source path */
function collect(string $base, string $prefix = ''): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1));
            $files[$prefix . $relative] = $file->getPathname();
        }
    }
    ksort($files);
    return $files;
}

function stamp(string $path, string $version): string
{
    $content = (string) file_get_contents($path);
    if (str_ends_with($path, '.xml')) {
        return (string) preg_replace('#<version>[^<]*</version>#', '<version>' . $version . '</version>', $content, 1);
    }
    if (str_ends_with($path, 'joomla.asset.json')) {
        return (string) preg_replace('/"version":\s*"[^"]*"/', '"version": "' . $version . '"', $content, 1);
    }
    return $content;
}

/** @param array<string, string> $entries */
function zip(string $target, array $entries, string $version): void
{
    $zip = new ZipArchive();
    if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException("Cannot create $target");
    }
    ksort($entries);
    foreach ($entries as $name => $source) {
        $isManifest = in_array($name, ['com_usertools.xml', 'usertools.xml', 'mod_usertools.xml', 'mod_usertools_groups.xml', 'pkg_usertools.xml', 'media/joomla.asset.json'], true);
        if ($isManifest) {
            $zip->addFromString($name, stamp($source, $version));
        } else {
            $zip->addFile($source, $name);
        }
        // Fixed timestamps keep builds reproducible.
        $zip->setMtimeName($name, 1767225600);
    }
    $zip->close();
}

$component = ['com_usertools.xml' => $root . '/com_usertools.xml'] + collect($root . '/admin', 'admin/') + collect($root . '/media', 'media/');
$plugin    = collect($root . '/plugins/task/usertools');
$module    = collect($root . '/modules/mod_usertools');
$groups    = collect($root . '/modules/mod_usertools_groups');
$files     = [
    'com'  => $output . "/com_usertools-$version.zip",
    'plg'  => $output . "/plg_task_usertools-$version.zip",
    'mod'  => $output . "/mod_usertools-$version.zip",
    'grp'  => $output . "/mod_usertools_groups-$version.zip",
    'pkg'  => $output . "/pkg_usertools-$version.zip",
];
zip($files['com'], $component, $version);
zip($files['plg'], $plugin, $version);
zip($files['mod'], $module, $version);
zip($files['grp'], $groups, $version);
zip($files['pkg'], [
    'pkg_usertools.xml'               => $root . '/package/pkg_usertools.xml',
    'script.php'                      => $root . '/package/script.php',
    'packages/com_usertools.zip'      => $files['com'],
    'packages/plg_task_usertools.zip' => $files['plg'],
    'packages/mod_usertools.zip'      => $files['mod'],
    'packages/mod_usertools_groups.zip' => $files['grp'],
] + collect($root . '/package/language', 'language/'), $version);

$sums = '';
foreach ($files as $file) {
    $sums .= hash_file('sha256', $file) . '  ' . basename($file) . "\n";
}
file_put_contents($output . '/SHA256SUMS.txt', $sums);

$hash     = hash_file('sha256', $files['pkg']);
$download = 'https://github.com/Danieldiazi/joomla-user-tools/releases/download/v' . $version . '/' . basename($files['pkg']);
$updates  = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<updates>
  <update>
    <name>User Tools</name>
    <description>User Tools $version</description>
    <element>pkg_usertools</element>
    <type>package</type>
    <client>site</client>
    <version>$version</version>
    <infourl title="User Tools $version">https://github.com/Danieldiazi/joomla-user-tools/releases/tag/v$version</infourl>
    <downloads>
      <downloadurl type="full" format="zip">$download</downloadurl>
    </downloads>
    <sha256>$hash</sha256>
    <tags><tag>stable</tag></tags>
    <maintainer>Danieldiazi</maintainer>
    <maintainerurl>https://github.com/Danieldiazi/joomla-user-tools</maintainerurl>
    <targetplatform name="joomla" version="6\.[0-9]+" />
    <php_minimum>8.3</php_minimum>
  </update>
</updates>

XML;
file_put_contents($output . '/updates.xml', $updates);
echo "Packages $version created in $output\n";
