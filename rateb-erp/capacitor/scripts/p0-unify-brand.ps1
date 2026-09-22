# P0 brand unification — RATEB ERP canonical mark
# Source of truth: public/assets/pwa/erp-icon.svg
#   plate #0f1117 · tile #3b82f6 rx · letter R white
# NOT the Capacitor default geometric glyph.

$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Drawing

$Root = 'c:\Users\انا\Documents\ratibprogram'
$Res = Join-Path $Root 'rateb-erp\capacitor\android\app\src\main\res'
$Pwa = Join-Path $Root 'rateb-erp\public\assets\pwa'
$Work = Join-Path $Root 'rateb-erp\capacitor\_brand-work-P0'
New-Item -ItemType Directory -Path $Work -Force | Out-Null

$Dark = [System.Drawing.Color]::FromArgb(255, 15, 17, 23)      # #0f1117
$Blue = [System.Drawing.Color]::FromArgb(255, 59, 130, 246)    # #3b82f6
$White = [System.Drawing.Color]::FromArgb(255, 255, 255, 255)

function Save-Png([System.Drawing.Bitmap]$bmp, [string]$path) {
  $dir = Split-Path $path -Parent
  if (-not (Test-Path $dir)) { New-Item -ItemType Directory -Path $dir -Force | Out-Null }
  if (Test-Path $path) { Remove-Item $path -Force }
  $bmp.Save($path, [System.Drawing.Imaging.ImageFormat]::Png)
}

function Get-RoundedRectPath([float]$x, [float]$y, [float]$w, [float]$h, [float]$r) {
  $path = New-Object System.Drawing.Drawing2D.GraphicsPath
  if ($r -le 0) { $path.AddRectangle((New-Object System.Drawing.RectangleF $x, $y, $w, $h)); return $path }
  $d = $r * 2
  $path.AddArc($x, $y, $d, $d, 180, 90)
  $path.AddArc($x + $w - $d, $y, $d, $d, 270, 90)
  $path.AddArc($x + $w - $d, $y + $h - $d, $d, $d, 0, 90)
  $path.AddArc($x, $y + $h - $d, $d, $d, 90, 90)
  $path.CloseFigure()
  return $path
}

# Draw RATEB mark tile (blue rounded square + R) onto transparent or colored canvas.
# $tileScale = fraction of min(canvas) occupied by the blue tile (safe-zone aware).
function Draw-RatebMark {
  param(
    [int]$Size,
    [System.Drawing.Color]$Plate,   # Transparent => no plate
    [double]$TileScale = 0.64,
    [switch]$CirclePlate
  )
  $bmp = New-Object System.Drawing.Bitmap $Size, $Size, ([System.Drawing.Imaging.PixelFormat]::Format32bppArgb)
  $g = [System.Drawing.Graphics]::FromImage($bmp)
  $g.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
  $g.TextRenderingHint = [System.Drawing.Text.TextRenderingHint]::AntiAliasGridFit
  $g.Clear([System.Drawing.Color]::Transparent)

  if ($Plate.A -gt 0) {
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

  $tile = [float]($Size * $TileScale)
  $x = ($Size - $tile) / 2
  $y = ($Size - $tile) / 2
  $radius = $tile * (48.0 / 328.0)  # match SVG rx=48 on 328 tile
  $tilePath = Get-RoundedRectPath $x $y $tile $tile $radius
  $brush = New-Object System.Drawing.SolidBrush $Blue
  $g.FillPath($brush, $tilePath)
  $brush.Dispose()
  $tilePath.Dispose()

  # Letter R — SVG font-size 180 on 512 viewBox ≈ 180/328 of tile
  $fontSize = [float]($tile * (180.0 / 328.0))
  $font = New-Object System.Drawing.Font 'Segoe UI', $fontSize, ([System.Drawing.FontStyle]::Bold), ([System.Drawing.GraphicsUnit]::Pixel)
  $sf = New-Object System.Drawing.StringFormat
  $sf.Alignment = [System.Drawing.StringAlignment]::Center
  $sf.LineAlignment = [System.Drawing.StringAlignment]::Center
  # Optical center: SVG text y=310 in 512 with tile 92..420 → slightly below geometric center
  $textRect = New-Object System.Drawing.RectangleF ($x), ($y + $tile * 0.02), $tile, $tile
  $tbrush = New-Object System.Drawing.SolidBrush $White
  $g.DrawString('R', $font, $tbrush, $textRect, $sf)
  $tbrush.Dispose(); $font.Dispose(); $sf.Dispose()

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
  $tile = [float]([Math]::Min($W, $H) * 0.28)
  $x = ($W - $tile) / 2
  $y = ($H - $tile) / 2
  $radius = $tile * (48.0 / 328.0)
  $tilePath = Get-RoundedRectPath $x $y $tile $tile $radius
  $brush = New-Object System.Drawing.SolidBrush $Blue
  $g.FillPath($brush, $tilePath)
  $brush.Dispose(); $tilePath.Dispose()
  $fontSize = [float]($tile * (180.0 / 328.0))
  $font = New-Object System.Drawing.Font 'Segoe UI', $fontSize, ([System.Drawing.FontStyle]::Bold), ([System.Drawing.GraphicsUnit]::Pixel)
  $sf = New-Object System.Drawing.StringFormat
  $sf.Alignment = [System.Drawing.StringAlignment]::Center
  $sf.LineAlignment = [System.Drawing.StringAlignment]::Center
  $textRect = New-Object System.Drawing.RectangleF $x, ($y + $tile * 0.02), $tile, $tile
  $tbrush = New-Object System.Drawing.SolidBrush $White
  $g.DrawString('R', $font, $tbrush, $textRect, $sf)
  $tbrush.Dispose(); $font.Dispose(); $sf.Dispose(); $g.Dispose()
  return $bmp
}

Write-Host '=== Android adaptive foregrounds (transparent + tile+R in safe zone) ==='
$fgSizes = @{
  'mipmap-mdpi' = 108; 'mipmap-hdpi' = 162; 'mipmap-xhdpi' = 216
  'mipmap-xxhdpi' = 324; 'mipmap-xxxhdpi' = 432
}
$launcherSizes = @{
  'mipmap-mdpi' = 48; 'mipmap-hdpi' = 72; 'mipmap-xhdpi' = 96
  'mipmap-xxhdpi' = 144; 'mipmap-xxxhdpi' = 192
}

foreach ($dir in $fgSizes.Keys) {
  # Adaptive FG: transparent; tile ~52% stays inside 66/108 safe zone
  $fg = Draw-RatebMark -Size ([int]$fgSizes[$dir]) -Plate ([System.Drawing.Color]::Transparent) -TileScale 0.52
  Save-Png $fg (Join-Path $Res "$dir\ic_launcher_foreground.png")
  $fg.Dispose()

  $ls = [int]$launcherSizes[$dir]
  $launch = Draw-RatebMark -Size $ls -Plate $Dark -TileScale 0.64
  Save-Png $launch (Join-Path $Res "$dir\ic_launcher.png")
  $launch.Dispose()

  $round = Draw-RatebMark -Size $ls -Plate $Dark -TileScale 0.58 -CirclePlate
  Save-Png $round (Join-Path $Res "$dir\ic_launcher_round.png")
  $round.Dispose()
  Write-Host "  $dir"
}

# Adaptive background = brand dark
$utf8 = New-Object System.Text.UTF8Encoding $false
[System.IO.File]::WriteAllText((Join-Path $Res 'values\ic_launcher_background.xml'), @"
<?xml version="1.0" encoding="utf-8"?>
<resources>
    <color name="ic_launcher_background">#0F1117</color>
</resources>
"@, $utf8)

$adaptiveXml = @"
<?xml version="1.0" encoding="utf-8"?>
<adaptive-icon xmlns:android="http://schemas.android.com/apk/res/android">
    <background android:drawable="@color/ic_launcher_background"/>
    <foreground android:drawable="@mipmap/ic_launcher_foreground"/>
</adaptive-icon>
"@
[System.IO.File]::WriteAllText((Join-Path $Res 'mipmap-anydpi-v26\ic_launcher.xml'), $adaptiveXml, $utf8)
[System.IO.File]::WriteAllText((Join-Path $Res 'mipmap-anydpi-v26\ic_launcher_round.xml'), $adaptiveXml, $utf8)

Write-Host '=== Splash ==='
$splashDirs = @(
  @{ Rel = 'drawable\splash.png'; W = 1080; H = 1920 },
  @{ Rel = 'drawable-port-mdpi\splash.png'; W = 320; H = 480 },
  @{ Rel = 'drawable-port-hdpi\splash.png'; W = 480; H = 800 },
  @{ Rel = 'drawable-port-xhdpi\splash.png'; W = 720; H = 1280 },
  @{ Rel = 'drawable-port-xxhdpi\splash.png'; W = 1080; H = 1920 },
  @{ Rel = 'drawable-port-xxxhdpi\splash.png'; W = 1440; H = 2560 },
  @{ Rel = 'drawable-land-mdpi\splash.png'; W = 480; H = 320 },
  @{ Rel = 'drawable-land-hdpi\splash.png'; W = 800; H = 480 },
  @{ Rel = 'drawable-land-xhdpi\splash.png'; W = 1280; H = 720 },
  @{ Rel = 'drawable-land-xxhdpi\splash.png'; W = 1920; H = 1080 },
  @{ Rel = 'drawable-land-xxxhdpi\splash.png'; W = 2560; H = 1440 }
)
foreach ($s in $splashDirs) {
  $bmp = Draw-Splash ([int]$s.W) ([int]$s.H)
  Save-Png $bmp (Join-Path $Res $s.Rel)
  $bmp.Dispose()
}

Write-Host '=== PWA ==='
$p192 = Draw-RatebMark -Size 192 -Plate $Dark -TileScale 0.64
Save-Png $p192 (Join-Path $Pwa 'erp-icon-192.png'); $p192.Dispose()
$p512 = Draw-RatebMark -Size 512 -Plate $Dark -TileScale 0.64
Save-Png $p512 (Join-Path $Pwa 'erp-icon-512.png'); $p512.Dispose()
# maskable: extra inset (tile ~42% of canvas) for OEM masks
$pm = Draw-RatebMark -Size 512 -Plate $Dark -TileScale 0.42
Save-Png $pm (Join-Path $Pwa 'erp-icon-maskable-512.png'); $pm.Dispose()
# transparent mark only (for tooling)
$tm = Draw-RatebMark -Size 512 -Plate ([System.Drawing.Color]::Transparent) -TileScale 0.64
Save-Png $tm (Join-Path $Pwa 'erp-mark-transparent.png'); $tm.Dispose()

# Canonical SVG (no embedded Capacitor glyph)
$svg = @"
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" role="img" aria-label="RATEB ERP">
  <rect width="512" height="512" fill="#0f1117"/>
  <rect x="92" y="92" width="328" height="328" rx="48" fill="#3b82f6"/>
  <text x="256" y="310" text-anchor="middle" font-family="Segoe UI, Arial, Helvetica, sans-serif" font-size="180" font-weight="700" fill="#ffffff">R</text>
</svg>
"@
[System.IO.File]::WriteAllText((Join-Path $Pwa 'erp-icon.svg'), $svg, $utf8)

Write-Host 'DONE RATEB mark generation'
