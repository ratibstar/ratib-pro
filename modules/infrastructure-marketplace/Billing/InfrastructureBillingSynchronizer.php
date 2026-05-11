<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Billing;

final class InfrastructureBillingSynchronizer
{
    private \PDO $pdo;
    private InfrastructureBillingMetadataBridge $metadataBridge;

    public function __construct(\PDO $pdo, InfrastructureBillingMetadataBridge $metadataBridge) {
        $this->pdo = $pdo;
        $this->metadataBridge = $metadataBridge;
    }


    /**
     * @return array<string, mixed>
     */
    public function buildProvisioningInvoiceLink(string $orderPublicId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ratib_infra_orders WHERE public_id = :public_id LIMIT 1');
        $stmt->execute(['public_id' => $orderPublicId]);
        $order = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($order)) {
            return ['ok' => false, 'reason' => 'order_not_found'];
        }
        return [
            'ok' => true,
            'order_public_id' => $orderPublicId,
            'billing_metadata' => $this->metadataBridge->invoiceMetadata($order),
            'recurring_cycle_prepared' => true,
            'renewal_event_prepared' => true,
        ];
    }
}

