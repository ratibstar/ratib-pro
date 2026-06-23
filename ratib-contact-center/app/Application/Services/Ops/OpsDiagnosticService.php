<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services\Ops;

use Ratib\ContactCenter\App\Core\Database;
use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventType;
use Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\Ops\PbxServerRepository;
use Ratib\ContactCenter\App\Infrastructure\Voice\AmiClient;
use Ratib\ContactCenter\App\Infrastructure\WebRTC\SipGateway;

final class OpsDiagnosticService
{
    public function __construct(
        private readonly PbxServerRepository $pbx = new PbxServerRepository(),
        private readonly OpsAuditService $audit = new OpsAuditService(),
        private readonly SipGateway $sipGateway = new SipGateway()
    ) {
    }

    /** @return array<string, mixed> */
    public function healthCenter(int $tenantId): array
    {
        $checks = [];
        $score = 0;
        $max = 0;

        $add = static function (string $name, bool $pass, string $detail = '') use (&$checks, &$score, &$max): void {
            $max++;
            if ($pass) {
                $score++;
            }
            $checks[] = ['name' => $name, 'pass' => $pass, 'detail' => $detail];
        };

        try {
            $pdo = Database::connection();
            $tables = $pdo->query("SHOW TABLES LIKE 'rcc_%'")->rowCount();
            $add('database', true, (string) $tables . ' tables');
            $add('schema_minimum', $tables >= 30, 'expected >= 30 after 012');
        } catch (\Throwable $e) {
            $add('database', false, $e->getMessage());
            $add('schema_minimum', false, 'N/A');
        }

        $pbxRow = $this->pbx->findActive($tenantId);
        $add('pbx_active', $pbxRow !== null, $pbxRow !== null ? (string) $pbxRow['name'] : 'none');

        $ami = $this->diagAmi($tenantId);
        $add('ami_login', (bool) ($ami['ok'] ?? false), (string) ($ami['message'] ?? ''));

        $hub = $this->diagHub();
        $add('realtime_hub', (bool) ($hub['running'] ?? false), 'port ' . ($hub['port'] ?? ''));

        $add('voice_worker_script', is_file(RCC_ROOT . '/bin/rcc-voice-worker.php'));

        $sipCount = $this->countActive('rcc_sip_extensions', $tenantId);
        $add('sip_extensions', $sipCount > 0, (string) $sipCount);

        $queueCount = $this->countActiveQueues($tenantId);
        $add('queues', $queueCount > 0, (string) $queueCount);

        $agentCount = $this->countActive('rcc_agents', $tenantId);
        $add('agents', $agentCount > 0, (string) $agentCount);

        $ivrCount = $this->countIvrFlows($tenantId);
        $add('ivr_flows', $ivrCount > 0, (string) $ivrCount);

        $percent = $max > 0 ? (int) round(($score / $max) * 100) : 0;

        $result = [
            'checks' => $checks,
            'score' => $score,
            'max' => $max,
            'percent' => $percent,
            'timestamp' => gmdate('c'),
        ];

        EventBus::instance()->emit([
            'type' => EventType::OPS_HEALTH_UPDATED,
            'tenant_id' => $tenantId,
            'payload' => ['percent' => $percent, 'score' => $score, 'max' => $max],
        ]);

        return $result;
    }

    /** @return array<string, mixed> */
    public function diagAmi(int $tenantId): array
    {
        $row = $this->pbx->findActive($tenantId);
        if ($row === null) {
            $config = (array) require RCC_ROOT . '/config/asterisk.php';
        } else {
            $secretRef = (string) $row['ami_secret_ref'];
            $secret = getenv($secretRef) !== false ? (string) getenv($secretRef) : (string) (getenv('RCC_AMI_PASS') ?: '');
            $config = [
                'host' => (string) $row['ami_host'],
                'port' => (int) $row['ami_port'],
                'username' => (string) $row['ami_username'],
                'secret' => $secret,
                'connect_timeout' => 5.0,
                'read_timeout' => 0.5,
            ];
        }
        try {
            $ami = new AmiClient($config);
            $ami->connect();
            $ami->disconnect();
            $result = ['ok' => true, 'message' => 'AMI login OK', 'host' => $config['host'], 'port' => $config['port']];
        } catch (\Throwable $e) {
            $result = ['ok' => false, 'message' => $e->getMessage(), 'host' => $config['host'] ?? '', 'port' => $config['port'] ?? 0];
        }
        $this->emitDiag($tenantId, 'ami', $result);
        return $result;
    }

    /** @return array<string, mixed> */
    public function diagWebrtc(int $tenantId, int $agentId): array
    {
        try {
            $this->sipGateway->assertTenantAccess($tenantId, $agentId);
            $creds = $this->sipGateway->buildWebRtcConfig($tenantId, $agentId);
            $result = [
                'ok' => true,
                'wss_uri' => $creds['server'] ?? null,
                'extension' => $creds['authorizationUsername'] ?? null,
                'domain' => parse_url((string) ($creds['server'] ?? ''), PHP_URL_HOST),
            ];
        } catch (\Throwable $e) {
            $result = ['ok' => false, 'message' => $e->getMessage()];
        }
        $this->emitDiag($tenantId, 'webrtc', $result);
        return $result;
    }

    /** @return array<string, mixed> */
    public function diagHub(): array
    {
        $port = (int) (getenv('RCC_WEBSOCKET_PORT') ?: 9702);
        $fp = @fsockopen('127.0.0.1', $port, $errno, $errstr, 2);
        $running = is_resource($fp);
        if ($running) {
            fclose($fp);
        }
        return [
            'running' => $running,
            'port' => $port,
            'error' => $running ? '' : $errstr,
            'script' => is_file(RCC_ROOT . '/bin/rcc-realtime-hub.php'),
        ];
    }

    /** @return array<string, mixed> */
    public function diagVoiceWorker(): array
    {
        $path = RCC_ROOT . '/bin/rcc-voice-worker.php';
        return [
            'ok' => is_file($path),
            'path' => 'bin/rcc-voice-worker.php',
            'interface_autoload' => interface_exists(\Ratib\ContactCenter\App\Application\Contracts\QueueGatewayInterface::class),
        ];
    }

    /** @param array<string, mixed> $result */
    private function emitDiag(int $tenantId, string $kind, array $result): void
    {
        EventBus::instance()->emit([
            'type' => EventType::OPS_DIAGNOSTIC_RUN,
            'tenant_id' => $tenantId,
            'payload' => ['kind' => $kind, 'result' => $result],
        ]);
    }

    private function countActive(string $table, int $tenantId): int
    {
        try {
            $stmt = Database::connection()->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE tenant_id = :tid AND status = 'active'"
            );
            $stmt->execute(['tid' => $tenantId]);
            return (int) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function countActiveQueues(int $tenantId): int
    {
        try {
            $stmt = Database::connection()->prepare(
                "SELECT COUNT(*) FROM rcc_queues q
                 INNER JOIN rcc_queue_members m ON m.queue_id = q.id AND m.tenant_id = q.tenant_id
                 WHERE q.tenant_id = :tid AND q.status = 'active'"
            );
            $stmt->execute(['tid' => $tenantId]);
            return (int) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function countIvrFlows(int $tenantId): int
    {
        try {
            $stmt = Database::connection()->prepare(
                'SELECT COUNT(*) FROM rcc_ivr_flows WHERE tenant_id = :tid AND is_active = 1'
            );
            $stmt->execute(['tid' => $tenantId]);
            return (int) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
