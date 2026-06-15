<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Models\Company;
use Rateb\App\Models\Invoice;
use PDO;

final class BillingAutomationService
{
    public function markOverdueInvoices(): int
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            "UPDATE rateb_invoices
             SET status = 'overdue'
             WHERE status = 'sent'
               AND due_date IS NOT NULL
               AND due_date < CURDATE()
               AND payment_status <> 'paid'"
        );
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function processDueReminders(): int
    {
        $db = Database::connection();
        $rows = $db->query(
            "SELECT i.*, c.name AS company_name, c.email AS company_email
             FROM rateb_invoices i
             INNER JOIN rateb_companies c ON c.id = i.company_id
             WHERE i.payment_status <> 'paid'
               AND i.status IN ('sent', 'overdue')
               AND i.due_date IS NOT NULL
               AND (
                    i.due_date = CURDATE()
                    OR i.due_date = DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                    OR i.due_date < CURDATE()
               )"
        )->fetchAll(PDO::FETCH_ASSOC);

        $count = 0;
        $notifier = new NotificationService();
        $mailer = new EmailAlertService();
        $today = date('Y-m-d');
        foreach ($rows as $row) {
            $invoiceId = (int) ($row['id'] ?? 0);
            $companyId = (int) ($row['company_id'] ?? 0);
            if ($invoiceId < 1 || $companyId < 1) {
                continue;
            }
            $due = (string) ($row['due_date'] ?? '');
            $trigger = 'invoice_due_soon';
            if ($due < $today) {
                $trigger = 'invoice_overdue';
            } elseif ($due === $today) {
                $trigger = 'invoice_due_today';
            }
            if ($this->wasNotifiedRecently($companyId, $trigger, $invoiceId)) {
                continue;
            }
            $vars = $this->invoiceMailVars($row);
            if ($trigger === 'invoice_overdue') {
                $notifier->notifyCompany(
                    $companyId,
                    __('invoice_overdue_alert', ['no' => $vars['invoice_no'], 'date' => $due]),
                    __('invoice_overdue_alert', ['no' => $vars['invoice_no'], 'date' => $due]),
                    'danger',
                    $trigger,
                    'invoice',
                    $invoiceId
                );
                $mailer->sendInvoiceOverdue($companyId, $vars);
            } else {
                $notifier->notifyCompany(
                    $companyId,
                    __('invoice_due_soon_alert', ['no' => $vars['invoice_no'], 'date' => $due]),
                    __('invoice_due_soon_alert', ['no' => $vars['invoice_no'], 'date' => $due]),
                    'warning',
                    $trigger,
                    'invoice',
                    $invoiceId
                );
                $mailer->sendInvoiceDueReminder($companyId, $vars);
            }
            $count++;
        }
        return $count;
    }

    /** @param array<string, mixed> $invoice */
    public function sendInvoiceToCustomer(array $invoice): bool
    {
        $companyId = (int) ($invoice['company_id'] ?? 0);
        if ($companyId < 1) {
            return false;
        }
        $company = (new Company())->find($companyId);
        if (!$company) {
            return false;
        }
        $vars = $this->invoiceMailVars($invoice, $company);
        $invoiceId = (int) ($invoice['id'] ?? 0);
        (new NotificationService())->notifyCompany(
            $companyId,
            __('invoice_sent_notification', ['no' => $vars['invoice_no']]),
            __('invoice_sent_message', ['no' => $vars['invoice_no'], 'total' => $vars['total'], 'currency' => $vars['currency']]),
            'info',
            'invoice_sent',
            'invoice',
            $invoiceId > 0 ? $invoiceId : null
        );
        return (new EmailAlertService())->sendInvoiceSent($companyId, $vars);
    }

    public function recalculatePaymentStatus(int $invoiceId): void
    {
        if ($invoiceId < 1) {
            return;
        }
        $invoice = (new Invoice())->find($invoiceId);
        if (!$invoice) {
            return;
        }
        if ((string) ($invoice['status'] ?? '') === 'paid') {
            (new Invoice())->update($invoiceId, ['payment_status' => 'paid']);
            return;
        }
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT COALESCE(SUM(amount), 0) AS paid FROM rateb_payments
             WHERE status = \'completed\' AND (invoice_id = :iid OR (invoice_id IS NULL AND company_id = :cid AND subscription_id <=> :sid))'
        );
        $stmt->execute([
            'iid' => $invoiceId,
            'cid' => (int) ($invoice['company_id'] ?? 0),
            'sid' => $invoice['subscription_id'] ?? null,
        ]);
        $paid = (float) ($stmt->fetch(PDO::FETCH_ASSOC)['paid'] ?? 0);
        $total = (float) ($invoice['total_amount'] ?? 0);
        $status = 'unpaid';
        if ($total > 0 && $paid >= $total) {
            $status = 'paid';
            (new Invoice())->update($invoiceId, ['payment_status' => 'paid', 'status' => 'paid']);
            return;
        }
        if ($paid > 0) {
            $status = 'partial';
        }
        (new Invoice())->update($invoiceId, ['payment_status' => $status]);
    }

    private function wasNotifiedRecently(int $companyId, string $trigger, int $invoiceId): bool
    {
        $row = (new Invoice())->queryOne(
            'SELECT id FROM rateb_notifications
             WHERE company_id = :cid AND trigger_type = :tt AND entity_type = \'invoice\' AND entity_id = :eid
               AND created_at >= DATE_SUB(NOW(), INTERVAL 3 DAY) LIMIT 1',
            ['cid' => $companyId, 'tt' => $trigger, 'eid' => $invoiceId]
        );
        return $row !== null;
    }

    /** @param array<string, mixed> $invoice */
    /** @param array<string, mixed>|null $company */
    /** @return array<string, string> */
    private function invoiceMailVars(array $invoice, ?array $company = null): array
    {
        $company = $company ?? (new Company())->find((int) ($invoice['company_id'] ?? 0)) ?? [];
        $id = (int) ($invoice['id'] ?? 0);
        $previewUrl = $id > 0 ? rateb_url('admin/invoices/' . $id . '/preview') : rateb_url('admin/invoices');
        return [
            'invoice_no' => (string) ($invoice['invoice_no'] ?? ''),
            'company' => (string) ($company['name'] ?? ''),
            'total' => number_format((float) ($invoice['total_amount'] ?? 0), 2),
            'currency' => (string) ($invoice['currency'] ?? 'SAR'),
            'due_date' => (string) ($invoice['due_date'] ?? ''),
            'issued_at' => (string) ($invoice['issued_at'] ?? ''),
            'preview_url' => $previewUrl,
        ];
    }
}
