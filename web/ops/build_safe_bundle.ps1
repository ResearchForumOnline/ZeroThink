<#
.SYNOPSIS
Archive a clean, reviewed ZeroThink Git snapshot without copying runtime state.
.DESCRIPTION
Portable replacement for the historical host-specific source bundler. This
exports committed tracked files only and refuses common private-state paths,
symlinks, dirty checkouts, existing outputs and outputs inside the repository.
These checks do not replace source/secret/license review before publication.
It does not deploy, alter services, delete files or read untracked user data.
#>
param(
    [string]$ProjectRoot = (Join-Path $PSScriptRoot '../..'),
    [Parameter(Mandatory = $true)][string]$OutputZip
)

$ErrorActionPreference = 'Stop'
$repoRoot = (& git -C $ProjectRoot rev-parse --show-toplevel)
if ($LASTEXITCODE -ne 0) { throw 'ProjectRoot must be inside a Git checkout.' }
$repoRoot = [IO.Path]::GetFullPath($repoRoot.Trim())
foreach ($anchor in @('PROVENANCE.json', 'web/local-router.php')) {
    if (-not (Test-Path -LiteralPath (Join-Path $repoRoot $anchor) -PathType Leaf)) {
        throw 'Expected the restored ZeroThink source repository.'
    }
}
$state = & git -C $repoRoot status --porcelain --untracked-files=normal
if ($LASTEXITCODE -ne 0 -or $state) { throw 'Review and commit source changes before building a snapshot.' }
$output = [IO.Path]::GetFullPath($OutputZip)
$relativeOutput = [IO.Path]::GetRelativePath($repoRoot, $output)
if (-not $relativeOutput.StartsWith('..' + [IO.Path]::DirectorySeparatorChar) -and
    -not [IO.Path]::IsPathRooted($relativeOutput)) {
    throw 'Choose an output path outside the source repository.'
}
if ([IO.Path]::GetExtension($output) -ne '.zip') { throw 'OutputZip must have the .zip extension.' }
if ((Test-Path -LiteralPath $output) -or (Test-Path -LiteralPath ($output + '.sha256'))) {
    throw 'Refusing to replace an existing archive or checksum.'
}
if (-not (Test-Path -LiteralPath ([IO.Path]::GetDirectoryName($output)) -PathType Container)) {
    throw 'Create the output parent directory before building a snapshot.'
}
$files = & git -C $repoRoot ls-tree -r --name-only HEAD
if ($LASTEXITCODE -ne 0) { throw 'Cannot enumerate the committed source tree.' }
foreach ($name in $files) {
    $normalized = $name.Replace('\', '/')
    $example = $normalized -match '(?:^|/)(?:\.env\.example|config\.example\.php)$'
    if ((-not $example -and $normalized -match '(?:^|/)\.env(?:\.|$)') -or
        ($normalized -match '(?:^|/)(?:data|storage|uploads|user_memories|backups|vendor|node_modules)/') -or
        ($normalized -match '(?:^|/)private/' -and -not $example) -or
        ($normalized -match '\.(?:sqlite3?|db|pem|key|pfx|p12|bak|log)$') -or
        ($normalized -match '(?:^|/)(?:client_secret|credentials)[^/]*\.json$')) {
        throw "Refusing a committed private/runtime path: $normalized"
    }
}
$tree = & git -C $repoRoot ls-tree -r HEAD
if ($LASTEXITCODE -ne 0 -or ($tree | Where-Object { $_ -match '^120000 ' })) {
    throw 'The reviewed source snapshot must not contain symbolic links.'
}
$commit = (& git -C $repoRoot rev-parse HEAD).Trim()
& git -C $repoRoot archive --format=zip --prefix=ZeroThink/ --output=$output $commit
if ($LASTEXITCODE -ne 0) { throw 'git archive failed.' }
$hash = (Get-FileHash -LiteralPath $output -Algorithm SHA256).Hash.ToLowerInvariant()
[IO.File]::WriteAllText($output + '.sha256', "$hash  $([IO.Path]::GetFileName($output))`n")
Write-Output "Archived reviewed commit: $commit"
Write-Output "SHA256: $hash"
