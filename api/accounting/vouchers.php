<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/vouchers.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/vouchers.php`.
 */
/**
 * Vouchers API - Proxy to receipt-payment-vouchers
 *
 * Frontend calls /api/accounting/vouchers.php?type=payment|receipt&id=...
 * This file forwards to receipt-payment-vouchers.php so the same backend handles
 * view (GET), update (PUT), delete (DELETE), duplicate (POST action=duplicate).
 */

if (!isset($_GET['type']) || !in_array($_GET['type'], ['receipt', 'payment'], true)) {
    $_GET['type'] = 'receipt';
}

require __DIR__ . '/receipt-payment-vouchers.php';
