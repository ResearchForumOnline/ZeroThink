$ErrorActionPreference = "Stop"
$homeDir = Join-Path $env:USERPROFILE ".quantumzero"
New-Item -ItemType Directory -Force -Path $homeDir | Out-Null
$target = Join-Path $homeDir "quantumzero_agent.py"
$url = "https://zerothink.talktoai.org/quantumzero-python/quantumzero_agent.py"
Invoke-WebRequest -Uri $url -OutFile $target
Write-Host "QuantumZero Agent installed to $target"
Write-Host ""
Write-Host "Run:"
Write-Host "  python $target setup"
Write-Host "  python $target doctor"
Write-Host "  python $target ask --mission evidence"

