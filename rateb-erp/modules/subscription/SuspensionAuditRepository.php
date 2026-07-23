<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

use Rateb\App\Core\Database;

/**
 * Optional shadow-mode audit log for suspension decisions.
 * Never used to block access.
 */
class SuspensionAuditRepository
{
    /**
     * @return int inserted id, or 0 on skip/failure
     */
    public function record(SuspensionDecision $decision): int
    {
        if ($decision->companyId() < 1) {
            return 0;
        }

        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare(
                'INSERT INTO rateb_subscription_suspension_audit
                    (company_id, decision, reason, created_at)
                 VALUES
                    (:company_id, :decision, :reason, NOW())'
            );
            $stmt->execute([
                'company_id' => $decision->companyId(),
                'decision' => $decision->isEligible() ? 'eligible' : 'not_eligible',
                'reason' => substr($decision->reason(), 0, 255),
            ]);
            return (int) $pdo->lastInsertId();
        } catch (\Throwable $e) {
            error_log('RATEB SuspensionAuditRepository::record: ' . $e->getMessage());
            return 0;
        }
    }
}
