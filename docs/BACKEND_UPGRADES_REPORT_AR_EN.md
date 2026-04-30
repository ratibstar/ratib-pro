# Backend Upgrades Report (AR/EN)

> Date: 2026-04-30  
> Scope: Platform backend architecture upgrades  
> Format: Arabic + English

---

## 1) Executive Summary | الملخص التنفيذي

### EN
A comprehensive backend upgrade was implemented across:
- Country isolation and tenant-safe data access
- IAM (roles/permissions/inheritance)
- Security hardening (rate limit, 2FA, request signing, IP restriction)
- Realtime events (SSE)
- Alert intelligence (scoring, dedupe, grouping, escalation)
- Workflow observability APIs
- External versioned API (`/api/v1`)
- Webhook subscriptions and async delivery

### AR
تم تنفيذ ترقية شاملة للبنية الخلفية وتشمل:
- عزل الدول والوصول الآمن لبيانات المستأجرين
- نظام الهوية والصلاحيات (IAM)
- تعزيزات الأمان (تحديد معدل الطلبات، 2FA، توقيع الطلبات، تقييد IP)
- بث الأحداث بشكل لحظي (SSE)
- طبقة ذكاء التنبيهات (تقييم، إزالة التكرار، التجميع، التصعيد)
- واجهات مراقبة سير العمل
- API خارجي بإصدار (`/api/v1`)
- نظام Webhooks مع إرسال غير متزامن

---

## 2) Country Isolation Enhancements | تحسينات عزل الدول

### EN
- Country scope helpers were centralized and improved.
- Worker tracking now uses proper tenant/country program DB resolution.
- Search and enrichment were hardened to avoid cross-country worker-ID collisions.
- Session-pinned country context was applied for non-overlapping admin workflows.

### AR
- تم توحيد وتحسين مساعدات نطاق الدولة.
- تتبع العمال أصبح يستخدم قاعدة البرنامج الصحيحة حسب المستأجر/الدولة.
- تم تقوية البحث والإثراء لمنع تداخل معرفات العمال بين الدول.
- تم تطبيق تثبيت سياق الدولة في الجلسة لتجنب التداخل حتى للمشرف العام.

---

## 3) IAM Layer | طبقة الهوية والصلاحيات

### EN
Implemented a production-style IAM layer:
- `roles`, `permissions`, `role_permissions`, `user_roles`
- Role inheritance support
- Wildcard permission (`*`)
- Central authorization via `AuthorizationService`
- Session audit repository added

### AR
تم تنفيذ طبقة IAM احترافية:
- جداول: `roles`, `permissions`, `role_permissions`, `user_roles`
- دعم وراثة الأدوار
- دعم صلاحية شاملة (`*`)
- تفويض مركزي عبر `AuthorizationService`
- إضافة مستودع تدقيق الجلسات

**Main files | الملفات الرئيسية**
- `app/Services/AuthorizationService.php`
- `app/Repositories/SessionRepository.php`
- `app/Middleware/AccessMiddleware.php`
- `docs/IAM_SCHEMA.sql`

---

## 4) Security Hardening | تعزيزات الأمان

### EN
Added middleware/service-based protection:
- Rate limiting (IP + user + external client)
- TOTP 2FA optional per role
- Login audit logging (success/failure, IP, timestamp)
- HMAC request signing for government mode sensitive actions
- IP allow/deny restrictions

### AR
تمت إضافة حماية عبر middleware/services:
- تحديد معدل الطلبات (IP + مستخدم + عميل خارجي)
- 2FA بنمط TOTP اختياري حسب الدور
- تسجيل محاولات الدخول (نجاح/فشل + IP + وقت)
- توقيع HMAC للطلبات الحساسة في وضع الحكومة
- قوائم السماح/المنع لعناوين IP

**Main files | الملفات الرئيسية**
- `app/Services/RateLimiterService.php`
- `app/Services/TwoFactorService.php`
- `app/Services/RequestSigningService.php`
- `app/Services/IpRestrictionService.php`
- `app/Services/ApiTokenService.php`
- `app/Middleware/SecurityMiddleware.php`
- `app/Middleware/ExternalApiMiddleware.php`
- `docs/SECURITY_HARDENING_SCHEMA.sql`

---

## 5) Realtime Layer (SSE) | طبقة الاتصال اللحظي

### EN
Implemented SSE realtime layer with EventDispatcher integration:
- Streams: worker movement, alerts, workflow status
- Subscription endpoint for frontend
- Event log + cursor based streaming

### AR
تم تنفيذ طبقة بث لحظي عبر SSE ومربوطة مع EventDispatcher:
- قنوات البث: حركة العامل، التنبيهات، حالة سير العمل
- نقطة اشتراك للواجهة الأمامية
- بث مبني على سجل أحداث + مؤشّر تتبع

**Main files | الملفات الرئيسية**
- `app/Core/RealtimeServer.php`
- `public/realtime/stream.php`

---

## 6) Alert Intelligence | ذكاء التنبيهات

### EN
Implemented intelligent alert processing:
- Severity scoring
- Deduplication window
- Grouping similar alerts
- Escalation rules based on severity/repetition
- Event-driven ingestion from existing event system

### AR
تم تنفيذ معالجة ذكية للتنبيهات:
- تقييم شدة التنبيه
- نافذة إزالة التكرار
- تجميع التنبيهات المتشابهة
- قواعد تصعيد حسب الشدة/التكرار
- الالتقاط عبر نظام الأحداث الحالي

**Main files | الملفات الرئيسية**
- `app/Services/AlertService.php`
- `app/Repositories/AlertRepository.php`
- `app/Listeners/ProcessAlertIntelligenceListener.php`
- `docs/ALERT_INTELLIGENCE_SCHEMA.sql`

---

## 7) Workflow Observability APIs | واجهات مراقبة سير العمل

### EN
Added workflow timeline visibility API without modifying workflow engine:
- `GET /workflows/{id}/timeline`
- Returns:
  - steps
  - timestamps
  - status
  - event chain
  - replay-ready context/events

### AR
تمت إضافة واجهة مراقبة Timeline لسير العمل بدون تعديل المحرك:
- `GET /workflows/{id}/timeline`
- تعيد:
  - الخطوات
  - التوقيتات
  - الحالة
  - سلسلة الأحداث
  - بيانات جاهزة لإعادة العرض (replay)

**Main files | الملفات الرئيسية**
- `app/Services/WorkflowTimelineService.php`
- `app/Controllers/Http/WorkflowTimelineController.php`

---

## 8) Metrics and Health APIs | واجهات المقاييس وصحة النظام

### EN
Workflow metrics were enhanced with runtime dimensions:
- per workflow type
- per country

Added read-only service and APIs:
- `GET /metrics/system-health`
- `GET /metrics/workflow-stats`
- `GET /metrics/failure-rates`

### AR
تم تحسين مقاييس سير العمل بأبعاد تشغيلية:
- حسب نوع سير العمل
- حسب الدولة

وتمت إضافة خدمة قراءة فقط مع واجهات:
- `GET /metrics/system-health`
- `GET /metrics/workflow-stats`
- `GET /metrics/failure-rates`

**Main files | الملفات الرئيسية**
- `app/Core/WorkflowMetrics.php`
- `app/Services/MetricsService.php`
- `app/Controllers/Http/MetricsController.php`

---

## 9) External API v1 | API خارجي بإصدار v1

### EN
Versioned external API implemented under:
- `/api/v1/`

Endpoints:
- workers
- tracking
- workflows
- alerts

Secured with token-based auth and external rate limits.
All access is service/controller-based (no endpoint-level direct DB logic).

### AR
تم تنفيذ API خارجي بإصدار:
- `/api/v1/`

النقاط المتاحة:
- workers
- tracking
- workflows
- alerts

مع حماية بالتوكن وتحديد معدل طلبات خارجي.
وكل الوصول عبر الخدمات/المتحكمات (بدون SQL مباشر في ملفات endpoints).

**Main files | الملفات الرئيسية**
- `api/v1/bootstrap.php`
- `api/v1/index.php`
- `api/v1/workers/index.php`
- `api/v1/tracking/index.php`
- `api/v1/workflows/index.php`
- `api/v1/alerts/index.php`

---

## 10) Webhook System | نظام Webhooks

### EN
Implemented asynchronous webhook infrastructure:
- Subscription table (`webhooks`)
- Delivery queue/log (`webhook_deliveries`)
- Event subscriptions:
  - `worker_created`
  - `violation_detected`
  - `workflow_completed`
- Retry with backoff
- Delivery status logging

### AR
تم تنفيذ بنية Webhooks غير متزامنة:
- جدول الاشتراكات (`webhooks`)
- طابور/سجل الإرسال (`webhook_deliveries`)
- الاشتراك في الأحداث:
  - `worker_created`
  - `violation_detected`
  - `workflow_completed`
- إعادة المحاولة مع backoff
- تسجيل حالة التسليم

**Main files | الملفات الرئيسية**
- `app/Repositories/WebhookRepository.php`
- `app/Services/WebhookService.php`
- `app/Listeners/QueueWebhookListener.php`
- `public/webhooks/dispatch.php`
- `docs/WEBHOOKS_SCHEMA.sql`

---

## 11) Validation and Safety | التحقق والسلامة

### EN
- Syntax checks (`php -l`) were run after major changes.
- Lint checks were run on changed files.
- Changes were layered through services/middleware/listeners.
- Workflow engine core logic was not directly modified for observability features.

### AR
- تم تنفيذ فحوصات صياغة (`php -l`) بعد التعديلات الرئيسية.
- تم تنفيذ فحوصات lint على الملفات المعدلة.
- تم تطبيق التعديلات عبر خدمات ووسطاء ومستمعين.
- لم يتم تعديل منطق محرك سير العمل مباشرة في ميزات المراقبة.

---

## 12) DB Scripts to Apply | سكربتات قواعد البيانات المطلوب تطبيقها

### EN
Run the following SQL files in your target environment:
1. `docs/IAM_SCHEMA.sql`
2. `docs/SECURITY_HARDENING_SCHEMA.sql`
3. `docs/ALERT_INTELLIGENCE_SCHEMA.sql`
4. `docs/WEBHOOKS_SCHEMA.sql`

### AR
نفّذ ملفات SQL التالية على بيئة التشغيل:
1. `docs/IAM_SCHEMA.sql`
2. `docs/SECURITY_HARDENING_SCHEMA.sql`
3. `docs/ALERT_INTELLIGENCE_SCHEMA.sql`
4. `docs/WEBHOOKS_SCHEMA.sql`

---

## 13) Deployment Notes | ملاحظات النشر

### EN
- Configure environment variables for:
  - API tokens
  - rate limits
  - government signing secret
  - webhook dispatcher token
- Schedule webhook dispatcher:
  - `public/webhooks/dispatch.php`

### AR
- اضبط متغيرات البيئة الخاصة بـ:
  - توكنات API
  - حدود المعدل
  - سر توقيع وضع الحكومة
  - توكن عامل webhook dispatcher
- جدولة مشغّل الويبهوك:
  - `public/webhooks/dispatch.php`

---

## 14) Final Status | الحالة النهائية

### EN
All requested architectural layers were implemented and integrated with existing event-driven design while preserving core execution paths.

### AR
تم تنفيذ جميع الطبقات المطلوبة وربطها مع التصميم القائم على الأحداث مع الحفاظ على مسارات التنفيذ الأساسية.

