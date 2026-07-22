#Requires -RunAsAdministrator
<#
.SYNOPSIS
  Phase D.2/D.3 — Branch Appliance install. Delegates to Universal installer.
#>
param(
    [string]$InstallRoot = 'C:\Program Files\RATEB Branch',
    [string]$PhpPath = '',
    [switch]$Upgrade,
    [switch]$Silent
)
$ErrorActionPreference = 'Stop'
$universal = Join-Path $PSScriptRoot '..\universal\install-universal.ps1'
$args = @{
  InstallRoot = $InstallRoot
  Silent = $Silent
}
if ($PhpPath) { $args.PhpPath = $PhpPath }
& $universal @args
