# Ratib Control Panel - Complete User Guide (AR/EN)

> Version: 1.0  
> Audience: Control Panel Operators, Country Admins, Super Admin

---

## 1) Overview | نظرة عامة

### EN
Ratib Control Panel is the central administration portal for managing:
- Countries
- Agencies
- Country users
- Registration requests
- Support chats
- Worker tracking
- Accounting
- Control admins and permissions

The panel supports **country-based isolation**. If your account is restricted to one country (or you selected one country in session), you should only see that country data without overlap.

### AR
لوحة تحكم راتب هي البوابة الإدارية المركزية لإدارة:
- الدول
- المكاتب (Agencies)
- مستخدمي الدول
- طلبات التسجيل
- محادثات الدعم
- تتبع العمال
- المحاسبة
- مسؤولي لوحة التحكم والصلاحيات

اللوحة تدعم **العزل حسب الدولة**. إذا كان حسابك مقيدًا على دولة واحدة (أو اخترت دولة محددة في الجلسة)، ستظهر لك بيانات هذه الدولة فقط بدون تداخل.

---

## 2) Login and Session Flow | تسجيل الدخول ومسار الجلسة

### EN
1. Open control panel login page.
2. Sign in using control admin credentials.
3. If your role requires country context, select country.
4. If needed, select agency.
5. You are redirected to dashboard with scoped visibility.

### AR
1. افتح صفحة تسجيل دخول لوحة التحكم.
2. سجل الدخول ببيانات مسؤول لوحة التحكم.
3. إذا كانت صلاحياتك تحتاج سياق دولة، اختر الدولة.
4. عند الحاجة، اختر المكتب.
5. سيتم توجيهك للوحة الرئيسية مع تطبيق نطاق الرؤية حسب صلاحياتك.

---

## 3) Country Scope Rules | قواعد نطاق الدولة

### EN
- `control_select_country` (or global admin): can operate all countries.
- `country_{slug}` permissions: can operate only assigned countries.
- Session country (`control_country_id`) pins the workspace to one country in many screens.
- If session is pinned, lists and dropdowns should not mix other countries.

### AR
- صلاحية `control_select_country` (أو مدير عام): تسمح بالعمل على كل الدول.
- صلاحيات `country_{slug}`: تسمح بالعمل فقط على الدول المخصصة.
- الدولة المختارة في الجلسة (`control_country_id`) تثبت مساحة العمل على دولة واحدة في كثير من الشاشات.
- عند تثبيت الجلسة على دولة، يجب ألا تختلط بيانات أو قوائم الدول الأخرى.

---

## 4) Main Modules | الوحدات الرئيسية

## 4.1 Dashboard | لوحة المعلومات

### EN
Use Dashboard for quick status:
- Number of countries/agencies in your scope
- Pending registration requests
- Quick navigation cards
- High-level health indicators

### AR
استخدم لوحة المعلومات لمتابعة الحالة السريعة:
- عدد الدول/المكاتب ضمن نطاقك
- طلبات التسجيل المعلقة
- بطاقات التنقل السريع
- مؤشرات الحالة العامة

---

## 4.2 Countries Management | إدارة الدول

### EN
In Countries page, you can:
- View country list (scoped)
- Add country (if permitted)
- Edit country data (name, slug, status)
- Activate/deactivate country

Best practice:
- Keep `slug` stable after go-live.
- Use consistent naming across systems.

### AR
في صفحة الدول يمكنك:
- عرض قائمة الدول (ضمن النطاق)
- إضافة دولة (حسب الصلاحية)
- تعديل بيانات الدولة (الاسم، slug، الحالة)
- تفعيل/تعطيل الدولة

أفضل ممارسة:
- لا تغيّر `slug` بعد التشغيل الفعلي إلا عند الضرورة.
- حافظ على تسمية موحدة بين الأنظمة.

---

## 4.3 Agencies Management | إدارة المكاتب

### EN
In Agencies page:
- Create agency linked to a country
- Manage credentials and DB mapping (`db_host`, `db_name`, etc.)
- Activate/suspend agency
- Open agency context

Important:
- Each agency can map to a specific country program DB.
- Wrong DB mapping causes missing users/workers.

### AR
في صفحة المكاتب:
- إنشاء مكتب مرتبط بدولة
- إدارة بيانات الربط وقاعدة البيانات (`db_host`, `db_name` وغيرها)
- تفعيل/تعليق المكتب
- فتح سياق المكتب

مهم:
- كل مكتب يمكن ربطه بقاعدة برنامج خاصة بدولة/مكتب.
- أي خطأ في الربط يسبب نقص في عرض المستخدمين/العمال.

---

## 4.4 Country Users | مستخدمو الدول

### EN
Use Country Users module to:
- View users per country/agency
- Add/update users
- Control activation status

Scope behavior:
- You can only manage users in agencies/countries allowed by your role.

### AR
استخدم وحدة مستخدمي الدول من أجل:
- عرض المستخدمين لكل دولة/مكتب
- إضافة/تحديث المستخدمين
- التحكم بحالة التفعيل

سلوك النطاق:
- يمكنك إدارة المستخدمين فقط ضمن المكاتب/الدول المسموح لك بها.

---

## 4.5 Registration Requests | طلبات التسجيل

### EN
In registration requests:
- Filter by status/country/date
- Review applicant details
- Approve/reject request
- Follow payment status

Operational note:
- Queue visibility is country-scoped for restricted operators.

### AR
في طلبات التسجيل:
- فلترة حسب الحالة/الدولة/التاريخ
- مراجعة بيانات مقدم الطلب
- قبول/رفض الطلب
- متابعة حالة الدفع

ملاحظة تشغيلية:
- رؤية قائمة الطلبات تكون ضمن نطاق الدولة للمستخدمين المقيدين.

---

## 4.6 Support Chats | محادثات الدعم

### EN
Support module allows:
- List open/closed chats
- Read conversation messages
- Reply as admin
- Track unread counts

Scope:
- Non-global users see only allowed country chats.

### AR
وحدة الدعم تتيح:
- عرض المحادثات المفتوحة/المغلقة
- قراءة الرسائل
- الرد كمسؤول
- متابعة عدد الرسائل غير المقروءة

النطاق:
- المستخدم غير العام يرى فقط محادثات الدولة المسموح بها.

---

## 4.7 Worker Tracking | تتبع العمال

### EN
Tracking module includes:
- Latest status map/list
- Alerts stream
- Worker history
- Geofences
- Health metrics

Country/DB behavior:
- Control DB is used for country/tenant scoping.
- Worker info is enriched from agency/country program DB.
- Search should avoid cross-country ID overlap.

### AR
وحدة التتبع تشمل:
- آخر حالة للعمال (خريطة/قائمة)
- تدفق التنبيهات
- سجل حركة العامل
- الأسوار الجغرافية
- مؤشرات الصحة

سلوك الدولة/قاعدة البيانات:
- يتم استخدام قاعدة التحكم لتحديد نطاق الدولة/المستأجر.
- يتم جلب بيانات العامل التفصيلية من قاعدة البرنامج المرتبطة بالمكتب/الدولة.
- البحث يجب أن يمنع تداخل معرفات العمال بين الدول.

---

## 4.8 Accounting | المحاسبة

### EN
Accounting module supports:
- Overview totals
- By-country / by-agency summaries
- Transactions list
- Journal entries and approvals
- Exports (CSV)

Scope:
- Country filters and exports must follow your allowed scope/session country.

### AR
وحدة المحاسبة تدعم:
- ملخصات عامة
- تقارير حسب الدولة / حسب المكتب
- قائمة العمليات
- قيود اليومية والموافقات
- التصدير (CSV)

النطاق:
- الفلاتر والتصدير يجب أن يتبعان نطاق صلاحياتك/الدولة المختارة في الجلسة.

---

## 4.9 Control Admins and Permissions | مسؤولو لوحة التحكم والصلاحيات

### EN
Admins module lets you:
- Create control admin accounts
- Assign active/inactive state
- Assign country (if column exists)
- Manage permissions group/user-level

Permission model basics:
- Parent permissions grant child permissions.
- `*` means full access.
- Country access can be global or slug-based (`country_xxx`).

### AR
وحدة مسؤولي لوحة التحكم تتيح:
- إنشاء حسابات مسؤولي لوحة التحكم
- تحديد حالة التفعيل
- ربط المسؤول بدولة (إن كان العمود موجودًا)
- إدارة الصلاحيات على مستوى المجموعات/المستخدم

أساسيات نموذج الصلاحيات:
- الصلاحية الرئيسية قد تمنح صلاحيات فرعية.
- `*` تعني وصول كامل.
- وصول الدول إما عام أو بالـ slug (`country_xxx`).

---

## 5) Typical Daily Workflow | سيناريو عمل يومي مقترح

### EN
1. Login.
2. Confirm selected country (if applicable).
3. Check dashboard KPIs.
4. Process pending registration requests.
5. Review support unread chats.
6. Monitor worker tracking alerts.
7. Reconcile accounting operations.
8. Logout.

### AR
1. تسجيل الدخول.
2. التأكد من الدولة المختارة (إذا ينطبق).
3. مراجعة مؤشرات لوحة المعلومات.
4. معالجة طلبات التسجيل المعلقة.
5. مراجعة محادثات الدعم غير المقروءة.
6. متابعة تنبيهات تتبع العمال.
7. مطابقة العمليات المحاسبية.
8. تسجيل الخروج.

---

## 6) Troubleshooting | استكشاف الأخطاء

### EN
Problem: I see data from other countries.
- Verify your permissions (`country_slug` vs `control_select_country`).
- Verify session country is selected.
- Re-login after permission changes.

Problem: Worker names are missing in tracking.
- Check agency DB mapping in `control_agencies`.
- Ensure `workers` table exists in target DB.

Problem: Empty lists after scope updates.
- Check if your scope became empty (`[]`) due to missing country permissions.

### AR
مشكلة: تظهر لي بيانات دول أخرى.
- تحقق من الصلاحيات (`country_slug` مقابل `control_select_country`).
- تأكد من اختيار الدولة في الجلسة.
- أعد تسجيل الدخول بعد تعديل الصلاحيات.

مشكلة: أسماء العمال لا تظهر في التتبع.
- راجع ربط قاعدة بيانات المكتب في `control_agencies`.
- تأكد من وجود جدول `workers` في القاعدة الهدف.

مشكلة: القوائم فارغة بعد تحديث النطاق.
- تحقق أن نطاقك لم يصبح فارغًا (`[]`) بسبب نقص صلاحيات الدول.

---

## 7) Security and Operations Notes | ملاحظات أمنية وتشغيلية

### EN
- Never share control admin credentials.
- Apply least privilege permissions.
- Keep country scope strict to avoid accidental cross-country actions.
- Audit critical actions (approve/reject/delete).

### AR
- لا تشارك بيانات دخول مسؤول لوحة التحكم.
- طبّق أقل صلاحية ممكنة (Least Privilege).
- حافظ على نطاق دولة صارم لتجنب أي إجراءات خاطئة بين الدول.
- راجع سجلات العمليات الحساسة (قبول/رفض/حذف).

---

## 8) Quick URL Reference | مرجع روابط سريع

### EN
- Login: `/control-panel/pages/login.php`
- Dashboard: `/control-panel/pages/control/dashboard.php`
- Countries: `/control-panel/pages/control/countries.php`
- Agencies: `/control-panel/pages/control/agencies.php`
- Registration Requests: `/control-panel/pages/control/registration-requests.php`
- Support Chats: `/control-panel/pages/control/support-chats.php`
- Tracking: `/control-panel/pages/control/tracking-map.php`
- Accounting: `/control-panel/pages/control/accounting.php`
- Admins: `/control-panel/pages/control/admins.php`

### AR
- تسجيل الدخول: `/control-panel/pages/login.php`
- لوحة المعلومات: `/control-panel/pages/control/dashboard.php`
- الدول: `/control-panel/pages/control/countries.php`
- المكاتب: `/control-panel/pages/control/agencies.php`
- طلبات التسجيل: `/control-panel/pages/control/registration-requests.php`
- الدعم: `/control-panel/pages/control/support-chats.php`
- التتبع: `/control-panel/pages/control/tracking-map.php`
- المحاسبة: `/control-panel/pages/control/accounting.php`
- مسؤولو اللوحة: `/control-panel/pages/control/admins.php`

---

## 9) Admin Country Isolation Policy (Recommended) | سياسة عزل الدول للمشرف العام (مُوصى بها)

### EN
To avoid overlap while still keeping full control:
1. Keep full permissions for admin.
2. Force selecting one workspace country per session.
3. Apply session country scope to lists/dropdowns/apis.
4. Switch country explicitly when moving to another workspace.

### AR
لتجنب التداخل مع بقاء التحكم الكامل:
1. أبقِ صلاحيات المشرف كاملة.
2. اجعل اختيار دولة العمل إجباريًا لكل جلسة.
3. طبّق نطاق دولة الجلسة على القوائم/الحقول/واجهات API.
4. غيّر الدولة بشكل صريح عند الانتقال إلى دولة أخرى.

---

## 10) Team Onboarding Checklist | قائمة تجهيز عضو جديد

### EN
- [ ] Create control admin user
- [ ] Assign permission group
- [ ] Assign country access (`country_slug` or global)
- [ ] Test visibility in dashboard, requests, support, tracking
- [ ] Confirm no cross-country overlap

### AR
- [ ] إنشاء مستخدم مسؤول لوحة التحكم
- [ ] تعيين مجموعة الصلاحيات
- [ ] تعيين نطاق الدول (`country_slug` أو عام)
- [ ] اختبار الرؤية في لوحة المعلومات، الطلبات، الدعم، التتبع
- [ ] التأكد من عدم وجود تداخل بين الدول

