<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services;

use Ratib\ContactCenter\App\Application\Contracts\IvrFlowRepositoryInterface;
use Ratib\ContactCenter\App\Application\Contracts\IvrNodeRepositoryInterface;
use Ratib\ContactCenter\App\Application\Contracts\IvrSessionRepositoryInterface;
use Ratib\ContactCenter\App\Application\Contracts\PbxCommandGatewayInterface;
use Ratib\ContactCenter\App\Application\Contracts\QueueGatewayInterface;
use Ratib\ContactCenter\App\Application\Contracts\TicketGatewayInterface;
use Ratib\ContactCenter\App\Application\Services\IvrStateStreamer;
use Ratib\ContactCenter\App\Application\Services\RealtimeOrchestrator;
use Ratib\ContactCenter\App\Core\Database;
use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventType;
use Ratib\ContactCenter\App\Core\TenantContext;
use Ratib\ContactCenter\App\Domain\IVR\Enums\IvrSessionStatus;
use Ratib\ContactCenter\App\Domain\IVR\IvrEngine;
use Ratib\ContactCenter\App\Domain\IVR\IvrSession;
use Ratib\ContactCenter\App\Domain\IVR\NodeExecutors\CollectInputExecutor;
use Ratib\ContactCenter\App\Domain\IVR\NodeExecutors\HangupExecutor;
use Ratib\ContactCenter\App\Domain\IVR\NodeExecutors\NodeExecutorRegistry;
use Ratib\ContactCenter\App\Domain\IVR\NodeExecutors\PlayMessageExecutor;
use Ratib\ContactCenter\App\Domain\IVR\NodeExecutors\RouteCallExecutor;
use Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\IvrFlowRepository;
use Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\IvrNodeRepository;
use Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\IvrSessionRepository;
use Ratib\ContactCenter\App\Infrastructure\Queue\QueueEngineGateway;
use Ratib\ContactCenter\App\Infrastructure\Ticket\TicketGateway;
use Ratib\ContactCenter\App\Infrastructure\Voice\AsteriskPbxCommandGateway;

/**
 * Facade for IVR lifecycle — wires engine + tenant-scoped call records.
 */
final class IvrSessionManager
{
    private IvrEngine $engine;

    public function __construct(?IvrEngine $engine = null)
    {
        $this->engine = $engine ?? self::buildEngine();
    }

    public function engine(): IvrEngine
    {
        return $this->engine;
    }

    /**
     * @param array<string, mixed> $callMeta
     */
    public function onIncomingCall(
        int $tenantId,
        string $channelId,
        string $callerNumber,
        string $calleeNumber,
        ?string $callUuid = null,
        ?int $erpCompanyId = null,
        array $callMeta = []
    ): IvrSession {
        TenantContext::set($tenantId, $erpCompanyId);

        $callId = $this->ensureCallRecord($tenantId, $callerNumber, $calleeNumber, $channelId, $callUuid);

        EventBus::instance()->emit([
            'type' => EventType::CALL_INCOMING,
            'tenant_id' => $tenantId,
            'call_id' => $callId,
            'payload' => [
                'caller_number' => $callerNumber,
                'callee_number' => $calleeNumber,
                'channel_id' => $channelId,
                'call_uuid' => $callUuid,
            ] + $callMeta,
        ]);

        return $this->engine->startSession(
            $callId,
            $tenantId,
            $callUuid,
            $channelId,
            $erpCompanyId,
            $callMeta
        );
    }

    public function onDtmf(string $channelId, int $tenantId, string $digit): ?IvrSession
    {
        $session = $this->engine->findSessionByChannel($channelId, $tenantId);
        if ($session === null) {
            return null;
        }
        return $this->engine->pushDtmfInput($session->id, $tenantId, $digit);
    }

    public function onHangup(string $channelId, int $tenantId): void
    {
        $session = $this->engine->findSessionByChannel($channelId, $tenantId);
        if ($session === null) {
            return;
        }
        $this->engine->finalizeSession($session->id, $tenantId, IvrSessionStatus::Completed);
        $this->markCallEnded($session->callId);

        EventBus::instance()->emit([
            'type' => EventType::CALL_ENDED,
            'tenant_id' => $tenantId,
            'call_id' => $session->callId,
            'ivr_session_id' => $session->id,
            'payload' => ['channel_id' => $channelId],
        ]);
    }

    public function onDtmfTimeout(string $channelId, int $tenantId): ?IvrSession
    {
        $session = $this->engine->findSessionByChannel($channelId, $tenantId);
        if ($session === null) {
            return null;
        }
        return $this->engine->handleTimeout($session->id, $tenantId);
    }

    public static function buildEngine(?EventBus $eventBus = null): IvrEngine
    {
        RealtimeOrchestrator::boot($eventBus);
        $bus = $eventBus ?? EventBus::instance();
        $ivrStreamer = new IvrStateStreamer($bus);

        $queueGateway = new QueueEngineGateway($bus);
        $ticketGateway = new TicketGateway();
        $pbx = new AsteriskPbxCommandGateway();

        $registry = new NodeExecutorRegistry([
            new PlayMessageExecutor(),
            new CollectInputExecutor(),
            new RouteCallExecutor($queueGateway, $ticketGateway),
            new HangupExecutor(),
        ]);

        return new IvrEngine(
            new IvrFlowRepository(),
            new IvrNodeRepository(),
            new IvrSessionRepository(),
            $registry,
            $pbx,
            $ivrStreamer
        );
    }

    private function ensureCallRecord(
        int $tenantId,
        string $caller,
        string $callee,
        string $channelId,
        ?string $uuid
    ): int {
        $pdo = Database::connection();
        if ($uuid !== null && $uuid !== '') {
            $check = $pdo->prepare('SELECT id FROM rcc_calls WHERE uuid = :uuid LIMIT 1');
            $check->execute(['uuid' => $uuid]);
            $existing = $check->fetchColumn();
            if ($existing !== false) {
                return (int) $existing;
            }
        }

        $uuid = $uuid ?: self::uuid4();
        $stmt = $pdo->prepare(
            'INSERT INTO rcc_calls (tenant_id, uuid, direction, status, caller_number, callee_number, channel_id, started_at)
             VALUES (:tid, :uuid, \'inbound\', \'ivr\', :caller, :callee, :ch, NOW())'
        );
        $stmt->execute([
            'tid' => $tenantId,
            'uuid' => $uuid,
            'caller' => $caller,
            'callee' => $callee,
            'ch' => $channelId,
        ]);
        return (int) $pdo->lastInsertId();
    }

    private function markCallEnded(int $callId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE rcc_calls SET status = \'completed\', ended_at = NOW() WHERE id = :id'
        );
        $stmt->execute(['id' => $callId]);
    }

    private static function uuid4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s-%s-%s-%s-%s', str_split(bin2hex($data), 4));
    }
}
