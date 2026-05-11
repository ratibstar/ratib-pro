<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\SSL\Lifecycle;

use Ratib\InfrastructureMarketplace\Observability\InfrastructureAlertingService;

final class SslExpirationMonitor
{
    private \PDO $pdo;
    private InfrastructureAlertingService $alerts;

    public function __construct(\PDO $pdo, InfrastructureAlertingService $alerts) {
        $this->pdo = $pdo;
        $this->alerts = $alerts;
    }


    public function checkExpiring(int $daysThreshold = 14): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT public_id, resource_reference, expires_at
             FROM ratib_infra_services
             WHERE service_type = :service_type
               AND expires_at IS NOT NULL
               AND expires_at <= DATE_ADD(NOW(), INTERVAL :days DAY)'
        );
        $stmt->execute([
            'service_type' => 'ssl',
            'days' => $daysThreshold,
        ]);
        $count = 0;
        while (($row = $stmt->fetch(\PDO::FETCH_ASSOC)) && is_array($row)) {
            $count++;
            $daysLeft = (int) floor((strtotime((string) $row['expires_at']) - time()) / 86400);
            $this->alerts->sslExpiration((string) ($row['resource_reference'] ?? $row['public_id']), max(0, $daysLeft));
        }
        return $count;
    }
}

