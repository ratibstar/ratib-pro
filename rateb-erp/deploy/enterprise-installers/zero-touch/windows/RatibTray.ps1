# Phase D.4 — RATEB Tray (Windows Notification Area)
# Status / Open ERP / Backup / Diagnostics / Restart / Exit — no technical jargon for customers.
param([string]$InstallRoot = 'C:\Program Files\RATEB Branch')
Add-Type -AssemblyName System.Windows.Forms
Add-Type -AssemblyName System.Drawing
$ErrorActionPreference = 'SilentlyContinue'

$appEnv = Join-Path $InstallRoot 'storage\branch\appliance.env'
$php = 'php.exe'
$cloudAdmin = 'https://rateb.sa/rateb-erp/public/admin/'
$url = $cloudAdmin
if (Test-Path $appEnv) {
  Get-Content $appEnv | ForEach-Object {
    if ($_ -match '^RATEB_CLOUD_ADMIN_URL=(.+)$' -and $Matches[1].Trim() -ne '') { $url = $Matches[1].Trim(); $cloudAdmin = $url }
    if ($_ -match '^RATEB_PHP_BIN=(.+)$' -and (Test-Path $Matches[1].Trim())) { $php = $Matches[1].Trim() }
  }
}
if ($url -notmatch 'rateb\.sa') { $url = $cloudAdmin }
$statusFile = Join-Path $InstallRoot 'storage\branch\status.json'

function Get-RatebStatus {
  if (-not (Test-Path $statusFile)) {
    return @{ display = '🔵 STARTING'; state = 'starting'; pending_records = 0; last_sync = $null; cloud_connected = $false; sqlite_connected = $false; open_url = $cloudAdmin }
  }
  try { return Get-Content $statusFile -Raw | ConvertFrom-Json } catch {
    return @{ display = '⚪ MAINTENANCE'; state = 'maintenance'; open_url = $cloudAdmin }
  }
}

function New-DotIcon([System.Drawing.Color]$Color) {
  $bmp = New-Object System.Drawing.Bitmap 16,16
  $g = [System.Drawing.Graphics]::FromImage($bmp)
  $g.Clear([System.Drawing.Color]::Transparent)
  $brush = New-Object System.Drawing.SolidBrush $Color
  $g.FillEllipse($brush, 2, 2, 12, 12)
  $g.Dispose()
  $icon = [System.Drawing.Icon]::FromHandle($bmp.GetHicon())
  return $icon
}

$icons = @{
  online = New-DotIcon ([System.Drawing.Color]::LimeGreen)
  offline = New-DotIcon ([System.Drawing.Color]::Red)
  syncing = New-DotIcon ([System.Drawing.Color]::Gold)
  starting = New-DotIcon ([System.Drawing.Color]::DodgerBlue)
  maintenance = New-DotIcon ([System.Drawing.Color]::Gray)
}

$form = New-Object System.Windows.Forms.Form
$form.WindowState = 'Minimized'
$form.ShowInTaskbar = $false
$form.Visible = $false

$notify = New-Object System.Windows.Forms.NotifyIcon
$notify.Text = 'RATEB ERP'
$notify.Icon = $icons.starting
$notify.Visible = $true

$menu = New-Object System.Windows.Forms.ContextMenuStrip
$miStatus = $menu.Items.Add('Status: STARTING')
$miStatus.Enabled = $false
$miPending = $menu.Items.Add('Pending: 0')
$miPending.Enabled = $false
$miLast = $menu.Items.Add('Last sync: —')
$miLast.Enabled = $false
$miCloud = $menu.Items.Add('Cloud: —')
$miCloud.Enabled = $false
$miSqlite = $menu.Items.Add('Local data: —')
$miSqlite.Enabled = $false
[void]$menu.Items.Add('-')
$miOpen = $menu.Items.Add('Open RATEB ERP')
$miBackup = $menu.Items.Add('Backup Now')
$miDiag = $menu.Items.Add('Diagnostics')
$miExport = $menu.Items.Add('Export Support Package')
$miRestart = $menu.Items.Add('Restart Services')
[void]$menu.Items.Add('-')
$miExit = $menu.Items.Add('Exit')
$notify.ContextMenuStrip = $menu

$miOpen.add_Click({
  $s = Get-RatebStatus
  $u = $cloudAdmin
  if ($s.open_url -and ([string]$s.open_url -match 'rateb\.sa')) { $u = [string]$s.open_url }
  elseif ($s.cloud_admin_url) { $u = [string]$s.cloud_admin_url }
  Start-Process $u
})
$miBackup.add_Click({
  Start-Process $php -ArgumentList @('-d','extension=pdo_sqlite','-d','extension=sqlite3',"`"$(Join-Path $InstallRoot 'bin\hybrid-branch-backup.php')`"","--label=manual") -WorkingDirectory $InstallRoot -WindowStyle Hidden
  $notify.ShowBalloonTip(3000, 'RATEB ERP', 'Backup started', [System.Windows.Forms.ToolTipIcon]::Info)
})
$miDiag.add_Click({
  Start-Process $php -ArgumentList @('-d','extension=pdo_sqlite','-d','extension=sqlite3',"`"$(Join-Path $InstallRoot 'bin\hybrid-branch-diagnostics.php')`"") -WorkingDirectory $InstallRoot -WindowStyle Hidden
  Start-Process (Join-Path $InstallRoot 'storage\branch')
})
$miExport.add_Click({
  Start-Process $php -ArgumentList @('-d','extension=pdo_sqlite','-d','extension=sqlite3',"`"$(Join-Path $InstallRoot 'bin\hybrid-zero-touch-export-support.php')`"") -WorkingDirectory $InstallRoot -WindowStyle Hidden
  $notify.ShowBalloonTip(4000, 'RATEB ERP', 'Support package exporting…', [System.Windows.Forms.ToolTipIcon]::Info)
})
$miRestart.add_Click({
  foreach ($n in @('RATEBBranchWeb','RATEBHybridSync')) {
    $exe = Join-Path $InstallRoot "bin\windows\$n.exe"
    if (Test-Path $exe) { & $exe restart 2>$null } else { Restart-Service $n -Force -ErrorAction SilentlyContinue }
  }
  $notify.ShowBalloonTip(3000, 'RATEB ERP', 'Services restarting', [System.Windows.Forms.ToolTipIcon]::Info)
})
$miExit.add_Click({
  $notify.Visible = $false
  [System.Windows.Forms.Application]::Exit()
})

$timer = New-Object System.Windows.Forms.Timer
$timer.Interval = 3000
$timer.add_Tick({
  $s = Get-RatebStatus
  $state = [string]$s.state
  if (-not $icons.ContainsKey($state)) { $state = 'maintenance' }
  $notify.Icon = $icons[$state]
  $disp = if ($s.display) { [string]$s.display } else { $state }
  $notify.Text = ('RATEB ERP — ' + $disp)
  $miStatus.Text = 'Status: ' + $disp
  $miPending.Text = 'Pending: ' + [string]$s.pending_records
  $miLast.Text = 'Last sync: ' + $(if ($s.last_sync) { $s.last_sync } else { '—' })
  $miCloud.Text = 'Cloud: ' + $(if ($s.cloud_connected) { 'Connected' } else { 'Unavailable' })
  $miSqlite.Text = 'Local data: ' + $(if ($s.sqlite_connected) { 'Ready' } else { 'Starting…' })
})
$timer.Start()

$notify.ShowBalloonTip(4000, 'RATEB ERP', 'Ready — double-click the desktop shortcut anytime', [System.Windows.Forms.ToolTipIcon]::Info)
[System.Windows.Forms.Application]::Run()
