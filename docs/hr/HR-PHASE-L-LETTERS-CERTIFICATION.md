# RATIB ERP — HR Phase L Letters + Employee Documents Certification

**Status:** COMPLETE  
**Date:** 2026-08-14  
**Base:** Phase K HireBridge + contracts (`3f560248`)

---

## Objective

Turn letter requests into a real HR letter service: **request → Matrix/Oversight approve → issue Arabic PDF → download**, linked to `rateb_employees`, using existing document storage.

---

## Letter types

| Type key | Arabic |
|----------|--------|
| `salary_certificate` | شهادة راتب |
| `employment_certificate` | شهادة تعريف بالعمل |
| `experience_letter` | شهادة خبرة |
| `end_of_service` | شهادة نهاية خدمة |

SoT remains `rateb_hr_employee_requests` (no LetterRequest2).

---

## Architecture

```text
Create request (hr/requests)
  → notify oversight (hr_request)
  → Approvals inbox / Oversight + Matrix (Phase F/G/J)
  → status = approved

HrLetterIssueService::issue
  → HrLetterPdfRenderer (Arabic Noto + PDF wrapper)
  → DocumentService::storeGeneratedBytes → rateb_documents (entity hr_employees)
  → request.document_id / issued_at / issued_by
  → Audit hr_letter_issue|reissue

Download
  → company + employee ownership checks
  → Audit hr_letter_download
  → DocumentService::sendDownload
```

**Forbidden:** new Workflow/ApprovalEngine, payroll/accounting changes, parallel DMS, ESS/mobile redesign.

---

## Surfaces

| Surface | Route |
|---------|--------|
| Letters workspace | `hr/letters` |
| Issue | `POST hr/letters/{id}/issue` |
| Download | `GET hr/letters/{id}/download` |
| Employee 360 Letters tab | issue / download actions |
| Migration | `251_hr_phase_l_letters.sql` (additive columns) |

RBAC: `rateb_erp_mw('hr', '', 'hr-leaves')` (same family as requests).

---

## PDF

- Font: `app/Lib/HrLetterPdf/fonts/NotoNaskhArabic-Regular.ttf` (OFL)
- Renderer: embedded TrueType PDF (`HrLetterPdfRenderer` + `TtfCmap`) — no GD required
- Arabic reshape via `ArabicText`

---

## Tests

| Suite | Result |
|-------|--------|
| `run-hr-phase-l-tests.php` | **CLEAR** |
| Phase B–K regressions | **CLEAR** |

---

## Explicit non-goals

- Mobile / ESS redesign  
- GOSI / WPS  
- Payroll redesign  
- Phase M decisions/disciplinary  

---

## Exit criteria

Authorized HR can request letter types, approve via existing inbox/matrix, issue Arabic PDF into `rateb_documents`, and download from letters page and Employee 360 — with tenant isolation and audit.
