<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/migrate-invoice-payment-journal-entries.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/migrate-invoice-payment-journal-entries.php`.
 */
/**
 * Migration Script: Create Journal Entries for Existing Invoices and Payments
 * 
 * This script migrates existing invoices and payments to have proper journal entries:
 * - Sales Invoices → Dr AR, Dr VAT Receivable, Cr Revenue, Cr VAT Payable
 * - Payments → Dr Cash/Bank, Cr Accounts Receivable
 * - Receipts → Dr Cash/Bank, Cr Revenue
 * - Disbursements (Payments) → Dr Expense, Cr Cash/Bank
 * 
 * Supports cost centers per line
 * 
 * Run this script once to migrate existing data
 */

require_once '../../includes/config.php';
require_once __DIR__ . '/core/invoice-payment-automation.php';

header('Content-Type: text/html; charset=utf-8');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set default user_id if not in session (for migration scripts)
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
}

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Invoice/Payment Journal Entry Migration</title></head><body>";
echo "<h1>Invoice/Payment Journal Entry Migration</h1>";
echo "<pre>";

try {
    $conn->begin_transaction();
    
    $results = [
        'invoices_processed' => 0,
        'invoices_created' => 0,
        'invoices_skipped' => 0,
        'payments_processed' => 0,
        'payments_created' => 0,
        'payments_skipped' => 0,
        'receipts_processed' => 0,
        'receipts_created' => 0,
        'receipts_skipped' => 0,
        'errors' => []
    ];
    
    // Step 1: Migrate Sales Invoices (accounts_receivable)
    echo "\n=== Step 1: Migrating Sales Invoices ===\n";
    $invoiceCheck = $conn->query("SHOW TABLES LIKE 'accounts_receivable'");
    if ($invoiceCheck->num_rows > 0) {
        $invoiceCheck->free();
        
        // Get all Posted invoices that don't have journal entries yet
        $invoiceQuery = "
            SELECT ar.id, ar.invoice_number, ar.invoice_date, ar.total_amount, ar.status
            FROM accounts_receivable ar
            WHERE ar.status = 'Posted'
            AND NOT EXISTS (
                SELECT 1 FROM journal_entries je 
                WHERE je.entry_number = CONCAT('JE-INV-', ar.invoice_number)
            )
            ORDER BY ar.id
        ";
        
        $invoiceResult = $conn->query($invoiceQuery);
        if ($invoiceResult) {
            while ($invoice = $invoiceResult->fetch_assoc()) {
                $results['invoices_processed']++;
                
                try {
                    $invoiceId = intval($invoice['id']);
                    $invoiceNumber = $invoice['invoice_number'];
                    $invoiceDate = $invoice['invoice_date'];
                    $totalAmount = floatval($invoice['total_amount']);
                    
                    // Calculate VAT (default 15%)
                    $vatRate = 15;
                    $baseAmount = $totalAmount / (1 + ($vatRate / 100));
                    $vatAmount = $totalAmount - $baseAmount;
                    
                    $journalResult = createInvoiceJournalEntry(
                        $conn,
                        $invoiceId,
                        $invoiceNumber,
                        $invoiceDate,
                        $totalAmount,
                        $vatAmount,
                        $vatRate,
                        null, // cost_center_id
                        "Migrated invoice {$invoiceNumber}"
                    );
                    
                    if ($journalResult['success']) {
                        $results['invoices_created']++;
                        echo "✓ Created journal entry for invoice {$invoiceNumber} (ID: {$invoiceId})\n";
                    } else {
                        $results['invoices_skipped']++;
                        $results['errors'][] = "Invoice {$invoiceNumber}: {$journalResult['message']}";
                        echo "✗ Skipped invoice {$invoiceNumber}: {$journalResult['message']}\n";
                    }
                } catch (Exception $e) {
                    $results['invoices_skipped']++;
                    $results['errors'][] = "Invoice {$invoice['invoice_number']}: {$e->getMessage()}";
                    echo "✗ Error processing invoice {$invoice['invoice_number']}: {$e->getMessage()}\n";
                }
            }
            $invoiceResult->free();
        }
    } else {
        $invoiceCheck->free();
        echo "No accounts_receivable table found. Skipping invoice migration.\n";
    }
    
    // Step 2: Migrate Payments (payment_payments) - as Payments (Dr Cash, Cr AR)
    echo "\n=== Step 2: Migrating Payments ===\n";
    $paymentCheck = $conn->query("SHOW TABLES LIKE 'payment_payments'");
    if ($paymentCheck->num_rows > 0) {
        $paymentCheck->free();
        
        // Get all Cleared/Sent payments that don't have journal entries yet
        $paymentQuery = "
            SELECT pp.id, pp.payment_number, pp.payment_date, pp.amount, pp.status, pp.bank_account_id, pp.notes
            FROM payment_payments pp
            WHERE pp.status IN ('Cleared', 'Sent')
            AND NOT EXISTS (
                SELECT 1 FROM journal_entries je 
                WHERE je.entry_number = CONCAT('JE-PAY-', pp.payment_number)
            )
            ORDER BY pp.id
        ";
        
        $paymentResult = $conn->query($paymentQuery);
        if ($paymentResult) {
            while ($payment = $paymentResult->fetch_assoc()) {
                $results['payments_processed']++;
                
                try {
                    $paymentId = intval($payment['id']);
                    $paymentNumber = $payment['payment_number'];
                    $paymentDate = $payment['payment_date'];
                    $amount = floatval($payment['amount']);
                    $bankAccountId = $payment['bank_account_id'] ? intval($payment['bank_account_id']) : null;
                    
                    $journalResult = createPaymentJournalEntry(
                        $conn,
                        $paymentId,
                        $paymentNumber,
                        $paymentDate,
                        $amount,
                        $bankAccountId,
                        null, // invoice_id
                        null, // cost_center_id
                        $payment['notes'] ?? "Migrated payment {$paymentNumber}"
                    );
                    
                    if ($journalResult['success']) {
                        $results['payments_created']++;
                        echo "✓ Created journal entry for payment {$paymentNumber} (ID: {$paymentId})\n";
                    } else {
                        $results['payments_skipped']++;
                        $results['errors'][] = "Payment {$paymentNumber}: {$journalResult['message']}";
                        echo "✗ Skipped payment {$paymentNumber}: {$journalResult['message']}\n";
                    }
                } catch (Exception $e) {
                    $results['payments_skipped']++;
                    $results['errors'][] = "Payment {$payment['payment_number']}: {$e->getMessage()}";
                    echo "✗ Error processing payment {$payment['payment_number']}: {$e->getMessage()}\n";
                }
            }
            $paymentResult->free();
        }
    } else {
        $paymentCheck->free();
        echo "No payment_payments table found. Skipping payment migration.\n";
    }
    
    // Step 3: Migrate Receipts (payment_receipts) - Dr Cash, Cr Revenue
    echo "\n=== Step 3: Migrating Receipts ===\n";
    $receiptCheck = $conn->query("SHOW TABLES LIKE 'payment_receipts'");
    if ($receiptCheck->num_rows > 0) {
        $receiptCheck->free();
        
        // Get all Cleared/Deposited receipts that don't have journal entries yet
        $receiptQuery = "
            SELECT pr.id, pr.receipt_number, pr.payment_date, pr.amount, pr.status, pr.bank_account_id, pr.notes
            FROM payment_receipts pr
            WHERE pr.status IN ('Cleared', 'Deposited')
            AND NOT EXISTS (
                SELECT 1 FROM journal_entries je 
                WHERE je.entry_number = CONCAT('JE-REC-', pr.receipt_number)
            )
            ORDER BY pr.id
        ";
        
        $receiptResult = $conn->query($receiptQuery);
        if ($receiptResult) {
            while ($receipt = $receiptResult->fetch_assoc()) {
                $results['receipts_processed']++;
                
                try {
                    $receiptId = intval($receipt['id']);
                    $receiptNumber = $receipt['receipt_number'];
                    $receiptDate = $receipt['payment_date'];
                    $amount = floatval($receipt['amount']);
                    $bankAccountId = $receipt['bank_account_id'] ? intval($receipt['bank_account_id']) : null;
                    
                    $journalResult = createReceiptJournalEntry(
                        $conn,
                        $receiptId,
                        $receiptNumber,
                        $receiptDate,
                        $amount,
                        $bankAccountId,
                        null, // cost_center_id
                        $receipt['notes'] ?? "Migrated receipt {$receiptNumber}"
                    );
                    
                    if ($journalResult['success']) {
                        $results['receipts_created']++;
                        echo "✓ Created journal entry for receipt {$receiptNumber} (ID: {$receiptId})\n";
                    } else {
                        $results['receipts_skipped']++;
                        $results['errors'][] = "Receipt {$receiptNumber}: {$journalResult['message']}";
                        echo "✗ Skipped receipt {$receiptNumber}: {$journalResult['message']}\n";
                    }
                } catch (Exception $e) {
                    $results['receipts_skipped']++;
                    $results['errors'][] = "Receipt {$receipt['receipt_number']}: {$e->getMessage()}";
                    echo "✗ Error processing receipt {$receipt['receipt_number']}: {$e->getMessage()}\n";
                }
            }
            $receiptResult->free();
        }
    } else {
        $receiptCheck->free();
        echo "No payment_receipts table found. Skipping receipt migration.\n";
    }
    
    // Commit transaction
    $conn->commit();
    
    // Summary
    echo "\n=== Migration Summary ===\n";
    echo "Invoices: {$results['invoices_processed']} processed, {$results['invoices_created']} created, {$results['invoices_skipped']} skipped\n";
    echo "Payments: {$results['payments_processed']} processed, {$results['payments_created']} created, {$results['payments_skipped']} skipped\n";
    echo "Receipts: {$results['receipts_processed']} processed, {$results['receipts_created']} created, {$results['receipts_skipped']} skipped\n";
    
    if (!empty($results['errors'])) {
        echo "\nErrors:\n";
        foreach ($results['errors'] as $error) {
            echo "  - {$error}\n";
        }
    }
    
    echo "\n✓ Migration completed successfully!\n";
    
} catch (Exception $e) {
    $conn->rollback();
    echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "</pre></body></html>";
?>
