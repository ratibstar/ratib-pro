<?php
declare(strict_types=1);

namespace Rateb\App\Logistics\Services;

use Rateb\App\Logistics\Contracts\CompanyNotifier;
use Rateb\App\Logistics\Services\Integration\ErpCompanyNotifier;

final class LogisticsNotificationService
{
    public const ENTITY_TYPE = 'logistics_shipment';

    public function __construct(private CompanyNotifier $notifier = new ErpCompanyNotifier())
    {
    }

    /** @param array<string, mixed> $shipment */
    public function shipmentCreated(int $companyId, array $shipment): void
    {
        $this->emit($companyId, $shipment, 'created', 'info', 'logistics_notify_shipment_created');
    }

    /** @param array<string, mixed> $shipment */
    public function shipmentDispatched(int $companyId, array $shipment): void
    {
        $this->emit($companyId, $shipment, 'dispatched', 'info', 'logistics_notify_shipment_dispatched');
    }

    /** @param array<string, mixed> $shipment */
    public function shipmentDelivered(int $companyId, array $shipment): void
    {
        $this->emit($companyId, $shipment, 'delivered', 'success', 'logistics_notify_shipment_delivered');
    }

    /** @param array<string, mixed> $shipment */
    public function shipmentFailed(int $companyId, array $shipment): void
    {
        $this->emit($companyId, $shipment, 'failed', 'warning', 'logistics_notify_shipment_failed');
    }

    /** @param array<string, mixed> $shipment */
    public function notifyStatus(int $companyId, array $shipment, string $status): void
    {
        match ($status) {
            'created' => $this->shipmentCreated($companyId, $shipment),
            'shipped' => $this->shipmentDispatched($companyId, $shipment),
            'delivered' => $this->shipmentDelivered($companyId, $shipment),
            'failed' => $this->shipmentFailed($companyId, $shipment),
            default => null,
        };
    }

    /** @param array<string, mixed> $shipment */
    private function emit(int $companyId, array $shipment, string $trigger, string $type, string $titleKey): void
    {
        if ($companyId < 1) {
            return;
        }
        $id = (int) ($shipment['id'] ?? 0);
        $tracking = (string) ($shipment['tracking_number'] ?? $id);
        $title = __($titleKey);
        if ($title === $titleKey) {
            $title = 'Logistics shipment ' . $trigger;
        }
        $message = __('logistics_notify_shipment_message', [
            'tracking' => $tracking,
            'status' => $trigger,
        ]);
        if ($message === 'logistics_notify_shipment_message') {
            $message = 'Shipment ' . $tracking . ' → ' . $trigger;
        }

        try {
            $this->notifier->notifyCompany(
                $companyId,
                $title,
                $message,
                $type,
                'logistics_shipment_' . $trigger,
                self::ENTITY_TYPE,
                $id > 0 ? $id : null
            );
        } catch (\Throwable $e) {
            error_log('Logistics notification failed: ' . $e->getMessage());
        }
    }
}
