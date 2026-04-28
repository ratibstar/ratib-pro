# Accounting Module – Full Check Summary

**Date:** 2025-02-06  
**Scope:** API (PHP), JS (professional.*), voucher/journal/entity flows, permissions, calculations, export.

---

## 1. Structure

- **Page:** `pages/accounting.php` – loads `professional.core.js`, `professional.utilities.js`, `professional.part1.js` … `professional.part6.js`, `professional.management.js`, `professional.part5.js`, `professional.modals.js`, `professional.modals.tables.js`, `professional.reports.js`, `professional-support-payments.js`, `professional.init.js`, `accounting-modal.js`.
- **API base:** `this.apiBase = baseUrl + '/api/accounting'` (professional.core.js).
- **Vouchers:** Payment vouchers use `receipt-payment-vouchers.php?type=payment`. Receipt list/save also use `payment-receipts.php`. View/Delete/Duplicate/Export use `vouchers.php` (proxy to `receipt-payment-vouchers.php`).

---

## 2. API Auth & Permissions

- All checked APIs use `require_once '../../includes/config.php'` and session checks where applicable.
- `receipt-payment-vouchers.php`, `payment-receipts.php`, `journal-entries.php`, `bills.php`, `invoices.php`, `entity-transactions.php`, `accounts.php`, `banks.php`, `customers.php`, `vendors.php`, `cost-centers.php`, `bank-guarantees.php`, `dashboard.php`, `financial-closings.php`, `settings.php`, `entry-approval.php` use `enforceApiPermission()` from `api/core/api-permission-helper.php` for view/create/update/delete as appropriate.

---

## 3. Voucher Flows

- **Payment:** List/save → `receipt-payment-vouchers.php?type=payment`. Edit modal loads single voucher from same API; form applies `source_account_id` (Cash/Bank GL) and `account_id` (Payee GL). Amount validated > 0 (client + server); stored with 2-decimal rounding.
- **Receipt:** List → `payment-receipts.php` (management and part6). Save → `payment-receipts.php`. Edit modal loads from `payment-receipts.php`; part5 and management both validate amount > 0 before submit.
- **View/Print/Delete/Duplicate:** `vouchers.php?id=…&type=payment|receipt` proxies to `receipt-payment-vouchers.php`. Export previously returned JSON; now CSV is supported (see below).

---

## 4. Journal Entries

- Frontend (part5): Debit/credit lines and totals rounded to 2 decimals; balance check with 0.01 tolerance. Payload sends `total_debit`, `total_credit`, `debit_lines`, `credit_lines`.
- Backend (journal-entries.php): Same 0.01 tolerance on create/update/post; totals from lines; balance validated before post.

---

## 5. Entity Transactions

- `total_amount = Math.max(debit, credit)` (or from amount) is correct for single-sided expense/income. API expects `total_amount`, `debit_amount`, `credit_amount`.

---

## 6. Calculations (Already Addressed)

- Payment/Receipt: Client-side amount > 0 validation; backend stores amount with `round(..., 2)`.
- Journal: Line amounts and totals rounded to 2 decimals in payload; 0.01 balance tolerance on both sides.

---

## 7. Fixes Applied in This Check

1. **Voucher CSV export** – `receipt-payment-vouchers.php`: when `GET export=1&format=csv`, returns CSV (Voucher #, Date, Type, Amount, Currency, Status, Reference, Description) for current type (payment or receipt). Frontend calls `vouchers.php?export=1&format=csv` (type defaults to receipt), so receipt vouchers download as CSV; for payment CSV use `receipt-payment-vouchers.php?type=payment&export=1&format=csv`.
2. **Duplicate formatDatesInArray** – Removed redundant block in receipt-payment-vouchers create response.
3. **Receipt amount validation in management** – `professional.management.js` `saveReceiptVoucher` now validates amount > 0 for consistency (part5’s handler remains the primary one).

---

## 8. Notes / Optional Follow-ups

- **Voucher export type:** Export from UI uses `vouchers.php`, which defaults `type=receipt`. To export both payment and receipt in one CSV, either call the API twice (payment + receipt) and merge client-side, or add a combined export endpoint.
- **Other exports:** `journal-entries.php`, `invoices.php`, `bills.php`, `bank-transactions.php` are called with `?export=1&format=csv` from part6; if they do not send CSV, the downloaded file will be JSON. Consider adding CSV handling there or building CSV from JSON in the frontend.
- **professional.js** (if used elsewhere) may still reference old voucher IDs or endpoints; the active accounting page uses the split professional.* files only.
- **Receipt list:** Two implementations of `loadReceiptVouchers` (management and part6); part6’s runs last and is used. Both use `payment-receipts.php`.

---

## 9. File Change Summary

| File | Change |
|------|--------|
| `api/accounting/receipt-payment-vouchers.php` | CSV export when `export=1&format=csv`; removed duplicate formatDatesInArray in create. |
| `js/accounting/professional.management.js` | `saveReceiptVoucher`: amount > 0 and not NaN validation. |
| **Final deep check** | |
| `js/accounting/professional.management.js` | Removed `[Receipt Vouchers]` debug console.log in loadReceiptVouchers. |
| `js/accounting/professional.part6.js` | Removed same debug console.log in loadReceiptVouchers. |
| `js/accounting/professional.part5.js` | Removed verbose saveInvoice console.log/console.error (entry/request/response logging). Left only user-facing showToast and minimal error handling. |

No other critical bugs or inconsistencies were found in the accounting flows checked.

---

## 10. Final Deep Check (Same Day)

- **Voucher display:** All tables use `voucher_number` / `receipt_number` / `reference` (PY/RC) for the first column; no raw `voucher.id` in display.
- **View/Export/Delete:** `vouchers.php` proxy passes `id` and `type`; receipt-payment-vouchers receives them. View modal uses `v.voucher_number || v.reference_number || voucherId`; export filename uses `voucher_number || receipt_number || voucherId`.
- **payment-receipts.php:** List returns `receipts`; single returns `receipt` with `payment_date` (mapped from voucher_date). Frontend uses `data.receipts` and `receipt.payment_date`; API maps `payment_date` ↔ `voucher_date` in create/update. Aligned.
- **XSS:** View voucher and tables use `this.escapeHtml()` for user-facing strings (description, notes, names). Numeric/date values in templates are safe.
- **Debug logs:** Removed receipt list debug logs (management + part6) and saveInvoice verbose logging (part5). Remaining `console.error` in catch blocks kept for real errors.
- **Duplicate methods:** `loadReceiptVouchers` and `saveReceiptVoucher` are defined in both management and part5/part6; the last-loaded (part5/part6) wins. Receipt modal submit uses part5’s save (with amount validation). No conflict.
- **Date handling:** Payment voucher uses `voucher_date` (API); receipt uses `payment_date` (form) mapped to `voucher_date` in payment-receipts API. Display uses `formatDateForInput` / `formatDate` where needed.

---

## 11. Final check

- **Linter:** No errors in professional.part5, part6, management, modals.tables or receipt-payment-vouchers / payment-receipts PHP.
- **Console:** Only catch-block console.error/console.warn remain (no stray debug logs).
- **APIs:** Payment list/save → receipt-payment-vouchers.php?type=payment; receipt list/save → payment-receipts.php; view/print/delete/duplicate → vouchers.php; export all → dual fetch + client CSV; export receipt list → payment-receipts + client CSV.
- **Amounts:** Payment and receipt validate amount > 0 before submit; journal lines and totals rounded to 2 decimals; backend rounds voucher amount.

---

## 12. Continue – Export All Vouchers as One CSV

- **exportVouchers()** (part6): Previously called `vouchers.php?export=1&format=csv`, which returned only receipt vouchers (default type). Updated to fetch both **payment** and **receipt** lists from `receipt-payment-vouchers.php?type=payment` and `?type=receipt`, merge into one array, build CSV client-side (with header row and proper escaping), and download as `vouchers_YYYY-MM-DD.csv`. One export now includes all payment and receipt vouchers.
- **exportReceiptVouchers(format)** (part6): Was a stub (“Coming soon”). Implemented for **csv** and **excel**: fetches `payment-receipts.php` list, builds CSV (Voucher #, Date, Description, Payee/Customer, Bank/Cash, Amount, Currency, Cost Center, Status), downloads as `receipt_vouchers_YYYY-MM-DD.csv`. Other formats (print, pdf, copy) still show “coming soon”.
