# Cash/Bank Account – File & Flow Verification

## Script load order (accounting.php)
1. professional.core.js
2. professional.utilities.js
3. professional.part1.js
4. professional.accounts.js
5. professional.dashboard.js
6. **professional.management.js** – loadVouchers, voucher table
7. **professional.part5.js** – getPaymentVoucherModalContent, applyPaymentVoucherDataToForm, savePaymentVoucher, loadPaymentVoucherAccountOptions
8. professional.modals.js
9. **professional.modals.tables.js** – openPaymentVoucherModal (OVERRIDES part5), setupVouchersHandlers
10. professional.reports.js
11. professional-support-payments.js
12. **professional.init.js** – patches loadPaymentVoucherAccountOptions on instance (bypassed by modals.tables)
13. accounting-modal.js

## Flow when you click Edit
1. **setupVouchersHandlers** (modals.tables.js line 3060) attaches Edit click handler
2. Click calls **openPaymentVoucherModal(id)** (modals.tables.js line 3351)
3. Form HTML from **getPaymentVoucherModalContent** (part5.js line 3942)
4. Fetch voucher from `receipt-payment-vouchers.php?id=X&type=payment`
5. **loadPaymentVoucherAccountOptions** – uses prototype version (part5.js line 4033) to bypass init patch
6. **applyPaymentVoucherDataToForm** (part5.js line 4124) + inline Cash/Bank logic in modals.tables

## Files that matter
- **professional.modals.tables.js** – openPaymentVoucherModal, Cash/Bank apply logic
- **professional.part5.js** – getPaymentVoucherModalContent, applyPaymentVoucherDataToForm, loadPaymentVoucherAccountOptions, savePaymentVoucher
- **api/accounting/receipt-payment-vouchers.php** – API response
- **api/accounting/core/ReceiptPaymentVoucherManager.php** – get(), payment_vouchers table

## How to verify bank_account_id in API response
1. Open browser DevTools (F12) → Network tab
2. Click Edit on a payment voucher
3. Find request: `receipt-payment-vouchers.php?id=X&type=payment`
4. Click it → Response tab
5. Check: does `voucher.bank_account_id` exist? What value?
   - If **null** or missing: DB has nothing → select Cash/Bank and Update
   - If **number** (e.g. 5): frontend apply bug → report value for debug
