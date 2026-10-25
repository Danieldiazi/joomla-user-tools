# Wrapper kept for Windows users: the build itself is scripts/build.php (PHP 8.3+ with the zip extension).
param(
    [string]$OutputDirectory = (Join-Path $PSScriptRoot '..\dist'),
    [string]$Version = ''
)
$ErrorActionPreference = 'Stop'
$arguments = @((Join-Path $PSScriptRoot 'build.php'), "--output=$OutputDirectory")
if ($Version) { $arguments += "--version=$Version" }
& php @arguments
if ($LASTEXITCODE -ne 0) { throw 'Build failed.' }
