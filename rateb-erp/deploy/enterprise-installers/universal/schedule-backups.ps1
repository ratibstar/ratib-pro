#Requires -RunAsAdministrator
# Phase D.3 — scheduled backups + recovery watchdog (Task Scheduler)
param(
  [string]$InstallRoot = 'C:\Program Files\RATIB Branch',
  [string]$PhpPath = 'php.exe'
)
$ErrorActionPreference = 'Stop'
$backupPhp = Join-Path $InstallRoot 'bin\hybrid-branch-backup.php'
$recoverPhp = Join-Path $InstallRoot 'bin\hybrid-branch-recover.php'
$flags = '-d extension=pdo_sqlite -d extension=sqlite3'

function Register-RatibTask($Name, $Args, $Trigger) {
  Unregister-ScheduledTask -TaskName $Name -Confirm:$false -ErrorAction SilentlyContinue
  $action = New-ScheduledTaskAction -Execute $PhpPath -Argument "$flags `"$Args`"" -WorkingDirectory $InstallRoot
  Register-ScheduledTask -TaskName $Name -Action $action -Trigger $Trigger -User 'SYSTEM' -RunLevel Highest -Force | Out-Null
}

Register-RatibTask 'RATIB Branch Backup Daily' "$backupPhp --label=daily" (New-ScheduledTaskTrigger -Daily -At 2am)
Register-RatibTask 'RATIB Branch Backup Weekly' "$backupPhp --label=weekly" (New-ScheduledTaskTrigger -Weekly -DaysOfWeek Sunday -At 3am)
Register-RatibTask 'RATIB Branch Backup Monthly' "$backupPhp --label=monthly" (New-ScheduledTaskTrigger -Weekly -WeeksInterval 4 -DaysOfWeek Sunday -At 4am)
Register-RatibTask 'RATIB Branch Recover Watchdog' $recoverPhp (New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Hours 1) -RepetitionDuration ([TimeSpan]::MaxValue))
Write-Host 'OK Windows backup+recover tasks registered'
