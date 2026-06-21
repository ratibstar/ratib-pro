<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Domain\IVR;

use Ratib\ContactCenter\App\Application\Services\IvrStateStreamer;
use Ratib\ContactCenter\App\Application\Contracts\IvrFlowRepositoryInterface;
use Ratib\ContactCenter\App\Application\Contracts\IvrNodeRepositoryInterface;
use Ratib\ContactCenter\App\Application\Contracts\IvrSessionRepositoryInterface;
use Ratib\ContactCenter\App\Application\Contracts\PbxCommandGatewayInterface;
use Ratib\ContactCenter\App\Core\ErpBridge;
use Ratib\ContactCenter\App\Core\TenantContext;
use Ratib\ContactCenter\App\Domain\IVR\Enums\IvrSessionStatus;
use Ratib\ContactCenter\App\Domain\IVR\NodeExecutors\NodeExecutorRegistry;

/**
 * Data-driven IVR state machine — ALL business logic lives here (not in controllers).
 *
 * Flow: startSession → loadFlow → executeNode (loop) with DTMF / timeout hooks.
 */
final class IvrEngine
{
    private const MAX_NODE_HOPS = 50;

    public function __construct(
        private readonly IvrFlowRepositoryInterface $flowRepository,
        private readonly IvrNodeRepositoryInterface $nodeRepository,
        private readonly IvrSessionRepositoryInterface $sessionRepository,
        private readonly NodeExecutorRegistry $executorRegistry,
        private readonly PbxCommandGatewayInterface $pbxGateway,
        private readonly ?IvrStateStreamer $ivrStreamer = null
    ) {
    }

    /**
     * Start a new IVR session for an inbound call.
     *
     * @param array<string, mixed> $initialState
     */
    public function startSession(
        int $callId,
        int $tenantId,
        ?string $callUuid = null,
        ?string $channelId = null,
        ?int $erpCompanyId = null,
        array $initialState = []
    ): IvrSession {
        TenantContext::set($tenantId, $erpCompanyId);

        $existing = $this->sessionRepository->findActiveByCallId($callId, $tenantId);
        if ($existing !== null) {
            return $existing;
        }

        $flow = $this->loadFlow($tenantId);
        if ($flow === null) {
            throw new \RuntimeException('No active IVR flow for tenant ' . $tenantId);
        }

        $locale = $this->resolveLocale($flow, $erpCompanyId);
        TenantContext::set($tenantId, $erpCompanyId, $locale);

        $entryNodeId = $flow->entryNodeId;
        if ($entryNodeId === null || $entryNodeId < 1) {
            $nodes = $this->nodeRepository->findByFlowId($flow->id);
            $entryNodeId = $nodes[0]->id ?? null;
            if ($entryNodeId !== null) {
                $this->flowRepository->updateEntryNode($flow->id, $entryNodeId);
            }
        }

        $state = array_merge([
            'started' => true,
            'flow_id' => $flow->id,
            'locale' => $locale,
        ], $initialState);

        $session = $this->sessionRepository->create(
            $callId,
            $callUuid,
            $tenantId,
            $flow->id,
            $entryNodeId,
            $state,
            $channelId,
            $locale
        );

        $this->ivrStreamer?->emitStarted(
            $tenantId,
            $session->id,
            $callId,
            $flow->id,
            $entryNodeId,
            $channelId
        );

        return $this->runUntilWaitOrComplete($session);
    }

    public function loadFlow(int $tenantId): ?IvrFlow
    {
        return $this->flowRepository->findActiveByTenant($tenantId);
    }

    /**
     * Execute current node and advance state machine until waiting for input or terminal.
     */
    public function executeNode(IvrSession $session): IvrSession
    {
        TenantContext::set($session->tenantId, null, $session->locale);

        if ($session->isTerminal()) {
            return $session;
        }

        return $this->runUntilWaitOrComplete($session);
    }

    /**
     * Push DTMF digit from AMI into active session and continue execution.
     */
    public function pushDtmfInput(int $sessionId, int $tenantId, string $digit): IvrSession
    {
        $session = $this->sessionRepository->findById($sessionId, $tenantId);
        if ($session === null) {
            throw new \RuntimeException('IVR session not found.');
        }
        if ($session->status !== IvrSessionStatus::WaitingInput) {
            return $session;
        }

        $digit = substr(trim($digit), 0, 1);
        $state = $session->state;
        $state['last_input'] = $digit;
        $state['awaiting_input'] = false;
        $state['inputs'] = $state['inputs'] ?? [];
        $state['inputs'][] = ['digit' => $digit, 'at' => time()];

        $collectNodeId = (int) ($state['collect_node_id'] ?? 0);
        $collectNode = $collectNodeId > 0
            ? $this->nodeRepository->findById($collectNodeId, $session->flowId)
            : null;

        $nextNodeId = $collectNode?->nextNodeId;

        $this->sessionRepository->persist(
            $session->id,
            $session->tenantId,
            $nextNodeId ?? $session->currentNodeId,
            $state,
            IvrSessionStatus::Active,
            0
        );

        $session = $this->sessionRepository->findById($session->id, $session->tenantId);
        if ($session === null) {
            throw new \RuntimeException('IVR session lost after DTMF persist.');
        }

        return $this->runUntilWaitOrComplete($session);
    }

    /**
     * Handle collect_input timeout — retry or fallback node.
     */
    public function handleTimeout(int $sessionId, int $tenantId): IvrSession
    {
        $session = $this->sessionRepository->findById($sessionId, $tenantId);
        if ($session === null || $session->status !== IvrSessionStatus::WaitingInput) {
            return $session ?? throw new \RuntimeException('IVR session not found.');
        }

        $collectNodeId = (int) ($session->state['collect_node_id'] ?? 0);
        $node = $collectNodeId > 0
            ? $this->nodeRepository->findById($collectNodeId, $session->flowId)
            : null;

        if ($node === null) {
            $this->sessionRepository->finalize($session->id, $tenantId, IvrSessionStatus::Timeout);
            return $this->sessionRepository->findById($sessionId, $tenantId)
                ?? throw new \RuntimeException('IVR session not found after timeout.');
        }

        $retryCount = $session->retryCount + 1;
        if ($retryCount < $node->maxRetries) {
            $state = $session->state;
            $state['timeout_retries'] = $retryCount;
            $this->sessionRepository->persist(
                $session->id,
                $tenantId,
                $node->id,
                $state,
                IvrSessionStatus::Active,
                $retryCount
            );
            $session = $this->sessionRepository->findById($sessionId, $tenantId);
            if ($session === null) {
                throw new \RuntimeException('IVR session not found after retry persist.');
            }
            return $this->runUntilWaitOrComplete($session);
        }

        $fallbackId = $node->fallbackNodeId;
        $state = $session->state;
        $state['timeout_exhausted'] = true;

        if ($fallbackId !== null && $fallbackId > 0) {
            $this->sessionRepository->persist(
                $session->id,
                $tenantId,
                $fallbackId,
                $state,
                IvrSessionStatus::Active,
                0
            );
            $session = $this->sessionRepository->findById($sessionId, $tenantId);
            if ($session === null) {
                throw new \RuntimeException('IVR session not found after fallback.');
            }
            return $this->runUntilWaitOrComplete($session);
        }

        $this->sessionRepository->finalize($session->id, $tenantId, IvrSessionStatus::Timeout);
        return $this->sessionRepository->findById($sessionId, $tenantId)
            ?? throw new \RuntimeException('IVR session not found after timeout finalize.');
    }

    /**
     * Finalize session on call hangup.
     */
    public function finalizeSession(int $sessionId, int $tenantId, IvrSessionStatus $status = IvrSessionStatus::Completed): void
    {
        $session = $this->sessionRepository->findById($sessionId, $tenantId);
        if ($session === null || $session->isTerminal()) {
            return;
        }
        $this->sessionRepository->finalize($sessionId, $tenantId, $status);
        $this->ivrStreamer?->emitCompleted($tenantId, $sessionId, $session->callId, $status->value);
    }

    public function findSessionByChannel(string $channelId, int $tenantId): ?IvrSession
    {
        return $this->sessionRepository->findActiveByChannelId($channelId, $tenantId);
    }

    private function runUntilWaitOrComplete(IvrSession $session): IvrSession
    {
        $hops = 0;
        $current = $session;

        while ($hops < self::MAX_NODE_HOPS) {
            if ($current->isTerminal()) {
                return $current;
            }

            if ($current->status === IvrSessionStatus::WaitingInput) {
                return $current;
            }

            $nodeId = $current->currentNodeId;
            if ($nodeId === null || $nodeId < 1) {
                $this->sessionRepository->finalize($current->id, $current->tenantId, IvrSessionStatus::Completed);
                break;
            }

            $node = $this->nodeRepository->findById($nodeId, $current->flowId);
            if ($node === null) {
                $this->sessionRepository->finalize($current->id, $current->tenantId, IvrSessionStatus::Failed);
                break;
            }

            $this->ivrStreamer?->emitNodeEntered(
                $current->tenantId,
                $current->id,
                $current->callId,
                $node->id,
                $node->type->value,
                $current->channelId
            );

            $result = $this->executorRegistry->executeNode($current, $node, $this->pbxGateway);

            $state = array_merge($current->state, $result->statePatch);
            if (isset($state['locale']) && in_array($state['locale'], ['en', 'ar'], true)) {
                TenantContext::set($current->tenantId, TenantContext::erpCompanyId(), (string) $state['locale']);
            }

            if (!$result->continueExecution) {
                if ($result->awaitInput) {
                    $this->sessionRepository->persist(
                        $current->id,
                        $current->tenantId,
                        $node->id,
                        $state,
                        IvrSessionStatus::WaitingInput,
                        $result->retryCount
                    );
                    $this->ivrStreamer?->emitWaitingInput(
                        $current->tenantId,
                        $current->id,
                        $current->callId,
                        $node->id,
                        $node->timeoutSeconds
                    );
                } elseif ($result->sessionStatus === IvrSessionStatus::Completed
                    || $result->sessionStatus === IvrSessionStatus::Failed
                    || $result->sessionStatus === IvrSessionStatus::Timeout) {
                    $this->sessionRepository->persist(
                        $current->id,
                        $current->tenantId,
                        $node->id,
                        $state,
                        $result->sessionStatus,
                        $result->retryCount
                    );
                    $this->sessionRepository->finalize($current->id, $current->tenantId, $result->sessionStatus);
                    $this->ivrStreamer?->emitCompleted(
                        $current->tenantId,
                        $current->id,
                        $current->callId,
                        $result->sessionStatus->value
                    );
                }
                break;
            }

            $nextId = $result->nextNodeId;
            $this->sessionRepository->persist(
                $current->id,
                $current->tenantId,
                $nextId,
                $state,
                IvrSessionStatus::Active,
                0
            );

            $current = $this->sessionRepository->findById($current->id, $current->tenantId);
            if ($current === null) {
                throw new \RuntimeException('IVR session lost during execution loop.');
            }

            if ($nextId === null) {
                $this->sessionRepository->finalize($current->id, $current->tenantId, IvrSessionStatus::Completed);
                break;
            }

            $hops++;
        }

        $refreshed = $this->sessionRepository->findById($session->id, $session->tenantId);
        return $refreshed ?? $session;
    }

    private function resolveLocale(IvrFlow $flow, ?int $erpCompanyId): string
    {
        if ($erpCompanyId !== null && $erpCompanyId > 0) {
            return ErpBridge::resolveLocaleForCompany($erpCompanyId, $flow->defaultLocale);
        }
        return in_array($flow->defaultLocale, ['en', 'ar'], true) ? $flow->defaultLocale : 'ar';
    }
}
