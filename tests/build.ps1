param(
    [Parameter(Mandatory = $true)][string]$Version,
    [Parameter(Mandatory = $true)][string]$PackageDirectory
)
$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression.FileSystem
$expectedVersion = $Version -replace '^v', ''
$packageDirectory = [IO.Path]::GetFullPath($PackageDirectory)
function Assert-Manifest($archive, [string]$name) {
    $entry = $archive.GetEntry($name)
    if (!$entry) { throw "Missing manifest: $name" }
    $reader = [IO.StreamReader]::new($entry.Open())
    try { $xml = [xml]$reader.ReadToEnd() } finally { $reader.Dispose() }
    if ([string]$xml.extension.version -ne $expectedVersion) { throw "Wrong version in $name" }
}
$package = [IO.Compression.ZipFile]::OpenRead((Join-Path $packageDirectory "pkg_usertools-$expectedVersion.zip"))
try {
    Assert-Manifest $package 'pkg_usertools.xml'
    foreach ($part in @(
        @{name='packages/com_usertools.zip'; manifest='com_usertools.xml'; source="com_usertools-$expectedVersion.zip"; required='admin/src/Service/Exclusions.php'},
        @{name='packages/plg_task_usertools.zip'; manifest='usertools.xml'; source="plg_task_usertools-$expectedVersion.zip"; required='forms/inactive.xml'}
    )) {
        $entry = $package.GetEntry($part.name)
        if (!$entry) { throw "Missing nested package: $($part.name)" }
        $memory = [IO.MemoryStream]::new()
        $inputStream = $entry.Open()
        try { $inputStream.CopyTo($memory) } finally { $inputStream.Dispose() }
        $nestedBytes = $memory.ToArray()
        $sourceBytes = [IO.File]::ReadAllBytes((Join-Path $packageDirectory $part.source))
        if ([Convert]::ToBase64String($nestedBytes) -ne [Convert]::ToBase64String($sourceBytes)) {
            throw 'Nested package differs from standalone package.'
        }
        $memory.Position = 0
        $nested = [IO.Compression.ZipArchive]::new($memory, [IO.Compression.ZipArchiveMode]::Read)
        try {
            Assert-Manifest $nested $part.manifest
            if (!$nested.GetEntry($part.required)) { throw 'Required installable file missing.' }
            if ($part.manifest -eq 'com_usertools.xml') {
                foreach ($required in @('admin/tmpl/wizard.php', 'admin/src/Service/Registration.php', 'media/css/admin.css', 'media/js/admin.js')) {
                    if (!$nested.GetEntry($required)) { throw "Missing wizard asset: $required" }
                }
            }
            foreach ($file in $nested.Entries) {
                if ($file.FullName -match '(^/|\\|\.\.)' -or $file.FullName -match '^(tests|scripts)/') {
                    throw "Invalid installable path: $($file.FullName)"
                }
            }
        } finally { $nested.Dispose(); $memory.Dispose() }
    }
} finally { $package.Dispose() }
$lines = [IO.File]::ReadAllLines((Join-Path $packageDirectory 'SHA256SUMS.txt'))
if ($lines.Count -ne 3) { throw 'Expected three package checksums.' }
foreach ($line in $lines) {
    if ($line -notmatch '^([0-9a-f]{64})  ([a-z0-9_.-]+\.zip)$') { throw 'Invalid checksum line.' }
    $expectedHash = $Matches[1]
    $fileName = $Matches[2]
    $actualHash = (Get-FileHash -LiteralPath (Join-Path $packageDirectory $fileName) -Algorithm SHA256).Hash.ToLowerInvariant()
    if ($actualHash -ne $expectedHash) { throw "Checksum mismatch: $fileName" }
}
Write-Output "OK: package versions, nested archives, paths and SHA256 ($expectedVersion)"
