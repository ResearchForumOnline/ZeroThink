$ErrorActionPreference = 'Stop'

$root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$builder = Join-Path $root 'ops\build_safe_bundle.ps1'
$testRoot = Join-Path ([System.IO.Path]::GetTempPath()) ('zt_safe_bundle_test_' + [guid]::NewGuid().ToString('N'))
$passed = 0

function Assert-True {
    param([bool]$Condition, [string]$Message)
    if (-not $Condition) {
        throw "FAIL: $Message"
    }
    $script:passed++
    Write-Host "PASS: $Message"
}

function Write-Fixture {
    param([string]$Project, [string]$RelativePath, [string]$Content)
    $path = Join-Path $Project $RelativePath
    $directory = Split-Path -Parent $path
    New-Item -ItemType Directory -Path $directory -Force | Out-Null
    [System.IO.File]::WriteAllText($path, $Content)
}

function Assert-RejectedSecretFixture {
    param([string]$RelativePath, [string]$Content)
    $caseRoot = Join-Path $testRoot ([guid]::NewGuid().ToString('N'))
    $project = Join-Path $caseRoot 'project'
    New-Item -ItemType Directory -Path $project -Force | Out-Null
    Write-Fixture -Project $project -RelativePath 'index.php' -Content '<?php echo "safe";'
    Write-Fixture -Project $project -RelativePath $RelativePath -Content $Content
    $rejected = $false
    try {
        & $builder -ProjectRoot $project -OutputZip (Join-Path $caseRoot 'fixture.zip') *> $null
    }
    catch {
        $rejected = $_.Exception.Message -like '*high-confidence secret*'
    }
    Assert-True $rejected "secret fixture is rejected in $RelativePath"
}

New-Item -ItemType Directory -Path $testRoot -Force | Out-Null
try {
    $cleanRoot = Join-Path $testRoot 'clean'
    $cleanProject = Join-Path $cleanRoot 'project'
    New-Item -ItemType Directory -Path $cleanProject -Force | Out-Null
    Write-Fixture -Project $cleanProject -RelativePath 'index.php' -Content '<?php echo "safe";'
    Write-Fixture -Project $cleanProject -RelativePath '.htaccess' -Content 'Options -Indexes'
    Write-Fixture -Project $cleanProject -RelativePath 'scripts/update-dna-data-packs' -Content '#!/usr/bin/env bash'
    Write-Fixture -Project $cleanProject -RelativePath 'cache/stale.pyc' -Content 'stale bytecode'
    Write-Fixture -Project $cleanProject -RelativePath 'pkg/__pycache__/stale.pyo' -Content 'stale bytecode'

    $cleanZip = Join-Path $cleanRoot 'clean.zip'
    & $builder -ProjectRoot $cleanProject -OutputZip $cleanZip *> $null
    $expanded = Join-Path $cleanRoot 'expanded'
    Expand-Archive -LiteralPath $cleanZip -DestinationPath $expanded
    $files = @(Get-ChildItem -LiteralPath $expanded -Recurse -Force -File | ForEach-Object {
        $_.FullName.Substring($expanded.Length).TrimStart('\').Replace('\', '/')
    })
    Assert-True ($files -contains '.htaccess') 'dotfile text remains in a clean bundle'
    Assert-True ($files -contains 'scripts/update-dna-data-packs') 'extensionless script remains in a clean bundle'
    Assert-True (-not ($files | Where-Object { $_ -match '(?:^|/)__pycache__/|\.(?:pyc|pyo)$' })) 'Python bytecode and cache files are absent from the bundle manifest'

    $providerToken = 'GOC' + 'SPX-' + ('A' * 24)
    $clientSecret = '{"client_' + 'secret":"' + ('B' * 24) + '"}'
    $databasePassword = ('DB_' + 'PASS=') + ('C' * 16)
    Assert-RejectedSecretFixture 'page.html' $providerToken
    Assert-RejectedSecretFixture 'style.css' $providerToken
    Assert-RejectedSecretFixture 'install.cmd' $providerToken
    Assert-RejectedSecretFixture 'worker.service' $providerToken
    Assert-RejectedSecretFixture '.htaccess' $databasePassword
    Assert-RejectedSecretFixture 'scripts/update-dna-data-packs' $providerToken
    Assert-RejectedSecretFixture 'vendor/runtime/config.php' $clientSecret

    Write-Host "RESULT: $passed passed, 0 failed"
}
finally {
    if (Test-Path -LiteralPath $testRoot) {
        Remove-Item -LiteralPath $testRoot -Recurse -Force
    }
}
