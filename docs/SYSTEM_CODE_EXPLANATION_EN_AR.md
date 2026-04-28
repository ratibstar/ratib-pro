# System Code Explanation (EN/AR)

## Purpose / الهدف
- EN: This guide explains what each major part of the system does (PHP, JS, CSS) so you can navigate quickly.
- AR: هذا الدليل يشرح وظيفة كل جزء رئيسي في النظام (PHP, JS, CSS) لتسهيل فهم المشروع بسرعة.

---

## 1) PHP Layer (Backend + Page Rendering) / طبقة PHP

### `pages/`
- EN: User-facing pages (home, login, dashboard, contact, notifications, communications, worker, etc.).
- AR: صفحات واجهة المستخدم (الرئيسية، تسجيل الدخول، لوحة التحكم، التواصل، الإشعارات، العامل...).

### `api/`
- EN: Endpoint layer for data operations (CRUD, reports, accounting, HR, notifications, workers, auth).
- AR: واجهات API لتنفيذ العمليات على البيانات (إضافة/تعديل/حذف، التقارير، المحاسبة، الموارد البشرية، الإشعارات، المصادقة).

### `includes/`
- EN: Shared bootstrap/config/helpers/middleware used by multiple pages and APIs.
- AR: ملفات مشتركة للإعدادات والوظائف المساعدة والـ middleware المستخدمة في أكثر من صفحة/واجهة.

### `control-panel/`
- EN: Country/control admin panel backend + APIs + views.
- AR: نظام لوحة التحكم للدول/الإدارة مع الصفحات وواجهات API الخاصة به.

### `admin/`
- EN: System-level administration and observability tooling (control center, metrics, timelines, alerts).
- AR: إدارة النظام العليا وأدوات المراقبة (مركز التحكم، المقاييس، السجل الزمني، التنبيهات).

---

## 2) JavaScript Layer (Client Behavior) / طبقة JavaScript

### `js/`
- EN: Frontend behavior for user pages (forms, tables, filters, chat widget, payment flow, etc.).
- AR: سلوك الواجهة لصفحات المستخدم (النماذج، الجداول، الفلاتر، المحادثة، الدفع...).

### `control-panel/js/`
- EN: Control-panel specific interactions (agencies, countries, support chats, permissions, settings).
- AR: تفاعلات مخصصة للوحة التحكم (المكاتب، الدول، الدعم، الصلاحيات، الإعدادات).

### `admin/assets/js/`
- EN: Admin control center interactions (live updates, event timeline, tenant ops).
- AR: تفاعلات مركز الإدارة (تحديثات مباشرة، السجل الزمني للأحداث، عمليات المستأجرين).

---

## 3) CSS Layer (Presentation) / طبقة CSS

### `css/`
- EN: Main site styling, shared UI components, page styles.
- AR: تنسيقات الموقع الرئيسية ومكونات الواجهة المشتركة وصفحات المستخدم.

### `css/pages/`
- EN: Page-scoped styles (for example home/customer-portal).
- AR: تنسيقات خاصة بكل صفحة (مثل home/customer-portal).

### `control-panel/css/`
- EN: Control panel visual system.
- AR: نظام التنسيقات الخاص بلوحة التحكم.

### `admin/assets/css/`
- EN: System admin/control-center visual system.
- AR: تنسيقات واجهات الإدارة ومركز التحكم.

---

## 4) How Data Flows / كيف تتحرك البيانات

1. EN: Browser loads a PHP page from `pages/` or `control-panel/pages/`.
   AR: المتصفح يحمّل صفحة PHP من `pages/` أو `control-panel/pages/`.
2. EN: Page includes CSS/JS assets.
   AR: الصفحة تربط ملفات CSS/JS.
3. EN: JS calls `api/...` endpoints for dynamic data.
   AR: JavaScript يستدعي واجهات `api/...` للحصول على/تعديل البيانات.
4. EN: API uses shared helpers in `includes/` and DB layer to return JSON.
   AR: API يستخدم الملفات المشتركة في `includes/` وقاعدة البيانات ثم يعيد JSON.
5. EN: JS updates DOM based on response.
   AR: JavaScript يحدّث الواجهة بناءً على الاستجابة.

---

## 5) Comment Policy Applied / سياسة الشرح المطبقة

- EN: Every file now starts with a bilingual purpose header (EN/AR) where possible.
- AR: كل ملف يبدأ الآن بترويسة تعريف ثنائية اللغة (EN/AR) حيثما أمكن.
- EN: Keep comments concise and section-focused; avoid noisy line-by-line comments.
- AR: التعليقات مختصرة وتركّز على الأقسام المهمة؛ بدون تعليق على كل سطر.

---

## 6) Reading Order for New Developers / ترتيب القراءة للمطور الجديد

1. `includes/config.php` (bootstrap/config)
2. `pages/home.php` + `js/pages/home-page.js` + `css/pages/home-public.css`
3. `pages/contact.php` + `js/contact.js` + `css/contact.css`
4. `pages/notifications.php` + `js/notifications.js` + `css/notifications.css`
5. `control-panel/pages/control/dashboard.php` + matching JS/CSS
6. `admin/control-center.php` + `admin/assets/js/control-center.js` + `admin/assets/css/control-center.css`

EN: This sequence gives the fastest understanding of architecture and conventions.
AR: هذا التسلسل يمنح أسرع فهم لبنية النظام ومعايير كتابة الكود.
