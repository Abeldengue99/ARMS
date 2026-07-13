# Script PowerShell para tentar corrigir double-encoding em ficheiros de texto
param(
    [string]$Root = "C:\xampp\htdocs\ARMS — Aksanti Request Management System"
)

$exts = '*.html','*.htm','*.js','*.php','*.css','*.md','*.json'
$fixed = @()

Get-ChildItem -Path $Root -Recurse -Include $exts -File | ForEach-Object {
    $path = $_.FullName
    try {
        $bytes = [System.IO.File]::ReadAllBytes($path)
    } catch {
        Write-Output "Read error: $path"
        return
    }

    # If bytes are valid UTF8, skip
    $utf8 = $true
    try {
        [System.Text.Encoding]::UTF8.GetString($bytes) | Out-Null
    } catch {
        $utf8 = $false
    }
    if ($utf8) { continue }

    # Attempt single-byte CP1252 -> UTF8
    $cp1252 = [System.Text.Encoding]::GetEncoding(1252).GetString($bytes)

    # Attempt double-fix: interpret cp1252 string as latin1 bytes then decode as UTF8
    $latin1Bytes = [System.Text.Encoding]::GetEncoding(28591).GetBytes($cp1252)
    try {
        $double = [System.Text.Encoding]::UTF8.GetString($latin1Bytes)
    } catch {
        $double = $null
    }

    # scoring function: count suspicious characters
    function Score([string]$s) {
        return ($s -split '').Where({$_ -match '[ÃÂ\uFFFD�]' }) | Measure-Object | Select-Object -ExpandProperty Count
    }

    $origText = [System.Text.Encoding]::Default.GetString($bytes)
    $origScore = Score $origText
    $cpScore = Score $cp1252
    $doubleScore = if ($double) { Score $double } else { 9999 }

    # choose best (lowest score)
    $best = $origText; $bestScore=$origScore
    if ($cpScore -lt $bestScore) { $best=$cp1252; $bestScore=$cpScore }
    if ($doubleScore -lt $bestScore) { $best=$double; $bestScore=$doubleScore }

    if ($bestScore -lt $origScore) {
        $bak = "$path.bak3"
        if (!(Test-Path $bak)) { Copy-Item -Path $path -Destination $bak }
        [System.IO.File]::WriteAllText($path, $best, [System.Text.Encoding]::UTF8)
        $fixed += @{path=$path; before=$origScore; after=$bestScore}
        Write-Output "Fixed: $path (score $origScore -> $bestScore)"
    }
}

Write-Output "Total fixed: $($fixed.Count)"
