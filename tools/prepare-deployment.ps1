param([string]$OutputDirectory = '.local/deployment-hardening')
$ErrorActionPreference = 'Stop'
$projectRoot = [IO.Path]::GetFullPath((Split-Path -Parent $PSScriptRoot))
$outputRoot = [IO.Path]::GetFullPath((Join-Path $projectRoot $OutputDirectory))
$allowedRoot = Join-Path $projectRoot '.local'
if (-not $outputRoot.StartsWith($allowedRoot + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) {
    throw 'Deployment output must stay under the ignored project .local directory.'
}
$uploadRoot = Join-Path $outputRoot 'upload'
$runtimeFiles = @('.htaccess','checkout.php','contact.php','login.php','register.php','style.css',
    'includes/bootstrap.php','includes/demo.php','includes/footer.php','includes/header.php')
$base = 'd6adb518ba9a39ee4d324cf326fbdc3f4c7b471e'
Push-Location $projectRoot
try {
    $changed = @(git -c "safe.directory=$projectRoot" diff --name-only $base)
    if ($LASTEXITCODE -ne 0) { throw 'Cannot inspect the release diff.' }
    $changed += @(git -c "safe.directory=$projectRoot" ls-files --others --exclude-standard)
    if ($LASTEXITCODE -ne 0) { throw 'Cannot inspect new files.' }
    $runtimeChanged = @($changed | Where-Object {
        ($_ -match '\.(php|css|js)$' -or $_ -eq '.htaccess') -and $_ -notmatch '^(tests|tools|docs|database)/'
    } | Sort-Object -Unique)
    $unexpected = @($runtimeChanged | Where-Object { $_ -notin $runtimeFiles })
    if ($unexpected.Count) { throw ('Review additional runtime changes before packaging: ' + ($unexpected -join ', ')) }
    if (Test-Path -LiteralPath $uploadRoot) {
        foreach ($existing in Get-ChildItem -LiteralPath $uploadRoot -Recurse -File -Force) {
            $relative = $existing.FullName.Substring($uploadRoot.Length + 1).Replace('\','/')
            if ($relative -notin $runtimeFiles) { throw 'Unexpected file in upload directory; review it manually before proceeding.' }
        }
    }
    $manifest = @()
    foreach ($relative in $runtimeFiles) {
        $source = Join-Path $projectRoot $relative
        $destination = Join-Path $uploadRoot $relative
        if (-not (Test-Path -LiteralPath $source -PathType Leaf)) { throw "Missing runtime file: $relative" }
        New-Item -ItemType Directory -Force -Path (Split-Path -Parent $destination) | Out-Null
        # Match the committed LF text required by .gitattributes, even when a
        # Windows editor leaves CRLF in the working copy.
        $runtimeText = [IO.File]::ReadAllText($source).Replace("`r`n", "`n")
        [IO.File]::WriteAllText($destination, $runtimeText, [Text.UTF8Encoding]::new($false))
        $manifest += [ordered]@{ path=$relative; sha256=(Get-FileHash -Algorithm SHA256 -LiteralPath $destination).Hash.ToLower(); bytes=(Get-Item -LiteralPath $destination).Length }
    }
    [ordered]@{ based_on=$base; status='Local candidate; not deployed'; files=$manifest } |
        ConvertTo-Json -Depth 5 | Set-Content -Encoding utf8 (Join-Path $outputRoot 'manifest.json')
    Write-Output "Prepared $($manifest.Count) runtime files in $uploadRoot"
    Write-Output 'Upload only the contents of upload/. Keep the manifest locally. No configuration, state, data or SQL files are included.'
} finally { Pop-Location }
