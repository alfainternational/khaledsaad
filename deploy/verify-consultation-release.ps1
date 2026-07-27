param(
    [switch]$Apply,
    [switch]$SkipBuilds
)

$ErrorActionPreference = 'Stop'
$repo = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
Set-Location $repo

function Invoke-Checked([string]$Label, [scriptblock]$Action) {
    Write-Host "`n[$Label]" -ForegroundColor Cyan
    & $Action
    if ($LASTEXITCODE -ne 0) { throw "$Label failed with exit code $LASTEXITCODE" }
}

Invoke-Checked 'PHP syntax and Laravel tests' {
    php vendor/phpunit/phpunit/phpunit --colors=never
}

Invoke-Checked 'Product integrity audit' {
    php artisan product:audit --require-verified --require-consultation
}

Invoke-Checked 'Code formatting' {
    vendor/bin/pint --test
}

if (-not $SkipBuilds) {
    Invoke-Checked 'Web production assets' { npm run build }
    Push-Location (Join-Path $repo 'mobile')
    try {
        Invoke-Checked 'Flutter static analysis' { flutter analyze --no-pub }
        Invoke-Checked 'Flutter tests' { flutter test --no-pub }
        Invoke-Checked 'Android APK' { flutter build apk --release --no-pub }
        Invoke-Checked 'Android App Bundle' { flutter build appbundle --release --no-pub }
    } finally {
        Pop-Location
    }
}

if ($Apply) {
    $pairs = @{}
    Get-Content (Join-Path $repo '.env') | ForEach-Object {
        if ($_ -match '^([^#=]+)=(.*)$') { $pairs[$matches[1]] = $matches[2].Trim('"') }
    }
    if ($pairs['APP_ENV'] -ne 'production') { throw 'Apply requires APP_ENV=production.' }
    $backupDir = Join-Path $repo 'deploy/backups'
    New-Item -ItemType Directory -Path $backupDir -Force | Out-Null
    $backup = Join-Path $backupDir ("before-consultation-{0}.sql" -f (Get-Date -Format 'yyyyMMdd-HHmmss'))
    $dump = 'D:/xampp/mysql/bin/mysqldump.exe'
    if (-not (Test-Path $dump)) { throw 'mysqldump was not found.' }
    $env:MYSQL_PWD = $pairs['DB_PASSWORD']
    try {
        $dumpArgs = @('-h', $pairs['DB_HOST'], '-P', $pairs['DB_PORT'], '-u', $pairs['DB_USERNAME'], '--single-transaction', '--routines', '--triggers', $pairs['DB_DATABASE'])
        $dumpProcess = Start-Process -FilePath $dump -ArgumentList $dumpArgs -RedirectStandardOutput $backup -NoNewWindow -Wait -PassThru
        if ($dumpProcess.ExitCode -ne 0 -or (Get-Item $backup).Length -lt 1024) { throw 'Database backup failed or is empty.' }
        php artisan down --render='errors::503'
        try {
            Invoke-Checked 'Additive production migration' { php artisan migrate --force }
            Invoke-Checked 'Publish consultation catalog' { php artisan db:seed --class=ConsultationCatalogSeeder --force }
            Invoke-Checked 'Production cache' { php artisan optimize }
            Invoke-Checked 'Queue restart' { php artisan queue:restart }
        } finally {
            php artisan up
        }
    } finally {
        Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue
    }
    Write-Host "Backup: $backup" -ForegroundColor Green
}

Write-Host "`nConsultation release gate passed." -ForegroundColor Green
