/**
 * EN: Implements frontend interaction behavior in `js/accounting/patch-support-payments.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/accounting/patch-support-payments.js`.
 */
/**
 * Patch professional.js - Support Payments: ID -> #, add Edit button
 * Run: node patch-support-payments.js
 */
const fs = require('fs');
const path = require('path');
const filePath = path.join(__dirname, 'professional.js');
let s = fs.readFileSync(filePath, 'utf8');

// 1) Support Payments table header: <th>ID</th> -> <th>#</th>
s = s.replace(
    /(<table[^>]*id="supportPaymentsTable"[^>]*>[\s\S]*?<thead>\s*<tr>\s*)<th>ID<\/th>(\s*<th>DATE)/,
    '$1<th>#</th>$2'
);

// 2) Row first column: ${v.id} -> ${idx + 1} in loadSupportPayments
s = s.replace(
    /(loadSupportPayments[\s\S]*?tbody\.innerHTML = vouchers\.map\(\(v, idx\) => `\s*<tr>\s*)<td>\$\{v\.id\}<\/td>/,
    '$1<td>${idx + 1}</td>'
);

// 3) Add Edit button between View and Print (if not already present)
if (!s.includes('data-action="edit-voucher"')) {
    s = s.replace(
        /(<button class="btn btn-sm btn-info" data-action="view-voucher" data-id="\$\{v\.id\}" data-type="payment" title="View">\s*<i class="fas fa-eye"><\/i>\s*<\/button>)\s*(<button class="btn btn-sm btn-secondary" data-action="print-voucher")/,
        '$1\n                            <button class="btn btn-sm btn-warning" data-action="edit-voucher" data-id="${v.id}" data-type="payment" title="Edit">\n                                <i class="fas fa-edit"></i>\n                            </button>\n                            $2'
    );
}

// 4) Add edit handler - add editBtn and else if (editBtn) block
if (!s.includes('editBtn') || !s.match(/editBtn.*edit-voucher/)) {
    s = s.replace(
        /(const viewBtn = e\.target\.closest\(\[data-action="view-voucher"\]\[data-type="payment"\]\);\s*)(const printBtn = e\.target\.closest)/,
        '$1const editBtn = e.target.closest(\'[data-action="edit-voucher"][data-type="payment"]\');\n        $2'
    );
    s = s.replace(
        /(if \(viewBtn\) \{\s*const id = viewBtn\.getAttribute\(['"]data-id['"]\);\s*this\.openPaymentVoucherModal\(parseInt\(id, 10\)\);\s*\} )else if \(printBtn\)/,
        '$1else if (editBtn) {\n                const id = editBtn.getAttribute(\'data-id\');\n                this.openPaymentVoucherModal(parseInt(id, 10));\n            } else if (printBtn)'
    );
}

fs.writeFileSync(filePath, s);
console.log('professional.js patched successfully.');
