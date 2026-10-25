param(
    [string]$OutputDirectory = (Join-Path $PSScriptRoot '..\dist'),
    [string]$Version = ''
)
$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression.FileSystem
$root = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$out = [IO.Path]::GetFullPath($OutputDirectory)
if ([string]::IsNullOrWhiteSpace($Version)) {
    $manifest = [xml][IO.File]::ReadAllText((Join-Path $root 'com_usertools.xml'))
    $Version = [string]$manifest.extension.version
}
if ($Version -notmatch '^v?((0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*))$') {
    throw 'La versión debe tener el formato X.Y.Z o vX.Y.Z, por ejemplo v0.2.1.'
}
$Version = $Matches[1]
[IO.Directory]::CreateDirectory($out) | Out-Null
function New-UsertoolsZip([string]$Path, [hashtable]$Entries) {
    $stream = [IO.File]::Open($Path, [IO.FileMode]::Create)
    $archive = [IO.Compression.ZipArchive]::new($stream, [IO.Compression.ZipArchiveMode]::Create)
    try {
        foreach ($name in ($Entries.Keys | Sort-Object)) {
            if ($name -in @('com_usertools.xml', 'usertools.xml', 'pkg_usertools.xml')) {
                # Stamp only the archive: tracked source files remain unchanged.
                $xml = [xml][IO.File]::ReadAllText($Entries[$name])
                $xml.extension.version = $Version
                $entry = $archive.CreateEntry($name)
                $writer = [IO.StreamWriter]::new($entry.Open(), [Text.UTF8Encoding]::new($false))
                try { $writer.Write($xml.OuterXml) } finally { $writer.Dispose() }
            } else {
                [IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archive, $Entries[$name], $name) | Out-Null
            }
        }
    } finally { $archive.Dispose(); $stream.Dispose() }
}
$component = @{ 'com_usertools.xml' = (Join-Path $root 'com_usertools.xml') }
foreach ($file in [IO.Directory]::GetFiles((Join-Path $root 'admin'), '*', [IO.SearchOption]::AllDirectories)) {
    $component[$file.Substring($root.Length + 1).Replace('\', '/')] = $file
}
$pluginRoot = Join-Path $root 'plugins/task/usertools'
$plugin = @{}
foreach ($file in [IO.Directory]::GetFiles($pluginRoot, '*', [IO.SearchOption]::AllDirectories)) {
    $plugin[$file.Substring($pluginRoot.Length + 1).Replace('\', '/')] = $file
}
$componentZip = Join-Path $out "com_usertools-$Version.zip"
$pluginZip = Join-Path $out "plg_task_usertools-$Version.zip"
$packageZip = Join-Path $out "pkg_usertools-$Version.zip"
New-UsertoolsZip $componentZip $component
New-UsertoolsZip $pluginZip $plugin
New-UsertoolsZip $packageZip @{
    'pkg_usertools.xml' = (Join-Path $root 'package/pkg_usertools.xml')
    'packages/com_usertools.zip' = $componentZip
    'packages/plg_task_usertools.zip' = $pluginZip
}
$checksums = @($componentZip, $pluginZip, $packageZip) | ForEach-Object {
    $hash = (Get-FileHash -LiteralPath $_ -Algorithm SHA256).Hash.ToLowerInvariant()
    "$hash  $([IO.Path]::GetFileName($_))"
}
[IO.File]::WriteAllText((Join-Path $out 'SHA256SUMS.txt'), (($checksums -join [char]10) + [char]10), [Text.UTF8Encoding]::new($false))
Write-Output "Paquetes $Version creados en $out"
