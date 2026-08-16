# 🔧 حل مشكلة شاشة البيع (POS Screen Not Opening)

## ⚠️ تقرير المشاكل المكتشفة

من التشخيص، وجدنا **مشكلتان حرجتان**:

### 1️⃣ **MySQLi Extension غير محمل** ❌
- **الوضع الحالي**: `mysqli` extension لم يتم تحميله
- **التأثير**: رسائل خطأ `Class "mysqli" not found` في السجلات
- **الحل**: تثبيت امتداد MySQLi في PHP 8.4

### 2️⃣ **خادم MySQL غير متاح** ❌  
- **الخطأ**: `SQLSTATE[HY000] [2002] No connection could be made because the target machine actively refused it`
- **السبب المحتمل**: 
  - خادم MySQL لم يتم تشغيله
  - خادم MySQL توقف أو معطوب
  - بيانات الاتصال خاطئة
- **الحل**: تشغيل خادم MySQL وتصحيح إعدادات الاتصال

---

## 🛠️ خطوات الحل

### الخطوة 1: تثبيت MySQLi Extension

#### على Windows (مع XAMPP):
```powershell
# 1. تحديد موقع php.ini
php -r "echo php_ini_loaded_file();"

# 2. فتح ملف php.ini وابحث عن السطر:
# ;extension=mysqli
# أزل الفاصلة المنقوطة:
# extension=mysqli

# 3. أعد تشغيل Apache
```

#### على Windows (مع Laragon):
```powershell
# في لوحة Laragon، اذهب إلى:
# Quick settings > PHP → تأكد من تفعيل mysqli
```

#### على Linux:
```bash
# Ubuntu/Debian
sudo apt-get install php8.4-mysqli

# CentOS/RHEL
sudo yum install php84-php-mysqli

# بعد التثبيت، أعد تشغيل PHP-FPM:
sudo systemctl restart php8.4-fpm
# أو Apache:
sudo systemctl restart apache2
```

---

### الخطوة 2: تشغيل خادم MySQL

#### على Windows:
```powershell
# مع XAMPP
Start-Process "C:\xampp\mysql\bin\mysqld.exe"

# أو عبر قائمة الخدمات
Get-Service MySQL* | Start-Service

# أو عبر PowerShell كمسؤول:
Start-Service MySQL80  # غيّر الرقم حسب الإصدار
```

#### على Linux:
```bash
# Ubuntu/Debian
sudo systemctl start mysql
sudo systemctl start mariadb  # إذا كنت تستخدم MariaDB

# CentOS/RHEL
sudo systemctl start mysqld

# التحقق من الحالة:
sudo systemctl status mysql
```

#### على macOS:
```bash
# مع Homebrew
brew services start mysql

# أو يدويًا:
mysql.server start
```

---

### الخطوة 3: التحقق من بيانات الاتصال بقاعدة البيانات

```php
<?php
// تحقق من الإعدادات الحالية
echo "Host: " . (defined('DB_HOST') ? DB_HOST : 'Not defined') . "\n";
echo "Port: " . (defined('DB_PORT') ? DB_PORT : '3306') . "\n";
echo "Database: " . (defined('DB_NAME') ? DB_NAME : 'Not defined') . "\n";
echo "User: " . (defined('DB_USER') ? DB_USER : 'Not defined') . "\n";
?>
```

**الإعدادات في ملف**: `config/env/bangladesh_rateb_sa.php`

---

### الخطوة 4: اختبار الاتصال

```powershell
# افتح PowerShell في مجلد المشروع:
cd "c:\Users\انا\Documents\ratibprogram"

# شغّل اختبار الاتصال:
php diagnose-pos-issue.php
```

**يجب أن تحصل على**:
```json
{
  "extensions": {
    "mysqli": { "loaded": true },      ✓
    "pdo": { "loaded": true },         ✓
    "pdo_mysql": { "loaded": true }    ✓
  },
  "database": {
    "connection": "نجح الاتصال بقاعدة البيانات ✓",
    "status": "ok"
  }
}
```

---

## ✅ اختبار شاشة البيع بعد الحل

بعد إتمام الخطوات السابقة:

### 1. امسح ذاكرة التخزين المؤقتة:
```powershell
Remove-Item -Path "c:\Users\انا\Documents\ratibprogram\cache\*" -Force -Recurse
Remove-Item -Path "c:\Users\انا\Documents\ratibprogram\storage\*" -Force -Recurse
```

### 2. افتح شاشة البيع:
```
https://your-domain.com/pos/register
# أو
http://localhost/ratibprogram/pages/pos
```

### 3. تحقق من السجلات للأخطاء:
```powershell
# ابدأ بآخر 50 سطر:
tail -50 "c:\Users\انا\Documents\ratibprogram\logs\php-errors.log"
```

---

## 🚨 استكشاف الأخطاء المتقدم

### إذا استمرت المشكلة:

#### 1️⃣ تحقق من ملف PHP.INI:
```powershell
php --ini
# يجب أن تظهر قائمة بملفات التكوين المحملة
```

#### 2️⃣ تحقق من ملفات السجلات:
```powershell
# أخطاء PHP:
Get-Content "c:\Users\انا\Documents\ratibprogram\logs\php-errors.log" -Tail 100

# سجلات MySQL:
# على Windows (مع XAMPP):
Get-Content "c:\xampp\mysql\data\*.err" -Tail 50
```

#### 3️⃣ تشغيل اختبار الاتصال المباشر:
```powershell
mysql -h localhost -u admin_rateb -p -D admin_bangladesh
# أدخل كلمة المرور عند الطلب
# إذا نجحت، اكتب: EXIT
```

#### 4️⃣ فحص جداول قاعدة البيانات:
```sql
SHOW TABLES LIKE 'rateb_pos%';
-- يجب أن تظهر جداول مثل:
-- rateb_pos_terminals
-- rateb_pos_shifts
-- rateb_pos_orders
```

---

## 📋 قائمة تدقيق الإصلاح

- [ ] تم تثبيت MySQLi extension
- [ ] تم تشغيل خادم MySQL
- [ ] تم التحقق من اتصال قاعدة البيانات
- [ ] جرى اختبار `diagnose-pos-issue.php` بنجاح
- [ ] تم مسح الذاكرة المؤقتة
- [ ] تم فتح شاشة البيع بنجاح
- [ ] لا توجد أخطاء في السجلات

---

## 📞 معلومات الدعم الإضافية

**الملفات ذات الصلة بشاشة البيع**:
- المسار الرئيسي: `rateb-erp/modules/pos/`
- ملف التكوين: `rateb-erp/modules/pos/config/`
- أداة التشخيص: `diagnose-pos-issue.php`
- السجلات: `logs/php-errors.log`

**رابط شاشة البيع**:
```
http://localhost/ratibprogram/pages/pos
# أو (V2):
http://localhost/ratibprogram/pages/pos/v2
```

---

## 🔗 المراجع السريعة

| المشكلة | الحل |
|--------|-----|
| `Class "mysqli" not found` | تثبيت MySQLi Extension |
| `Connection refused` | تشغيل MySQL Server |
| `Access denied for user` | تصحيح بيانات الاتصال في `.env` |
| `Unknown database` | تأكد من وجود قاعدة البيانات `admin_bangladesh` |

---

**آخر تحديث**: 2026-08-16  
**الحالة**: جاهز للاستخدام
