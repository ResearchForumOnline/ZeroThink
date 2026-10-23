$ErrorActionPreference = "Stop"

$baseUrl = if ($env:ZEROTHINK_BASE_URL) { $env:ZEROTHINK_BASE_URL } else { "https://zerothink.talktoai.org" }
$installRoot = Join-Path $HOME ".zerothink"
$binDir = Join-Path $installRoot "bin"
$scriptPath = Join-Path $installRoot "zerothink.py"
$cmdPath = Join-Path $binDir "zerothink.cmd"
$psPath = Join-Path $binDir "zerothink.ps1"

@'
███████╗███████╗██████╗  ██████╗
╚══███╔╝██╔════╝██╔══██╗██╔═══██╗
  ███╔╝ █████╗  ██████╔╝██║   ██║
 ███╔╝  ██╔══╝  ██╔══██╗██║   ██║
███████╗███████╗██║  ██║╚██████╔╝
╚══════╝╚══════╝╚═╝  ╚═╝ ╚═════╝
'@ | Write-Host
Write-Host "Installing ZeroThink CLI..." -ForegroundColor Cyan
Write-Host ""

New-Item -ItemType Directory -Force -Path $installRoot | Out-Null
New-Item -ItemType Directory -Force -Path $binDir | Out-Null

Invoke-WebRequest -UseBasicParsing -Uri "$baseUrl/cli-assets/zerothink.py" -OutFile $scriptPath

$pythonCmd = $null
if (Get-Command py -ErrorAction SilentlyContinue) {
    $pythonCmd = @("py", "-3")
} elseif (Get-Command python -ErrorAction SilentlyContinue) {
    $pythonCmd = @("python")
}

if ($pythonCmd) {
    $pyExe = $pythonCmd[0]
    $pyArgs = if ($pythonCmd.Length -gt 1) { $pythonCmd[1..($pythonCmd.Length-1)] } else { @() }
    $checkScript = 'import importlib.util,sys; sys.exit(0 if importlib.util.find_spec("prompt_toolkit") else 1)'
    & $pyExe @pyArgs -c $checkScript 2>$null
    if ($LASTEXITCODE -ne 0) {
        & $pyExe @pyArgs -m pip install --user --disable-pip-version-check prompt_toolkit *> $null
        if ($LASTEXITCODE -eq 0) {
            Write-Host "Installed prompt_toolkit for interactive autocomplete." -ForegroundColor Green
        } else {
            Write-Host "Could not install prompt_toolkit automatically. The CLI will still work without autocomplete." -ForegroundColor Yellow
        }
    }
}

$cmd = @"
@echo off
where py >nul 2>nul
if %errorlevel%==0 (
  py -3 "%USERPROFILE%\.zerothink\zerothink.py" %*
) else (
  python "%USERPROFILE%\.zerothink\zerothink.py" %*
)
"@
Set-Content -Path $cmdPath -Value $cmd -Encoding ASCII

$ps = @"
if (Get-Command py -ErrorAction SilentlyContinue) {
    & py -3 "$scriptPath" @args
} elseif (Get-Command python -ErrorAction SilentlyContinue) {
    & python "$scriptPath" @args
} else {
    Write-Error "Python 3 was not found on PATH."
    exit 1
}
"@
Set-Content -Path $psPath -Value $ps -Encoding UTF8

$userPath = [Environment]::GetEnvironmentVariable("Path", "User")
if (-not $userPath) { $userPath = "" }
if (($userPath -split ";") -notcontains $binDir) {
    $newPath = if ($userPath) { "$userPath;$binDir" } else { $binDir }
    [Environment]::SetEnvironmentVariable("Path", $newPath, "User")
    Write-Host "Added $binDir to your user PATH." -ForegroundColor Green
} else {
    Write-Host "PATH already contains $binDir" -ForegroundColor Green
}

Write-Host ""
Write-Host "Installed to $scriptPath" -ForegroundColor Green
Write-Host "Launchers created in $binDir" -ForegroundColor Green
Write-Host ""
Write-Host "Next steps:" -ForegroundColor Yellow
Write-Host "  zerothink                    # first run opens the guided setup"
Write-Host "  zerothink tutorial           # rerun the guide anytime"
Write-Host "  zerothink help quickstart"
Write-Host "  zerothink help examples"
Write-Host "  zerothink login"
Write-Host "  zerothink key set groq      # optional no-Google direct mode"
Write-Host "  zerothink access folder .   # safer repo-only mode"
Write-Host ""
Write-Host "Open a new PowerShell window if the command is not found immediately." -ForegroundColor DarkGray
