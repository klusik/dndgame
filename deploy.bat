@echo off
setlocal EnableExtensions

set "ROOT=%~dp0"
set "DEPLOY_DIR=%ROOT%deploy"

if not exist "%DEPLOY_DIR%" mkdir "%DEPLOY_DIR%"

powershell -NoProfile -ExecutionPolicy Bypass -Command "& { $ErrorActionPreference = 'Stop'; $root = (Resolve-Path '%ROOT%').Path; $deploy = Join-Path $root 'deploy'; New-Item -ItemType Directory -Force -Path $deploy | Out-Null; $stamp = Get-Date -Format 'yyyyMMdd-HHmmss'; $zip = Join-Path $deploy ('dnd-chatgpt-campaign-' + $stamp + '.zip'); $tmp = Join-Path $env:TEMP ('dnd-deploy-' + [guid]::NewGuid().ToString()); New-Item -ItemType Directory -Force -Path $tmp | Out-Null; $files = Get-ChildItem -LiteralPath $root -Recurse -File -Force | Where-Object { $relative = $_.FullName.Substring($root.Length).TrimStart('\','/'); $relative = $relative -replace '/', '\'; if ($relative -eq 'config.php') { return $false }; if ($relative -eq 'storage\install.lock') { return $false }; if ($relative -like '.git\*') { return $false }; if ($relative -like 'deploy\*') { return $false }; if ($relative -like 'storage\*' -and $relative -ne 'storage\.gitkeep' -and $relative -ne 'storage\.htaccess') { return $false }; return $true }; foreach ($file in $files) { $relative = $file.FullName.Substring($root.Length).TrimStart('\','/'); $target = Join-Path $tmp $relative; New-Item -ItemType Directory -Force -Path (Split-Path $target -Parent) | Out-Null; Copy-Item -LiteralPath $file.FullName -Destination $target -Force }; Compress-Archive -Path (Join-Path $tmp '*') -DestinationPath $zip -Force; Remove-Item -LiteralPath $tmp -Recurse -Force; Write-Host ('Created deploy package: ' + $zip) }"

if errorlevel 1 (
    echo Deployment package creation failed.
    exit /b 1
)

echo Done.
