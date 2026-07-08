$ErrorActionPreference = 'Stop'

$repoRoot = Split-Path -Parent $PSScriptRoot
$source = Join-Path $repoRoot 'tools\windows-helper\EraportDapodikBridgeHelper.cs'
$output = Join-Path $repoRoot 'public\downloads\eraport-dapodik-bridge-helper-base.exe'

$candidates = @()
$pathCommand = Get-Command csc.exe -ErrorAction SilentlyContinue
if ($pathCommand) {
    $candidates += $pathCommand.Source
}

if ($env:WINDIR) {
    $candidates += Join-Path $env:WINDIR 'Microsoft.NET\Framework64\v4.0.30319\csc.exe'
    $candidates += Join-Path $env:WINDIR 'Microsoft.NET\Framework\v4.0.30319\csc.exe'
}

$compiler = $candidates | Where-Object { $_ -and (Test-Path $_) } | Select-Object -First 1
if (-not $compiler) {
    throw 'csc.exe tidak ditemukan. Install .NET Framework Developer Pack atau Visual Studio Build Tools.'
}

if (-not (Test-Path $source)) {
    throw "Source helper tidak ditemukan: $source"
}

$outputDir = Split-Path -Parent $output
if (-not (Test-Path $outputDir)) {
    New-Item -ItemType Directory -Path $outputDir | Out-Null
}

Write-Host "Compiler : $compiler"
Write-Host "Source   : $source"
Write-Host "Output   : $output"

& $compiler `
    /nologo `
    /target:winexe `
    /optimize+ `
    "/out:$output" `
    /reference:System.Windows.Forms.dll `
    /reference:System.Drawing.dll `
    /reference:System.Web.Extensions.dll `
    "$source"

if ($LASTEXITCODE -ne 0) {
    throw "Build helper gagal dengan exit code $LASTEXITCODE"
}

$bytes = [IO.File]::ReadAllBytes($output)
$unicodeText = [Text.Encoding]::Unicode.GetString($bytes)
$requiredChecks = [ordered]@{
    Authorization = $unicodeText.Contains('Authorization')
    Bearer = $unicodeText.Contains('Bearer')
    GetMataPelajaran = $unicodeText.Contains('getMataPelajaran')
    GetAnggotaRombel = $unicodeText.Contains('getAnggotaRombel')
    GetPembelajaran = $unicodeText.Contains('getPembelajaran')
    PortableConfig = $unicodeText.Contains('eraport-bridge-config.txt')
    OldMapelEndpointRemoved = -not $unicodeText.Contains('getReferensiMataPelajaran')
}

$failedChecks = $requiredChecks.GetEnumerator() | Where-Object { -not $_.Value } | ForEach-Object { $_.Key }
if ($failedChecks) {
    throw 'Build selesai, tapi validasi binary gagal: ' + ($failedChecks -join ', ')
}

$hash = Get-FileHash -Path $output -Algorithm SHA256
Write-Host 'Validasi : OK'
Write-Host "Size     : $($bytes.Length) bytes"
Write-Host "SHA256   : $($hash.Hash)"
