# P0 brand for rateb_mobile — source: assets/rateb-logo.svg (RATEB wordmark + brand colors)
# Does not invent a new mark; square/adaptive adaptations of the existing wordmark.

$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Drawing

$Root = 'c:\Users\انا\Documents\ratibprogram\rateb_mobile'
$Res = Join-Path $Root 'android\app\src\main\res'
$Web = Join-Path $Root 'web'
$Work = Join-Path $Root '_brand-work-P0'
New-Item -ItemType Directory -Path $Work -Force | Out-Null

# From assets/rateb-logo.svg gradient stops
$C1 = [System.Drawing.Color]::FromArgb(255, 233, 69, 96)    # #e94560
$C2 = [System.Drawing.Color]::FromArgb(255, 93, 173, 226)   # #5dade2
$C3 = [System.Drawing.Color]::FromArgb(255, 241, 196, 15)   # #f1c40f
$Dark = [System.Drawing.Color]::FromArgb(255, 11, 18, 32)   # AppColors.darkBackground #0B1220
$White = [System.Drawing.Color]::FromArgb(255, 255, 255, 255)

function Save-Png([System.Drawing.Bitmap]$bmp, [string]$path) {
  $dir = Split-Path $path -Parent
  if (-not (Test-Path $dir)) { New-Item -ItemType Directory -Path $dir -Force | Out-Null }
  if (Test-Path $path) { Remove-Item $path -Force }
  $bmp.Save($path, [System.Drawing.Imaging.ImageFormat]::Png)
}

function Draw-RatebWordmark {
  param(
    [int]$Size,
    [System.Drawing.Color]$Plate,
    [double]$TextScale = 0.22,
    [switch]$CirclePlate,
    [switch]$TransparentPlate
  )
  $bmp = New-Object System.Drawing.Bitmap $Size, $Size, ([System.Drawing.Imaging.PixelFormat]::Format32bppArgb)
  $g = [System.Drawing.Graphics]::FromImage($bmp)
  $g.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
  $g.TextRenderingHint = [System.Drawing.Text.TextRenderingHint]::AntiAliasGridFit
  $g.Clear([System.Drawing.Color]::Transparent)

  if (-not $TransparentPlate) {
    if ($CirclePlate) {
      $br = New-Object System.Drawing.SolidBrush $Plate
      $g.FillEllipse($br, 0, 0, $Size - 1, $Size - 1)
      $br.Dispose()
      $path = New-Object System.Drawing.Drawing2D.GraphicsPath
      $path.AddEllipse(0, 0, $Size - 1, $Size - 1)
      $g.SetClip($path)
      $path.Dispose()
    } else {
      $g.Clear($Plate)
    }
  }

  $fontSize = [float]($Size * $TextScale)
  $font = New-Object System.Drawing.Font 'Segoe UI', $fontSize, ([System.Drawing.FontStyle]::Bold), ([System.Drawing.GraphicsUnit]::Pixel)
  $sf = New-Object System.Drawing.StringFormat
  $sf.Alignment = [System.Drawing.StringAlignment]::Center
  $sf.LineAlignment = [System.Drawing.StringAlignment]::Center
  $rect = New-Object System.Drawing.RectangleF 0, 0, $Size, $Size

  # Gradient brush matching rateb-logo.svg stops (left→right)
  $gy = [int]($Size * 0.35)
  $gh = [Math]::Max(1, [int]($Size * 0.3))
  $gradRect = New-Object System.Drawing.Rectangle 0, $gy, $Size, $gh
  $brush = New-Object System.Drawing.Drawing2D.LinearGradientBrush (
    $gradRect,
    $C1,
    $C3,
    [System.Drawing.Drawing2D.LinearGradientMode]::Horizontal
  )
  $cb = New-Object System.Drawing.Drawing2D.ColorBlend
  $cb.Colors = [System.Drawing.Color[]]@($C1, $C2, $C3)
  $cb.Positions = [float[]]@(0.0, 0.5, 1.0)
  $brush.InterpolationColors = $cb

  $g.DrawString('RATEB', $font, $brush, $rect, $sf)
  $brush.Dispose(); $font.Dispose(); $sf.Dispose()
  if ($CirclePlate) { $g.ResetClip() }
  $g.Dispose()
  return $bmp
}

function Draw-Splash([int]$W, [int]$H) {
  $bmp = New-Object System.Drawing.Bitmap $W, $H, ([System.Drawing.Imaging.PixelFormat]::Format32bppArgb)
  $g = [System.Drawing.Graphics]::FromImage($bmp)
  $g.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
  $g.TextRenderingHint = [System.Drawing.Text.TextRenderingHint]::AntiAliasGridFit
  $g.Clear($Dark)
  $fontSize = [float]([Math]::Min($W, $H) * 0.08)
  $font = New-Object System.Drawing.Font 'Segoe UI', $fontSize, ([System.Drawing.FontStyle]::Bold), ([System.Drawing.GraphicsUnit]::Pixel)
  $sf = New-Object System.Drawing.StringFormat
  $sf.Alignment = [System.Drawing.StringAlignment]::Center
  $sf.LineAlignment = [System.Drawing.StringAlignment]::Center
  $rect = New-Object System.Drawing.RectangleF 0, 0, $W, $H
  $gy = [int]($H * 0.45)
  $gh = [Math]::Max(1, [int]($H * 0.12))
  $gradRect = New-Object System.Drawing.Rectangle 0, $gy, $W, $gh
  $brush = New-Object System.Drawing.Drawing2D.LinearGradientBrush ($gradRect, $C1, $C3, 0.0)
  $cb = New-Object System.Drawing.Drawing2D.ColorBlend
  $cb.Colors = [System.Drawing.Color[]]@($C1, $C2, $C3)
  $cb.Positions = [float[]]@(0.0, 0.5, 1.0)
  $brush.InterpolationColors = $cb
  $g.DrawString('RATEB', $font, $brush, $rect, $sf)
  $brush.Dispose(); $font.Dispose(); $sf.Dispose(); $g.Dispose()
  return $bmp
}

Write-Host '=== Android adaptive + launcher ==='
$fgSizes = @{
  'mipmap-mdpi' = 108; 'mipmap-hdpi' = 162; 'mipmap-xhdpi' = 216
  'mipmap-xxhdpi' = 324; 'mipmap-xxxhdpi' = 432
}
$launcherSizes = @{
  'mipmap-mdpi' = 48; 'mipmap-hdpi' = 72; 'mipmap-xhdpi' = 96
  'mipmap-xxhdpi' = 144; 'mipmap-xxxhdpi' = 192
}

foreach ($dir in $fgSizes.Keys) {
  # Adaptive FG: transparent + RATEB wordmark in safe zone
  $fg = Draw-RatebWordmark -Size ([int]$fgSizes[$dir]) -Plate ([System.Drawing.Color]::Transparent) -TextScale 0.18 -TransparentPlate
  Save-Png $fg (Join-Path $Res "$dir\ic_launcher_foreground.png")
  $fg.Dispose()

  $ls = [int]$launcherSizes[$dir]
  $launch = Draw-RatebWordmark -Size $ls -Plate $Dark -TextScale 0.20
  Save-Png $launch (Join-Path $Res "$dir\ic_launcher.png")
  $launch.Dispose()

  $round = Draw-RatebWordmark -Size $ls -Plate $Dark -TextScale 0.18 -CirclePlate
  Save-Png $round (Join-Path $Res "$dir\ic_launcher_round.png")
  $round.Dispose()
  Write-Host "  $dir"
}

$anyDpi = Join-Path $Res 'mipmap-anydpi-v26'
New-Item -ItemType Directory -Path $anyDpi -Force | Out-Null
$utf8 = New-Object System.Text.UTF8Encoding $false
[System.IO.File]::WriteAllText((Join-Path $Res 'values\ic_launcher_background.xml'), @"
<?xml version="1.0" encoding="utf-8"?>
<resources>
    <color name="ic_launcher_background">#0B1220</color>
</resources>
"@, $utf8)

$adaptive = @"
<?xml version="1.0" encoding="utf-8"?>
<adaptive-icon xmlns:android="http://schemas.android.com/apk/res/android">
    <background android:drawable="@color/ic_launcher_background"/>
    <foreground android:drawable="@mipmap/ic_launcher_foreground"/>
</adaptive-icon>
"@
[System.IO.File]::WriteAllText((Join-Path $anyDpi 'ic_launcher.xml'), $adaptive, $utf8)
[System.IO.File]::WriteAllText((Join-Path $anyDpi 'ic_launcher_round.xml'), $adaptive, $utf8)

# Point Manifest roundIcon if missing - check later

Write-Host '=== Splash ==='
$splash = Draw-Splash 1080 1920
Save-Png $splash (Join-Path $Res 'drawable\splash_logo.png')
$splash.Dispose()

# Update launch_background xmls
$launchXml = @"
<?xml version="1.0" encoding="utf-8"?>
<layer-list xmlns:android="http://schemas.android.com/apk/res/android">
    <item android:drawable="@color/ic_launcher_background" />
    <item>
        <bitmap
            android:gravity="center"
            android:src="@drawable/splash_logo" />
    </item>
</layer-list>
"@
# splash_logo is full screen dark+text — use as sole window background instead to avoid double dark
$launchXmlSimple = @"
<?xml version="1.0" encoding="utf-8"?>
<layer-list xmlns:android="http://schemas.android.com/apk/res/android">
    <item>
        <bitmap
            android:gravity="fill"
            android:src="@drawable/splash_logo" />
    </item>
</layer-list>
"@
[System.IO.File]::WriteAllText((Join-Path $Res 'drawable\launch_background.xml'), $launchXmlSimple, $utf8)
[System.IO.File]::WriteAllText((Join-Path $Res 'drawable-v21\launch_background.xml'), $launchXmlSimple, $utf8)

Write-Host '=== Web / PWA ==='
$w192 = Draw-RatebWordmark -Size 192 -Plate $Dark -TextScale 0.20
Save-Png $w192 (Join-Path $Web 'icons\Icon-192.png'); $w192.Dispose()
$w512 = Draw-RatebWordmark -Size 512 -Plate $Dark -TextScale 0.20
Save-Png $w512 (Join-Path $Web 'icons\Icon-512.png'); $w512.Dispose()
$wm192 = Draw-RatebWordmark -Size 192 -Plate $Dark -TextScale 0.14
Save-Png $wm192 (Join-Path $Web 'icons\Icon-maskable-192.png'); $wm192.Dispose()
$wm512 = Draw-RatebWordmark -Size 512 -Plate $Dark -TextScale 0.14
Save-Png $wm512 (Join-Path $Web 'icons\Icon-maskable-512.png'); $wm512.Dispose()
$fav = Draw-RatebWordmark -Size 48 -Plate $Dark -TextScale 0.18
Save-Png $fav (Join-Path $Web 'favicon.png'); $fav.Dispose()

# Master transparent wordmark for tooling
$tm = Draw-RatebWordmark -Size 512 -Plate ([System.Drawing.Color]::Transparent) -TextScale 0.22 -TransparentPlate
Save-Png $tm (Join-Path $Root 'brand\rateb-mark-transparent.png'); $tm.Dispose()

Write-Host 'DONE'
