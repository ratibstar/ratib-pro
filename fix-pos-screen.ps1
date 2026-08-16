#!/usr/bin/env powershell
# POS Screen Fix Script - Windows
# استعادة شاشة البيع - Windows

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

Write-Host "`n=====================================`n" -ForegroundColor Cyan
Write-Host "🔧 أداة إصلاح شاشة البيع (POS Screen Fix)" -ForegroundColor Green
Write-Host "=====================================`n" -ForegroundColor Cyan

# 1. Check PHP Extensions
Write-Host "1️⃣  فحص امتدادات PHP..." -ForegroundColor Yellow

$phpExtensions = php -r "
`$ext = ['mysqli' => 'mysqli', 'pdo' => 'pdo', 'pdo_mysql' => 'pdo_mysql'];
foreach(`$ext as `$name => `$ext_name) {
    `$loaded = extension_loaded(`$ext_name);
    echo `$name . ':' . (`$loaded ? 'yes' : 'no') . \"\n\";
}
"

$extensions = @{}
foreach ($line in $phpExtensions -split "`n") {
    if ($line -match '(.+):(.+)') {
        $extensions[$Matches[1]] = $Matches[2] -eq 'yes'
    }
}

Write-Host "  ✓ MySQLi: $(if($extensions['mysqli']) { 'محمل ✅' } else { 'غير محمل ❌' })" -ForegroundColor $(if($extensions['mysqli']) { 'Green' } else { 'Red' })
Write-Host "  ✓ PDO: $(if($extensions['pdo']) { 'محمل ✅' } else { 'غير محمل ❌' })" -ForegroundColor $(if($extensions['pdo']) { 'Green' } else { 'Red' })
Write-Host "  ✓ PDO MySQL: $(if($extensions['pdo_mysql']) { 'محمل ✅' } else { 'غير محمل ❌' })" -ForegroundColor $(if($extensions['pdo_mysql']) { 'Green' } else { 'Red' })

# 2. Detect PHP Installation
Write-Host "`n2️⃣  البحث عن تثبيت PHP..." -ForegroundColor Yellow

$phpIni = php -r "echo php_ini_loaded_file();"
Write-Host "  PHP.INI: $phpIni" -ForegroundColor Cyan

# Check for XAMPP
if (Test-Path "C:\xampp\php.ini") {
    Write-Host "  ✓ تم اكتشاف XAMPP في C:\xampp" -ForegroundColor Green
    $phpIni = "C:\xampp\php.ini"
}
# Check for Laragon
elseif (Test-Path "C:\laragon\bin\php\*\php.ini") {
    Write-Host "  ✓ تم اكتشاف Laragon" -ForegroundColor Green
    $phpIni = Get-Item "C:\laragon\bin\php\*\php.ini" | Select-Object -First 1 -ExpandProperty FullName
}

# 3. Enable MySQLi Extension
if (-not $extensions['mysqli']) {
    Write-Host "`n3️⃣  تفعيل امتداد MySQLi..." -ForegroundColor Yellow
    
    if (Test-Path $phpIni) {
        # Backup original
        Copy-Item $phpIni "$phpIni.backup" -Force
        Write-Host "  ✓ تم حفظ نسخة احتياطية: $phpIni.backup" -ForegroundColor Cyan
        
        # Read and modify
        $content = Get-Content $phpIni -Raw
        
        # Try different mysqli line patterns
        $patterns = @(
            '^;extension=mysqli$',
            '^;extension=php_mysqli\.dll$',
            '^; extension=mysqli$'
        )
        
        $modified = $false
        foreach ($pattern in $patterns) {
            if ($content -match $pattern) {
                $content = $content -replace $pattern, 'extension=mysqli'
                $modified = $true
                break
            }
        }
        
        if ($modified) {
            Set-Content $phpIni $content -Force
            Write-Host "  ✓ تم تفعيل MySQLi في php.ini" -ForegroundColor Green
        } else {
            Write-Host "  ⚠️  لم يتم العثور على سطر mysqli في php.ini" -ForegroundColor Yellow
            Write-Host "  💡 أضف السطر التالي يدويًا في php.ini:" -ForegroundColor Cyan
            Write-Host "     extension=mysqli" -ForegroundColor White
        }
    } else {
        Write-Host "  ❌ لم يتم العثور على php.ini" -ForegroundColor Red
        Write-Host "  💡 حاول تثبيت php-mysqli يدويًا عبر:" -ForegroundColor Cyan
        Write-Host "     pecl install mysqli" -ForegroundColor White
    }
} else {
    Write-Host "`n3️⃣  امتداد MySQLi مفعّل بالفعل ✅" -ForegroundColor Green
}

# 4. Check MySQL Service
Write-Host "`n4️⃣  فحص خادم MySQL..." -ForegroundColor Yellow

$mysqlServices = @(
    'MySQL80', 'MySQL81', 'MySQL82', 'MySQL84', 'MariaDB', 'MySQL'
)

$serviceRunning = $false
foreach ($svc in $mysqlServices) {
    try {
        $service = Get-Service -Name $svc -ErrorAction SilentlyContinue
        if ($service) {
            Write-Host "  ✓ تم اكتشاف: $svc (الحالة: $($service.Status))" -ForegroundColor Cyan
            if ($service.Status -eq 'Stopped') {
                Write-Host "    🔄 جاري تشغيل الخدمة..." -ForegroundColor Yellow
                Start-Service -Name $svc -ErrorAction SilentlyContinue
                Start-Sleep -Seconds 2
                $service = Get-Service -Name $svc
                if ($service.Status -eq 'Running') {
                    Write-Host "    ✅ تم تشغيل الخدمة بنجاح" -ForegroundColor Green
                    $serviceRunning = $true
                }
            } else {
                $serviceRunning = $true
            }
        }
    } catch {
        # Service not found, continue
    }
}

if (-not $serviceRunning) {
    Write-Host "  ⚠️  لم يتم اكتشاف خدمة MySQL قيد التشغيل" -ForegroundColor Yellow
    Write-Host "  💡 حاول تشغيل MySQL يدويًا:" -ForegroundColor Cyan
    Write-Host "     مع XAMPP: اضغط 'Start' في لوحة التحكم" -ForegroundColor White
}

# 5. Test Database Connection
Write-Host "`n5️⃣  اختبار اتصال قاعدة البيانات..." -ForegroundColor Yellow

$testResult = php -r "
require 'diagnose-pos-issue.php';
" 2>&1

if ($testResult -match '"connection".*ok') {
    Write-Host "  ✅ اتصال قاعدة البيانات ناجح!" -ForegroundColor Green
} elseif ($testResult -match '"connection".*error') {
    Write-Host "  ❌ فشل الاتصال بقاعدة البيانات" -ForegroundColor Red
    Write-Host "  💡 تحقق من:" -ForegroundColor Cyan
    Write-Host "     - تشغيل خادم MySQL" -ForegroundColor White
    Write-Host "     - بيانات الاتصال في config/env/" -ForegroundColor White
} else {
    Write-Host "  ⚠️  لم يتم التمكن من اختبار الاتصال" -ForegroundColor Yellow
}

# 6. Clear Cache
Write-Host "`n6️⃣  مسح الذاكرة المؤقتة..." -ForegroundColor Yellow

$cacheDirs = @(
    ".\cache",
    ".\storage",
    ".\logs\*.log"
)

foreach ($dir in $cacheDirs) {
    if (Test-Path $dir) {
        try {
            if ($dir -like '*.log') {
                Remove-Item $dir -Force -ErrorAction SilentlyContinue
                Write-Host "  ✓ تم مسح السجلات" -ForegroundColor Green
            } else {
                Remove-Item -Path $dir -Recurse -Force -ErrorAction SilentlyContinue
                Write-Host "  ✓ تم مسح: $dir" -ForegroundColor Green
            }
        } catch {
            Write-Host "  ⚠️  لم يتمكن من مسح: $dir" -ForegroundColor Yellow
        }
    }
}

# Summary
Write-Host "`n=====================================`n" -ForegroundColor Cyan
Write-Host "📋 ملخص الإصلاح:" -ForegroundColor Green
Write-Host "`n✅ تم إنجاز الخطوات التالية:" -ForegroundColor Green
Write-Host "  ✓ فحص امتدادات PHP" -ForegroundColor White
Write-Host "  ✓ تحديد تثبيت PHP" -ForegroundColor White
if ($modified) {
    Write-Host "  ✓ تفعيل امتداد MySQLi" -ForegroundColor White
}
Write-Host "  ✓ فحص خادم MySQL" -ForegroundColor White
Write-Host "  ✓ اختبار الاتصال بقاعدة البيانات" -ForegroundColor White
Write-Host "  ✓ مسح الذاكرة المؤقتة" -ForegroundColor White

Write-Host "`n🔄 الخطوات التالية المطلوبة:" -ForegroundColor Yellow
Write-Host "  1. أعد تشغيل خادم الويب (Apache/IIS)" -ForegroundColor White
Write-Host "  2. احذف ذاكرة التخزين المؤقت للمتصفح" -ForegroundColor White
Write-Host "  3. افتح شاشة البيع: http://localhost/ratibprogram/pages/pos" -ForegroundColor White

Write-Host "`n📌 إذا استمرت المشكلة:" -ForegroundColor Cyan
Write-Host "  ✓ اقرأ دليل الحل الكامل: FIX_POS_SCREEN_GUIDE.md" -ForegroundColor White
Write-Host "  ✓ شغّل أداة التشخيص: php diagnose-pos-issue.php" -ForegroundColor White
Write-Host "  ✓ افحص السجلات: logs/php-errors.log" -ForegroundColor White

Write-Host "`n=====================================`n" -ForegroundColor Cyan
Write-Host "✅ انتهت الأداة. اضغط أي زر للإغلاق..." -ForegroundColor Green

$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
