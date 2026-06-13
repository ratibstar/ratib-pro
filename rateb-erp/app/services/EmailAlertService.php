<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Models\User;

final class EmailAlertService
{
    public function sendLowStock(int $companyId, string $itemName, float $qty): void
    {
        $this->broadcastToCompany($companyId, 'low_stock_alert', [
            'item' => $itemName,
            'qty' => (string) $qty,
        ]);
    }

    public function sendExpiry(int $companyId, string $itemName, string $date): void
    {
        $this->broadcastToCompany($companyId, 'expiry_alert', [
            'item' => $itemName,
            'date' => $date,
        ]);
    }

    public function sendContractExpiry(int $companyId, string $contractNo, string $date): void
    {
        $this->broadcastToCompany($companyId, 'contract_expiry_alert', [
            'no' => $contractNo,
            'date' => $date,
        ]);
    }

    public function sendApprovalRequest(int $userId, int $companyId, string $entityType, int $entityId): void
    {
        $user = (new User())->find($userId);
        if (!$user || empty($user['email'])) {
            return;
        }
        TenantContext::setCompanyId($companyId);
        (new MailService())->sendTemplateAsync((string) $user['email'], 'approval_request', [
            'type' => $entityType,
            'id' => (string) $entityId,
        ]);
    }

    public function sendApprovalResult(int $companyId, string $entityType, int $entityId, string $result): void
    {
        $slug = $result === 'approved' ? 'approval_completed' : 'approval_rejected';
        $this->broadcastToCompany($companyId, $slug, [
            'type' => $entityType,
            'id' => (string) $entityId,
        ]);
    }

    public function sendMaintenanceDue(int $companyId, string $deviceName, string $dueDate): void
    {
        $this->broadcastToCompany($companyId, 'maintenance_due_alert', [
            'device' => $deviceName,
            'date' => $dueDate,
        ]);
    }

    public function sendWarrantyExpiry(int $companyId, string $deviceName, string $date): void
    {
        $this->broadcastToCompany($companyId, 'warranty_expiry_alert', [
            'device' => $deviceName,
            'date' => $date,
        ]);
    }

    public function sendWorkflowOverdue(int $companyId, string $entityType, int $entityId): void
    {
        $this->broadcastToCompany($companyId, 'approval_request', [
            'type' => $entityType . ' (overdue)',
            'id' => (string) $entityId,
        ]);
    }

    public function sendSupplierAlert(int $companyId, string $name, string $type): void
    {
        $this->broadcastToCompany($companyId, 'low_stock_alert', [
            'item' => 'Supplier: ' . $name . ' (' . $type . ')',
            'qty' => '0',
        ]);
    }

    /** @param array<string,string> $vars */
    private function broadcastToCompany(int $companyId, string $template, array $vars): void
    {
        $users = (new User())->query(
            'SELECT email FROM rateb_users WHERE company_id = :cid AND status = \'active\' AND email IS NOT NULL AND email != \'\'',
            ['cid' => $companyId]
        );
        TenantContext::setCompanyId($companyId);
        $mail = new MailService();
        foreach ($users as $u) {
            $email = trim((string) ($u['email'] ?? ''));
            if ($email !== '') {
                $mail->sendTemplateAsync($email, $template, $vars);
            }
        }
    }
}
