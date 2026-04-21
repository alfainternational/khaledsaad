<#
.SYNOPSIS
    تثبيت Laravel Queue Worker كخدمة Windows باستخدام NSSM.

.DESCRIPTION
    يستخدم NSSM (The Non-Sucking Service Manager) لتحويل `php artisan queue:work`
    إلى خدمة Windows تعمل تلقائياً عند الإقلاع.

.PREREQUISITES
    - حمّل NSSM من https://nssm.cc/download ثم ضع nssm.exe في PATH
      أو داخل المسار المحدد بـ -NssmPath.

.USAGE
    # كـ Administrator:
    .\install-queue-service.ps1 -ProjectPath "D:\xampp\htdocs\khaledsaad" -PhpPath "D:\xampp\php\php.exe"

.EXAMPLE
    .\install-queue-service.ps1 `
        -ProjectPath "D:\xampp\htdocs\khaledsaad" `
        -PhpPath "D:\xampp\php\php.exe" `
        -ServiceName "KhaledsaadQueue" `
        -LogDir "D:\xampp\htdocs\khaledsaad\storage\logs"
#>

param(
    [Parameter(Mandatory = $true)][string]$ProjectPath,
    [Parameter(Mandatory = $true)][string]$PhpPath,
    [string]$ServiceName = "KhaledsaadQueue",
    [string]$NssmPath   = "nssm.exe",
    [string]$LogDir     = $null,
    [string]$Queues     = "ai,integrations,exports,notifications,default"
)

if (-not (Test-Path $ProjectPath)) { throw "ProjectPath not found: $ProjectPath" }
if (-not (Test-Path $PhpPath))     { throw "PhpPath not found: $PhpPath" }
if (-not $LogDir) { $LogDir = Join-Path $ProjectPath "storage\logs" }
if (-not (Test-Path $LogDir)) { New-Item -ItemType Directory -Path $LogDir -Force | Out-Null }

$artisan   = Join-Path $ProjectPath "artisan"
$stdoutLog = Join-Path $LogDir "queue-stdout.log"
$stderrLog = Join-Path $LogDir "queue-stderr.log"

Write-Host "Installing service '$ServiceName' ..." -ForegroundColor Cyan
& $NssmPath install $ServiceName $PhpPath $artisan "queue:work" "--queue=$Queues" "--tries=3" "--backoff=5,15,60" "--max-time=3600" "--sleep=3"
if ($LASTEXITCODE -ne 0) { throw "NSSM install failed with exit $LASTEXITCODE" }

& $NssmPath set $ServiceName AppDirectory  $ProjectPath
& $NssmPath set $ServiceName AppStdout     $stdoutLog
& $NssmPath set $ServiceName AppStderr     $stderrLog
& $NssmPath set $ServiceName AppRotateFiles 1
& $NssmPath set $ServiceName AppRotateBytes 20971520
& $NssmPath set $ServiceName Start          SERVICE_AUTO_START
& $NssmPath set $ServiceName AppRestartDelay 5000

Write-Host "Starting service ..." -ForegroundColor Cyan
Start-Service -Name $ServiceName
Write-Host "Done. Check status with:  Get-Service $ServiceName" -ForegroundColor Green
Write-Host "Logs:                     $stdoutLog" -ForegroundColor Yellow
