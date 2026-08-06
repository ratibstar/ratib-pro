<?php
declare(strict_types=1);

namespace Rateb\App\Logistics\Repositories;

use Rateb\App\Core\Model;
use Rateb\App\Logistics\Models\LogisticsDeliveryProof;

class LogisticsDeliveryProofRepository extends AbstractLogisticsRepository
{
    protected function newModel(): Model
    {
        return new LogisticsDeliveryProof();
    }

    /** @return array<string, mixed>|null */
    public function findByShipment(int $shipmentId, int $companyId): ?array
    {
        $rows = $this->listForCompany($companyId, 500, 0);
        foreach ($rows as $row) {
            if ((int) ($row['shipment_id'] ?? 0) === $shipmentId) {
                return $row;
            }
        }

        return null;
    }
}
