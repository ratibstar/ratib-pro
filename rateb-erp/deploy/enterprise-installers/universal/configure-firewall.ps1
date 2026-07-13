# Phase D.3 — Windows Firewall rule for Branch Web
param([int]$Port = 80)
$ErrorActionPreference = 'SilentlyContinue'
$name = "RATIB Branch Web $Port"
Get-NetFirewallRule -DisplayName $name -ErrorAction SilentlyContinue | Remove-NetFirewallRule
New-NetFirewallRule -DisplayName $name -Direction Inbound -Protocol TCP -LocalPort $Port -Action Allow -Profile Any | Out-Null
Write-Host "firewall=windows port=$Port"
