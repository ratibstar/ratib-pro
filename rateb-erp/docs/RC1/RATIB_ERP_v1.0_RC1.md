# RATIB ERP v1.0 RC1 — التقرير الشامل

**الإصدار:** RC1 (Release Candidate 1)  
**التاريخ:** 2026-06-24  
**الأدوار:** QA Lead + Release Manager  
**حالة التجميد:** Code Freeze **نشط**

---

## جدول المحتويات

1. [القرار النهائي](#1-القرار-النهائي)
2. [Release Notes](#2-release-notes)
3. [Code Freeze Audit](#3-code-freeze-audit)
4. [قائمة الأخطاء (Bug List)](#4-قائمة-الأخطاء-bug-list)
5. [تقرير الأمان (Security)](#5-تقرير-الأمان-security)
6. [تقرير الأداء (Performance)](#6-تقرير-الأداء-performance)
7. [Production Checklist](#7-production-checklist)
8. [Deployment Checklist](#8-deployment-checklist)
9. [Rollback Checklist](#9-rollback-checklist)
10. [التوقيع](#10-التوقيع)

---

## 1. القرار النهائي

# ⛔ RELEASE BLOCKED

v1.0 RC1 **غير معتمد** للإصدار العام حتى إغلاق العناصر المحظورة بالأدلة.

### أسباب الحظر

1. أخطاء **Critical** في التحويلات بين الفروع (`Csrf::verifyOrAbort`، import ناقص) — **مُصلحة محلياً، غير منشورة**
2. **Migration 135** و`InterBranchTransferService` غير مُتحققين على Production
3. **انحراف النشر** — `erp-health.php` بدون JSON probes؛ `erp-security-cert.php` يعيد 404
4. **لا أدلة Staging** — اختبارات enterprise، load test، restore drill غير منفذة
5. **تعرض ops** — health endpoint يكشف metadata قاعدة البيانات (يتطلب تقييد)

### ما نجح (بوابات الجودة)

| البوابة | النتيجة |
|---------|---------|
| PHP syntax (`app/`) | ✅ |
| CSRF على POST (بعد إصلاح C01) | ✅ |
| Auth / Session | ✅ |
| API token + branch guard | ✅ |
| PDO prepared statements | ✅ |
| اتصال Production DB | ✅ |
| سكربتات backup/restore | ✅ |

### الحد الأدنى لرفع الحظر

1. نشر RC1 hotfix + Phase 6
2. تطبيق migration **135** والتحقق عبر `?probe=schema`
3. Smoke: تحويل فرع → approve → `completed`
4. نشر `erp-security-cert.php` → `ok: true`, critical=0, high=0
5. تقييد health probes المتقدمة (IP/token)
6. Restore drill موثق على Staging
7. إعادة التدقيق → **READY FOR RELEASE**

### Code Freeze

يمنع: modules جديدة، features جديدة، تعديل business logic إلا لإصلاح bug مثبت Critical/High.

---

## 2. Release Notes

### نطاق v1.0 RC1

RATIB ERP — نظام ERP متعدد المستأجرين والفروع:

- **Accounting** — COA، journals، AP/AR، VAT، GL بالفرع، تقارير HQ موحدة
- **HR** — موظفون، حضور، إجازات، رواتب
- **Inventory** — مستودعات، حركات مخزون، batches
- **Procurement** — PR، PO، مدفوعات موردين
- **CRM** — عملاء، فواتير
- **Assets & contracts** — إهلاك، تجديد، عزل فروع
- **Branches** — أدوار HQ/فرع، تحويلات بين الفروع (تنفيذ Phase 6)
- **API v1** — token auth، mutations معزولة بالفرع
- **CMS / Marketing** — الموقع العام (خارج ops الأساسية)

### ما تغيّر منذ Phase 5

| المجال | التغيير |
|--------|---------|
| Inter-branch transfers | `InterBranchTransferService` — تنفيذ عند الموافقة |
| Migration 135 | حالة `failed`؛ مصدر قيد `branch_transfer` |
| Audit | `AuditService::logTransfer()` مع session/IP/old-new |
| Notifications | مديرو الفروع + HQ عند اكتمال التحويل |
| Validation infra | `bin/enterprise-seed/`, `enterprise-test/`, `enterprise-dr-validate.php`, k6 |
| **RC1 hotfix** | CSRF `validateCsrf()` + import `InterBranchTransferService` |

### قيود معروفة (لا تُصلح في RC1)

- `financialSummary()` company-scoped فقط
- تقارير HQ تتطلب migrations 131–135
- seed/load tests تحتاج Staging
- `erp-health.php` على out.ratib.sa — build أساسي بدون JSON probes

### مسار الترقية

1. نشر ملفات `rateb-erp/` (انظر Deployment Checklist)
2. تشغيل migrations حتى **135**
3. Smoke: login، dashboard، قيد، تحويل فرع pending → approve
4. التحقق من health probes بعد النشر

### Breaking Changes

لا يوجد. Migration 135 يوسّع ENUMs فقط.

---

## 3. Code Freeze Audit

**التاريخ:** 2026-06-24

| المجال | النتيجة |
|--------|---------|
| **PHP syntax** | ✅ لا أخطاء في `app/` |
| **JavaScript** | 38 ملف `assets/js` — لا أخطاء بناء واضحة |
| **SQL** | PDO مهيمن؛ ديناميكي بـ whitelist |
| **Dead code** | 🟡 طبقات branch متكررة — Low، لا حذف في RC1 |
| **Duplicate code** | 🟡 TenantGuard vs TenantFkValidator؛ branch services |
| **Circular dependencies** | لم يُكتشف fatal |
| **N+1 queries** | 🟡 Medium — dashboards، document counts، consolidated |
| **Missing indexes** | 🟡 `(company_id, branch_id, status)` على JE/PO — v1.1 |
| **Memory leaks** | لا أنماط واضحة؛ تقارير موحدة ثقيلة على request واحد |

---

## 4. قائمة الأخطاء (Bug List)

**الرموز:** 🔴 Critical · 🟠 High · 🟡 Medium · 🟢 Low

### 🔴 Critical

| ID | الخطأ | الموقع | الحالة |
|----|-------|--------|--------|
| RC1-C01 | `Csrf::verifyOrAbort()` غير معرّف — fatal | `BranchControllers.php` | **مُصلح محلياً** |
| RC1-C02 | `use InterBranchTransferService` ناقص | `BranchControllers.php` | **مُصلح محلياً** |
| RC1-C03 | Phase 6 + RC1 غير منشور | Deployment | **مفتوح** |

### 🟠 High

| ID | الخطأ | الموقع | الحالة |
|----|-------|--------|--------|
| RC1-H01 | Migration 135 غير مطبّق | DB | مفتوح |
| RC1-H02 | health بدون JSON probes | Production | مفتوح |
| RC1-H03 | `erp-security-cert.php` → 404 | `public/` | مفتوح |
| RC1-H04 | health يكشف DB host/name | `erp-health.php` | تخفيف — IP restrict |
| RC1-H05 | `probe=branch-ops` يتظاهر super-admin | `erp-health.php` | تخفيف — token/IP |
| RC1-H06 | SVG في CMS — XSS مخزّن | `CmsMediaService.php` | تأجيل / تقييد SVG |

### 🟡 Medium (v1.1)

| ID | الخطأ | الموقع |
|----|-------|--------|
| RC1-M01 | ApiBranchGuard يتخطى عند allowed list فارغ | `ApiBranchGuardService.php` |
| RC1-M02 | API create ضعيف التحقق | `ApiController.php` |
| RC1-M03 | API bearer بدون rate limit | `routes/api.php` |
| RC1-M04 | N+1 document counts | `CrudController.php` |
| RC1-M05 | N+1 branch overview | `BranchReportingService.php` |
| RC1-M06 | N+1 consolidated reports | `BranchFinancialReportingService.php` |
| RC1-M07 | فهرس مركب ناقص JE/PO | DB |
| RC1-M08 | Migration 135 غير idempotent | `135_*.sql` |
| RC1-M09 | `financialSummary()` غير branch-scoped | `AccountingService.php` |
| RC1-M10 | PHP 7.4.33 على Production | Server |

### 🟢 Low

| ID | الخطأ | الموقع |
|----|-------|--------|
| RC1-L01 | `Auth::check()` يستعلم DB كل طلب | `Auth.php` |
| RC1-L02 | تكرار branch services | متعدد |
| RC1-L03 | security-cert عام (عند النشر) | informational |
| RC1-L04 | CMS `custom_head_code` HTML خام | `analytics-head.php` |

**بوابة الإصدار:** كل Critical و High يجب إغلاقها أو تخفيفها قبل GA.

---

## 5. تقرير الأمان (Security)

**الطريقة:** مراجعة ثابتة + probe على `out.ratib.sa`

### الملخص

| الشدة | مفتوح | مُصلح محلياً |
|-------|-------|---------------|
| Critical | 0* | 2 (*بعد hotfix، نشر معلق) |
| High | 4 | 0 |
| Medium | 6 | 0 |
| Low | 4 | — |

**الوضع:** قوي في نواة التطبيق؛ **محظور بسبب النشر والتعرض التشغيلي**.

### Authentication

| التحكم | الحالة |
|--------|--------|
| `password_verify` | ✅ |
| Account lockout | ✅ |
| Session regenerate | ✅ |
| Remember-me revocation | ✅ |
| فصل admin/company | ✅ |
| 2FA | ✅ |

### Authorization

| التحكم | الحالة |
|--------|--------|
| Role + permission middleware | ✅ |
| Branch context | ✅ |
| Entity permissions | ✅ |
| API module abilities | ✅ |
| حظر super-admin API | ✅ |

### CSRF / XSS / SQLi / IDOR

| الفئة | الحالة |
|-------|--------|
| CSRF (web POST) | ✅ بعد C01 |
| XSS (views) | ✅ `View::escape()` |
| XSS (CMS SVG) | ⚠️ |
| SQL Injection | ✅ PDO |
| Tenant / Branch isolation | ✅ |
| SSRF | لا مسار واضح في core |

### File Upload

| الخدمة | التحقق |
|--------|--------|
| `DocumentService` | MIME، اسم عشوائي، حجم |
| `ProductCategoryService` | finfo whitelist |
| `CmsMediaService` | MIME incl. SVG ⚠️ |

### API

| التحكم | الحالة |
|--------|--------|
| SHA-256 token | ✅ |
| Branch guard | ✅ |
| Plan limits | ✅ |
| Rate limit token create | ✅ |
| Rate limit API reads/writes | ❌ Medium |

### Session

httponly ✅ · SameSite Lax · Secure عند HTTPS · cookie `rateb_erp`

### Production Probes (2026-06-24)

| Endpoint | النتيجة |
|----------|---------|
| `erp-health.php?probe=ping` | ✅ PHP 7.4.33، DB OK |
| `?probe=schema` | ❌ build قديم |
| `erp-security-cert.php` | ❌ 404 |

### توصيات (بدون تغيير كود في freeze إلا Critical)

1. نشر hotfixes + migration 135
2. تقييد health probes
3. نشر `erp-security-cert.php`
4. تقييد أو إزالة SVG
5. rate limiting للـ API قبل GA عام

---

## 6. تقرير الأداء (Performance)

**ملاحظة:** لم يُنفَّذ load test (100–1000 مستخدم). تقديرات من مراجعة الكود.

### Production Snapshot

| المقياس | القيمة |
|---------|--------|
| health ping | ~1–2s |
| PHP | 7.4.33 |
| DB | `outratib_rateb-erp` |

### أبطأ 10 صفحات (تقديري)

| # | الصفحة | السبب |
|---|--------|-------|
| 1 | تقارير HQ الموحدة | حلقة P&L/BS/CF لكل فرع |
| 2 | Branch overview | N× metrics |
| 3 | Admin dashboard | تجميعات متعددة |
| 4 | Journal list + lines | joins كبيرة |
| 5 | Inventory + document counts | N+1 |
| 6 | Stock movements | joins |
| 7 | Payroll approval | batch employees |
| 8 | Invoice list (tenant كبير) | company filter |
| 9 | Trial balance all branches | تجميع قيود |
| 10 | Inter-branch transfers | OK (LIMIT 100) |

### أنماط استعلام بطيئة (تقديري)

- Branch P&L per branch — فهرس مركب ناقص
- Document COUNT per row — N+1
- Consolidated elimination 1350/2150 — مسح company
- User branch resolution — per login

### الذاكرة / CPU

- تقارير موحدة: خطر عالٍ على request واحد
- Auth DB lookup: متوسط–منخفض
- PHP 8.x موصى به

### فهارس مقترحة (v1.1 — لا تغيير schema في RC1)

```sql
-- ALTER TABLE rateb_journal_entries ADD INDEX idx_je_cid_bid_st (company_id, branch_id, status);
-- ALTER TABLE rateb_purchase_orders ADD INDEX idx_po_cid_bid_st (company_id, branch_id, status);
```

### خطة Load Test (قبل GA)

```bash
export RATEB_STAGING_URL=https://staging-host/rateb-erp/public
k6 run bin/enterprise-perf/k6-load.js
ab -n 5000 -c 100 ${RATEB_STAGING_URL}/erp-health.php?probe=ping
```

---

## 7. Production Checklist

**مُدقق:** 2026-06-24 — `out.ratib.sa`

### Database

| الفحص | الحالة |
|-------|--------|
| Connection | ✅ |
| Migrations 129–134 | ⚠️ UNKNOWN |
| Migration 135 | ❌ |
| utf8mb4 | ✅ مفترض |

### Queue / Cron / Cache

| الفحص | الحالة |
|-------|--------|
| `rateb_notification_queue` | ✅ schema |
| `erp-cron.php` | ⚠️ VERIFY |
| Queue worker | ⚠️ VERIFY |
| OPcache | ⚠️ VERIFY |
| App cache (Redis) | N/A |

### Sessions / Uploads / Storage

| الفحص | الحالة |
|-------|--------|
| SessionManager | ✅ |
| HTTPS secure cookie | ⚠️ VERIFY |
| `storage/uploads/` writable | ⚠️ VERIFY |
| DocumentService | ✅ code |
| `storage/backups/` | ⚠️ VERIFY |

### SSL / Logs / Backups / Restore

| الفحص | الحالة |
|-------|--------|
| HTTPS | ✅ |
| Fatal errors 24h | ⚠️ VERIFY |
| `erp-backup.php` | ✅ repo |
| latest `.sql.gz` | ⚠️ VERIFY |
| Restore drill | ❌ غير موثق |
| RTO/RPO | ❌ غير مقاس |

### Monitoring / Health

| Probe | Production |
|-------|------------|
| `?probe=ping` | ✅ |
| `?probe=schema` | ❌ |
| `?probe=branch-ops` | ❌ |
| `erp-security-cert.php` | ❌ 404 |

### الملخص

| | Pass | Fail | Verify |
|--|------|------|--------|
| Count | 8 | 5 | 14 |

**جاهزية GA:** ❌

---

## 8. Deployment Checklist

**الهدف:** Production (`out.ratib.sa`)  
**الطريقة:** GitHub Actions fast deploy + sync يدوي لـ `rateb-erp/`

### Pre-deploy

- [ ] Code freeze — hotfixes فقط
- [ ] Critical مغلقة في الفرع
- [ ] `php bin/erp-backup.php`
- [ ] نسخة ملفات في `storage/backups/`
- [ ] نافذة صيانة (إن لزم migration 135)

### Deploy files

**Auto:** `includes/`, `pages/`, `js/`, `css/`, `api/`, `config/env/`, `public/`

**Manual (تحقق):**

- [ ] `rateb-erp/app/services/InterBranchTransferService.php`
- [ ] `rateb-erp/app/controllers/Company/BranchControllers.php`
- [ ] `rateb-erp/app/services/AuditService.php`
- [ ] `rateb-erp/public/erp-health.php`
- [ ] `rateb-erp/public/erp-security-cert.php`
- [ ] `rateb-erp/config/lang/en.php`, `ar.php`
- [ ] `rateb-erp/migrations/135_phase6_interbranch_execution.sql`

### Database

- [ ] تطبيق migration **135**
- [ ] `rateb_branch_transfers.status` يتضمن `failed`
- [ ] `journal_entries.source_type` يتضمن `branch_transfer`
- [ ] 129–134 مطبقة

```sql
SELECT filename FROM rateb_migrations WHERE filename LIKE '13%' ORDER BY filename;
```

### Post-deploy smoke

- [ ] `?probe=ping` → OK
- [ ] `?probe=schema` → JSON
- [ ] `erp-security-cert.php` → ok, critical=0, high=0
- [ ] Admin + company login
- [ ] تحويل فرع → approve → `completed`
- [ ] journal list + invoice list

### Runtime

- [ ] Cron `erp-cron.php` كل 5–15 دقيقة
- [ ] Backup ليلي `erp-backup.php`
- [ ] OPcache مفعّل
- [ ] HTTPS + تقييد health probes
- [ ] `storage/` غير عام

---

## 9. Rollback Checklist

### محفزات القرار

- HTTP 500 على login/dashboard
- فشل migration 135 جزئي
- فساد بيانات تحويل فرع
- فشل واسع في ترحيل القيود

### فوري (0–15 دقيقة)

1. إيقاف cron
2. وضع صيانة
3. حفظ PHP error log + Apache log + `SHOW PROCESSLIST`

### Application rollback

**A — Git revert (مفضل):**

```bash
git log -3 --oneline
git checkout <previous-green-commit> -- rateb-erp/
```

**B — استعادة ملفات:** `BranchControllers.php`, `InterBranchTransferService.php`, `erp-health.php`

### Database rollback

```sql
SELECT COUNT(*) FROM rateb_journal_entries WHERE source_type = 'branch_transfer';
SELECT COUNT(*) FROM rateb_branch_transfers WHERE status IN ('completed','failed');
```

**مفضل:** `php bin/erp-restore.php` من `storage/backups/erp-*.sql.gz` قبل النشر

### بعد الـ rollback

- [ ] health ping OK
- [ ] login يعمل
- [ ] لا fatals 15 دقيقة
- [ ] إعادة cron

---

## 10. التوقيع

### عند رفع الحظر — READY FOR RELEASE

| الدور | الاسم | التاريخ | ✓ |
|-------|-------|---------|---|
| QA Lead | | | ☐ |
| Release Manager | | | ☐ |
| Ops / DBA | | | ☐ |

---

*وثيقة موحدة — RATIB ERP v1.0 RC1 — آخر تحديث 2026-06-24*
