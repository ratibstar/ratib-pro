<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Infrastructure\Voice;

use Ratib\ContactCenter\App\Application\Services\IvrSessionManager;
use Ratib\ContactCenter\App\Core\TenantContext;

/**
 * Asterisk AMI event adapter — delegates ALL IVR logic to IvrEngine via IvrSessionManager.
 */
final class AsteriskAmiAdapter
{
    private IvrSessionManager $sessionManager;

    /** @var array<string, int> channel → tenantId cache from dialplan vars */
    private array $channelTenants = [];

    public function __construct(?IvrSessionManager $sessionManager = null)
    {
        $this->sessionManager = $sessionManager ?? new IvrSessionManager();
    }

    /**
     * Newchannel / StasisStart / custom RCC inbound event.
     *
     * @param array<string, mixed> $event
     */
    public function onIncomingCall(array $event): void
    {
        $channelId = (string) ($event['Channel'] ?? $event['channel'] ?? '');
        $caller = (string) ($event['CallerIDNum'] ?? $event['caller_number'] ?? '');
        $callee = (string) ($event['Exten'] ?? $event['callee_number'] ?? '');
        $tenantId = $this->resolveTenantId($event);
        $erpCompanyId = isset($event['RCC_ERP_COMPANY_ID']) ? (int) $event['RCC_ERP_COMPANY_ID'] : null;
        $callUuid = isset($event['Linkedid']) ? (string) $event['Linkedid'] : null;

        if ($channelId === '' || $tenantId < 1) {
            error_log('[RCC AMI] IncomingCall ignored — missing channel or tenant.');
            return;
        }

        $this->channelTenants[$channelId] = $tenantId;
        TenantContext::set($tenantId, $erpCompanyId);

        try {
            $this->sessionManager->onIncomingCall(
                $tenantId,
                $channelId,
                $caller,
                $callee,
                $callUuid,
                $erpCompanyId,
                ['ami_event' => $event['Event'] ?? 'IncomingCall']
            );
        } catch (\Throwable $e) {
            error_log('[RCC AMI] IVR start failed: ' . $e->getMessage());
        }
    }

    /**
     * DTMF / RCC custom digit event.
     *
     * @param array<string, mixed> $event
     */
    public function onDtmf(array $event): void
    {
        $channelId = (string) ($event['Channel'] ?? '');
        $digit = (string) ($event['Digit'] ?? $event['digit'] ?? '');
        $tenantId = $this->resolveTenantId($event, $channelId);

        if ($channelId === '' || $digit === '' || $tenantId < 1) {
            return;
        }

        TenantContext::set($tenantId);
        $this->sessionManager->onDtmf($channelId, $tenantId, $digit);
    }

    /**
     * Hangup / ChannelDestroyed.
     *
     * @param array<string, mixed> $event
     */
    public function onHangup(array $event): void
    {
        $channelId = (string) ($event['Channel'] ?? '');
        $tenantId = $this->resolveTenantId($event, $channelId);

        if ($channelId === '' || $tenantId < 1) {
            return;
        }

        TenantContext::set($tenantId);
        $this->sessionManager->onHangup($channelId, $tenantId);
        unset($this->channelTenants[$channelId]);
    }

    /**
     * Custom timeout event from dialplan or ARI when Read() expires.
     *
     * @param array<string, mixed> $event
     */
    public function onDtmfTimeout(array $event): void
    {
        $channelId = (string) ($event['Channel'] ?? '');
        $tenantId = $this->resolveTenantId($event, $channelId);
        if ($channelId === '' || $tenantId < 1) {
            return;
        }
        TenantContext::set($tenantId);
        $this->sessionManager->onDtmfTimeout($channelId, $tenantId);
    }

    /**
     * Generic AMI dispatcher — wire to your AMI listener loop.
     *
     * @param array<string, mixed> $event
     */
    public function dispatch(array $event): void
    {
        $name = (string) ($event['Event'] ?? '');

        switch ($name) {
            case 'Newchannel':
            case 'StasisStart':
            case 'RCCIncomingCall':
                if ($this->isInboundIvrContext($event)) {
                    $this->onIncomingCall($event);
                }
                break;
            case 'DTMF':
            case 'RCCDTMF':
                $this->onDtmf($event);
                break;
            case 'Hangup':
            case 'ChannelDestroyed':
                $this->onHangup($event);
                break;
            case 'RCCDTMFTimeout':
                $this->onDtmfTimeout($event);
                break;
        }
    }

    /** @param array<string, mixed> $event */
    private function isInboundIvrContext(array $event): bool
    {
        $context = (string) ($event['Context'] ?? '');
        return str_starts_with($context, 'rcc-ivr') || isset($event['RCC_IVR']);
    }

    /** @param array<string, mixed> $event */
    private function resolveTenantId(array $event, string $channelId = ''): int
    {
        if (isset($event['RCC_TENANT_ID'])) {
            return (int) $event['RCC_TENANT_ID'];
        }
        if (isset($event['tenant_id'])) {
            return (int) $event['tenant_id'];
        }
        if ($channelId !== '' && isset($this->channelTenants[$channelId])) {
            return $this->channelTenants[$channelId];
        }
        return 0;
    }
}
