# Build a company-branded RATEB mobile app (own icon, name, package and update channel) and stage it
# for deploy. Super Admin -> Mobile Apps -> company card -> "Company-branded build" shows the command.
#
#   .\scripts\build-branded-app.ps1 -App hr -Key "hr-51-0a1b2c3d4e" -Package "sa.rateb.hr.mobile.c51" `
#       -Name "Acme" -IconUrl "https://rateb.sa/.../logo.png" -Server "https://rateb.sa/rateb-erp/public" -Code "ABCD-2345"
#   .\scripts\build-branded-app.ps1 -RebuildAll        # rebuild every branded app (after a new base version)
#
# Output (commit + push; the next Mobile Apps page view moves it into the company link/QR):
#   rateb-erp/public/downloads/company/<key>.apk + <key>.json   public, unguessable key
#   mobile-branding/<key>.json                                   build spec for -RebuildAll (not deployed)
# App sources are restored from git afterwards; they must have no local changes before the build.

param(
    [ValidateSet('hr', 'erp', 'customer')][string]$App,
    [string]$Key,
    [string]$Package,
    [string]$Name,
    [string]$IconUrl,
    [string]$Server,
    [string]$Code = '',
    [switch]$RebuildAll
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$specDir = Join-Path $root 'mobile-branding'
$publishDir = Join-Path $root 'rateb-erp\public\downloads\company'

if ($RebuildAll) {
    $specs = Get-ChildItem $specDir -Filter '*.json' -ErrorAction SilentlyContinue
    if (-not $specs) { Write-Host 'No branded builds to rebuild.'; exit 0 }
    $failed = @()
    foreach ($file in $specs) {
        $s = [IO.File]::ReadAllText($file.FullName, [Text.Encoding]::UTF8) | ConvertFrom-Json
        try {
            & $PSCommandPath -App $s.app -Key $s.key -Package $s.package -Name $s.name -IconUrl $s.icon_url -Server $s.server -Code $s.code
        } catch {
            Write-Host "$($s.key): $_" -ForegroundColor Red
            $failed += $s.key
        }
    }
    if ($failed) { Write-Host ('FAILED: ' + ($failed -join ', ')) -ForegroundColor Red; exit 1 }
    exit 0
}

if (-not $App) { throw '-App is required (hr|erp|customer)' }
if ($Key -notmatch "^$App-[1-9][0-9]{0,9}-[a-f0-9]{10}$") { throw "Invalid -Key for $App" }
if ($Package -notmatch '^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$') { throw 'Invalid -Package' }
if ($IconUrl -notmatch '^https://\S+$') { throw '-IconUrl must be an https URL' }
$Server = $Server.Trim().TrimEnd('/')
if ($Server -notmatch '^https://[^/\s]+(/\S*)?$') { throw '-Server must be an https URL' }
$Name = ($Name -replace '[''"\\`$<>&@?\r\n]', '').Trim()
if (-not $Name) { throw '-Name is empty' }
if ($Code -and $Code -notmatch '^[A-Z0-9]{4}-?[A-Z0-9]{4}$') { throw 'Invalid -Code' }

if (-not $env:JAVA_HOME -or -not (Test-Path $env:JAVA_HOME)) {
    $env:JAVA_HOME = 'C:\Program Files\Android\Android Studio\jbr'
}
$env:Path = "$env:JAVA_HOME\bin;$env:Path"
$flutter = @('C:\flutter_sdk\bin\flutter.bat', 'C:\flutter-sdk\bin\flutter.bat') | Where-Object { Test-Path $_ } | Select-Object -First 1
$aapt2 = Get-ChildItem 'C:\android_sdk\build-tools' -Recurse -Filter 'aapt2.exe' -ErrorAction SilentlyContinue | Sort-Object FullName -Descending | Select-Object -First 1

switch ($App) {
    'hr' {
        $appDir = Join-Path $root 'ratib_hr_mobile'
        $res = Join-Path $appDir 'android\app\src\main\res'
        $restore = @('ratib_hr_mobile/android/app/src/main/res', 'ratib_hr_mobile/android/app/src/production/res')
        $stringsFiles = @(
            (Join-Path $appDir 'android\app\src\production\res\values\strings.xml'),
            (Join-Path $appDir 'android\app\src\production\res\values-ar\strings.xml')
        )
    }
    'customer' {
        $appDir = Join-Path $root 'rateb_mobile'
        $res = Join-Path $appDir 'android\app\src\main\res'
        $restore = @('rateb_mobile/android/app/src/main/res')
        $stringsFiles = @(Join-Path $res 'values\strings.xml')
    }
    'erp' {
        $appDir = Join-Path $root 'rateb-erp\capacitor'
        $res = Join-Path $appDir 'android\app\src\main\res'
        $restore = @('rateb-erp/capacitor/android/app/src/main/res', 'rateb-erp/capacitor/capacitor.config.json')
        $stringsFiles = @(Join-Path $res 'values\strings.xml')
    }
}

Push-Location $root
try {
    $dirty = git status --porcelain -- $restore
    if ($dirty) { throw "Local changes in app sources - commit or stash first:`n$dirty" }
} finally { Pop-Location }

Add-Type -AssemblyName System.Drawing

function Save-Icon {
    param([System.Drawing.Image]$Src, [int]$W, [int]$H, [double]$Scale, [string]$Bg, [string]$Shape, [string]$Out)
    $bmp = New-Object System.Drawing.Bitmap $W, $H, ([System.Drawing.Imaging.PixelFormat]::Format32bppArgb)
    $g = [System.Drawing.Graphics]::FromImage($bmp)
    $g.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
    $g.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
    $g.PixelOffsetMode = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality
    $g.Clear([System.Drawing.Color]::Transparent)
    if ($Bg) {
        $brush = New-Object System.Drawing.SolidBrush ([System.Drawing.ColorTranslator]::FromHtml($Bg))
        if ($Shape -eq 'circle') {
            $g.FillEllipse($brush, 0, 0, $W, $H)
        } elseif ($Shape -eq 'rounded') {
            $r = [int]([Math]::Min($W, $H) * 0.2)
            $path = New-Object System.Drawing.Drawing2D.GraphicsPath
            $path.AddArc(0, 0, 2 * $r, 2 * $r, 180, 90)
            $path.AddArc($W - 2 * $r, 0, 2 * $r, 2 * $r, 270, 90)
            $path.AddArc($W - 2 * $r, $H - 2 * $r, 2 * $r, 2 * $r, 0, 90)
            $path.AddArc(0, $H - 2 * $r, 2 * $r, 2 * $r, 90, 90)
            $path.CloseFigure()
            $g.FillPath($brush, $path)
            $path.Dispose()
        } else {
            $g.FillRectangle($brush, 0, 0, $W, $H)
        }
        $brush.Dispose()
    }
    $box = [Math]::Min($W, $H) * $Scale
    $ratio = [Math]::Min($box / $Src.Width, $box / $Src.Height)
    $dw = $Src.Width * $ratio
    $dh = $Src.Height * $ratio
    $g.DrawImage($Src, [single](($W - $dw) / 2), [single](($H - $dh) / 2), [single]$dw, [single]$dh)
    $g.Dispose()
    $bmp.Save($Out, [System.Drawing.Imaging.ImageFormat]::Png)
    $bmp.Dispose()
}

$iconFile = Join-Path ([IO.Path]::GetTempPath()) ("rateb-brand-{0}.img" -f $Key)
$apk = $null
try {
    Write-Host "=== $App for $Name ($Package) ===" -ForegroundColor Cyan
    Invoke-WebRequest -Uri $IconUrl -OutFile $iconFile -UseBasicParsing -TimeoutSec 60 -ErrorAction Stop
    try { $logo = [System.Drawing.Image]::FromFile($iconFile) } catch { throw 'Icon must be a PNG/JPG image' }

    $densities = [ordered]@{ mdpi = 48; hdpi = 72; xhdpi = 96; xxhdpi = 144; xxxhdpi = 192 }
    foreach ($d in $densities.Keys) {
        $size = $densities[$d]
        $dir = Join-Path $res "mipmap-$d"
        New-Item -ItemType Directory -Force -Path $dir | Out-Null
        Save-Icon $logo $size $size 0.74 '#FFFFFF' 'rounded' (Join-Path $dir 'ic_launcher.png')
        if (Test-Path (Join-Path $dir 'ic_launcher_round.png')) {
            Save-Icon $logo $size $size 0.64 '#FFFFFF' 'circle' (Join-Path $dir 'ic_launcher_round.png')
        }
        if (Test-Path (Join-Path $dir 'ic_launcher_foreground.png')) {
            $fg = [int]($size * 2.25)
            Save-Icon $logo $fg $fg 0.56 '' '' (Join-Path $dir 'ic_launcher_foreground.png')
        }
    }
    $bgXml = Join-Path $res 'values\ic_launcher_background.xml'
    if (Test-Path $bgXml) {
        $xml = [IO.File]::ReadAllText($bgXml) -replace '(<color name="ic_launcher_background">)[^<]*(</color>)', '${1}#FFFFFF${2}'
        [IO.File]::WriteAllText($bgXml, $xml, (New-Object Text.UTF8Encoding($false)))
    }
    Get-ChildItem $res -Recurse -Include 'splash.png', 'splash_logo.png' | ForEach-Object {
        $img = [System.Drawing.Image]::FromFile($_.FullName)
        $w = $img.Width; $h = $img.Height
        $img.Dispose()
        if ($_.Name -eq 'splash_logo.png') {
            Save-Icon $logo $w $h 0.9 '' '' $_.FullName
        } else {
            Save-Icon $logo $w $h 0.32 '#FFFFFF' '' $_.FullName
        }
    }
    $logo.Dispose()

    foreach ($file in $stringsFiles) {
        $xml = [IO.File]::ReadAllText($file, [Text.Encoding]::UTF8)
        $xml = $xml -replace '(<string name="(app_name|title_activity_main)">)[^<]*(</string>)', ('${1}' + $Name + '${3}')
        [IO.File]::WriteAllText($file, $xml, (New-Object Text.UTF8Encoding($false)))
    }

    # npm / gradle / flutter write warnings to stderr; success is judged by exit codes only.
    $ErrorActionPreference = 'Continue'
    $env:RATEB_BRAND_APP_ID = $Package
    Push-Location $appDir
    try {
        if ($App -eq 'erp') {
            $configPath = Join-Path $appDir 'capacitor.config.json'
            $config = [IO.File]::ReadAllText($configPath, [Text.Encoding]::UTF8) | ConvertFrom-Json
            $config.server.url = $Server
            $config | Add-Member -NotePropertyName appendUserAgent -NotePropertyValue "RatebBrand/$Key" -Force
            $config.android.backgroundColor = '#FFFFFF'
            $config.plugins.SplashScreen.backgroundColor = '#FFFFFF'
            [IO.File]::WriteAllText($configPath, ($config | ConvertTo-Json -Depth 20), (New-Object Text.UTF8Encoding($false)))
            npm run cap:sync
            if ($LASTEXITCODE -ne 0) { throw 'cap sync failed' }
            Push-Location (Join-Path $appDir 'android')
            try {
                & .\gradlew.bat assembleRelease
                if ($LASTEXITCODE -ne 0) { throw 'Gradle assembleRelease failed' }
            } finally { Pop-Location }
            $apk = Join-Path $appDir 'android\app\build\outputs\apk\release\app-release.apk'
        } else {
            if (-not $flutter) { throw 'Flutter SDK not found' }
            $defines = @("--dart-define=RATEB_BRAND_KEY=$Key")
            if ($Code) { $defines += "--dart-define=RATEB_ACTIVATION_CODE=$Code" }
            if ($App -eq 'hr') {
                & $flutter build apk --release --flavor production --target-platform android-arm64 `
                    --dart-define=APP_FLAVOR=production "--dart-define=ERP_BASE_URL=$Server" @defines
                $apk = Join-Path $appDir 'build\app\outputs\flutter-apk\app-production-release.apk'
            } else {
                & $flutter build apk --release --target-platform android-arm64 "--dart-define=RATEB_API_BASE_URL=$Server" @defines
                $apk = Join-Path $appDir 'build\app\outputs\flutter-apk\app-release.apk'
            }
            if ($LASTEXITCODE -ne 0) { throw 'flutter build failed' }
        }
    } finally { Pop-Location }
} finally {
    $env:RATEB_BRAND_APP_ID = $null
    try { if ([IO.File]::Exists($iconFile)) { [IO.File]::Delete($iconFile) } } catch { }
    Push-Location $root
    git checkout -- $restore
    git clean -fdq -- $restore
    Pop-Location
    if ($App -eq 'erp') {
        Push-Location $appDir
        npx cap copy android | Out-Null
        Pop-Location
    }
}

if (-not $apk -or -not (Test-Path $apk)) { throw "APK missing: $apk" }
$versionName = ''
$versionCode = 0
if ($aapt2) {
    $badging = (& $aapt2.FullName dump badging $apk | Select-Object -First 1)
    if ($badging -notmatch "name='$([regex]::Escape($Package))'") { throw "Built package does not match $Package" }
    if ($badging -match "versionCode='(\d+)'") { $versionCode = [int]$Matches[1] }
    if ($badging -match "versionName='([^']*)'") { $versionName = $Matches[1] }
}

New-Item -ItemType Directory -Force -Path $publishDir, $specDir | Out-Null
$dest = Join-Path $publishDir "$Key.apk"
Copy-Item -Force $apk $dest
$sha = (Get-FileHash -Algorithm SHA256 $dest).Hash.ToLowerInvariant()
$utf8 = New-Object Text.UTF8Encoding($false)
[IO.File]::WriteAllText((Join-Path $publishDir "$Key.json"), ([ordered]@{
    app = $App; file = "$Key.apk"; package = $Package; version = $versionName; version_code = $versionCode
    sha256 = $sha; size = (Get-Item $dest).Length
} | ConvertTo-Json), $utf8)
[IO.File]::WriteAllText((Join-Path $specDir "$Key.json"), ([ordered]@{
    app = $App; key = $Key; package = $Package; name = $Name; icon_url = $IconUrl; server = $Server; code = $Code
} | ConvertTo-Json), $utf8)

Write-Host ("OK -> {0} ({1} MB, {2} / {3})" -f $dest, [math]::Round((Get-Item $dest).Length / 1MB, 1), $versionName, $versionCode) -ForegroundColor Green
exit 0
