param([string]$OutputDirectory = (Join-Path $PSScriptRoot '..\dist'))
$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression.FileSystem
$root = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$out = [IO.Path]::GetFullPath($OutputDirectory)
[IO.Directory]::CreateDirectory($out) | Out-Null
function New-UsertoolsZip([string]$Path, [hashtable]$Entries) {
    $stream = [IO.File]::Open($Path, [IO.FileMode]::Create)
    $archive = [IO.Compression.ZipArchive]::new($stream, [IO.Compression.ZipArchiveMode]::Create)
    try {
        foreach ($name in ($Entries.Keys | Sort-Object)) {
            [IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archive, $Entries[$name], $name) | Out-Null
        }
    } finally { $archive.Dispose(); $stream.Dispose() }
}
$component = @{ 'com_usertools.xml' = "$root\com_usertools.xml" }
foreach ($file in [IO.Directory]::GetFiles("$root\admin", '*', [IO.SearchOption]::AllDirectories)) {
    $component[$file.Substring($root.Length + 1).Replace('\', '/')] = $file
}
$pluginRoot = "$root\plugins\task\usertools"
$plugin = @{}
foreach ($file in [IO.Directory]::GetFiles($pluginRoot, '*', [IO.SearchOption]::AllDirectories)) {
    $plugin[$file.Substring($pluginRoot.Length + 1).Replace('\', '/')] = $file
}
New-UsertoolsZip "$out\com_usertools-0.2.0.zip" $component
New-UsertoolsZip "$out\plg_task_usertools-0.2.0.zip" $plugin
New-UsertoolsZip "$out\pkg_usertools-0.2.0.zip" @{
    'pkg_usertools.xml' = "$root\package\pkg_usertools.xml"
    'packages/com_usertools.zip' = "$out\com_usertools-0.2.0.zip"
    'packages/plg_task_usertools.zip' = "$out\plg_task_usertools-0.2.0.zip"
}
Write-Output "Paquetes creados en $out"
