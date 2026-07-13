param(
    [string] $TaskName = 'KhaledSaad Private AI Worker',
    [string] $WorkerDirectory = 'D:\KhaledSaadAI\worker'
)

$ErrorActionPreference = 'Stop'
$launcher = Join-Path $WorkerDirectory 'run_windows_worker.ps1'
if (-not (Test-Path -LiteralPath $launcher)) {
    throw 'The private worker launcher does not exist.'
}

$arguments = '-NoProfile -NonInteractive -ExecutionPolicy Bypass -WindowStyle Hidden -File "{0}" -Continuous' -f $launcher
$action = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument $arguments
$trigger = New-ScheduledTaskTrigger -AtLogOn -User $env:USERNAME
$settings = New-ScheduledTaskSettingsSet `
    -MultipleInstances IgnoreNew `
    -RestartCount 999 `
    -RestartInterval (New-TimeSpan -Minutes 1) `
    -ExecutionTimeLimit ([TimeSpan]::Zero) `
    -StartWhenAvailable `
    -DontStopIfGoingOnBatteries `
    -AllowStartIfOnBatteries

Register-ScheduledTask -TaskName $TaskName -Action $action -Trigger $trigger -Settings $settings -Force | Out-Null
Start-ScheduledTask -TaskName $TaskName
