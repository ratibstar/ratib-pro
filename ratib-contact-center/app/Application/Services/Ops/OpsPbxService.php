<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services\Ops;

use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventType;
use Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\Ops\PbxServerRepository;
use Ratib\ContactCenter\App\Infrastructure\Voice\AmiClient;

final class OpsPbxService
{
    public function __construct(
        private readonly PbxServerRepository $pbx = new PbxServerRepository(),
        private readonly OpsAuditService $audit = new OpsAuditService()
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(int $tenantId): array
    {
        return $this->pbx->listByTenant($tenantId);
    }

    /** @param array<string, mixed> $data */
    public function save(int $tenantId, array $data, ?int $userId = null): array
    {
        $id = isset($data['id']) ? (int) $data['id'] : null;
        if ($id !== null && $id < 1) {
            $id = null;
        }
        $payload = [
            'name' => (string) ($data['name'] ?? 'PBX'),
            'ami_host' => (string) ($data['ami_host'] ?? ''),
            'ami_port' => (int) ($data['ami_port'] ?? 5038),
            'ami_username' => (string) ($data['ami_username'] ?? ''),
            'ami_secret_ref' => (string) ($data['ami_secret_ref'] ?? 'RCC_AMI_PASS'),
            'sip_domain' => (string) ($data['sip_domain'] ?? ''),
            'wss_uri' => $data['wss_uri'] ?? null,
            'rtp_start' => isset($data['rtp_start']) ? (int) $data['rtp_start'] : null,
            'rtp_end' => isset($data['rtp_end']) ? (int) $data['rtp_end'] : null,
            'dialplan_package' => (string) ($data['dialplan_package'] ?? 'deploy/asterisk'),
            'status' => (string) ($data['status'] ?? 'draft'),
            'config_json' => is_array($data['config_json'] ?? null) ? $data['config_json'] : null,
        ];
        if ($payload['ami_host'] === '' || $payload['sip_domain'] === '') {
            throw new \InvalidArgumentException('ami_host and sip_domain are required.');
        }
        $savedId = $this->pbx->save($tenantId, $payload, $id);
        $this->audit->log($tenantId, 'ops.pbx.save', $userId, 'pbx_server', $savedId, ['name' => $payload['name']]);
        EventBus::instance()->emit([
            'type' => EventType::OPS_PBX_UPDATED,
            'tenant_id' => $tenantId,
            'payload' => ['pbx_id' => $savedId],
        ]);
        return $this->pbx->find($tenantId, $savedId) ?? ['id' => $savedId];
    }

    /** @return array<string, mixed> */
    public function testAmi(int $tenantId, int $pbxId, ?int $userId = null): array
    {
        $row = $this->pbx->find($tenantId, $pbxId);
        if ($row === null) {
            throw new \InvalidArgumentException('PBX record not found.');
        }
        $secret = $this->resolveSecret((string) $row['ami_secret_ref']);
        $config = [
            'host' => (string) $row['ami_host'],
            'port' => (int) $row['ami_port'],
            'username' => (string) $row['ami_username'],
            'secret' => $secret,
            'connect_timeout' => 5.0,
            'read_timeout' => 0.5,
        ];
        $result = ['ok' => false, 'message' => ''];
        try {
            $ami = new AmiClient($config);
            $ami->connect();
            $ami->disconnect();
            $result = ['ok' => true, 'message' => 'AMI login successful'];
            $this->pbx->setHealth($tenantId, $pbxId, 'ok');
        } catch (\Throwable $e) {
            $result = ['ok' => false, 'message' => $e->getMessage()];
            $this->pbx->setHealth($tenantId, $pbxId, 'fail');
        }
        $this->audit->log($tenantId, 'ops.pbx.test_ami', $userId, 'pbx_server', $pbxId, $result);
        EventBus::instance()->emit([
            'type' => EventType::OPS_DIAGNOSTIC_RUN,
            'tenant_id' => $tenantId,
            'payload' => ['kind' => 'ami', 'pbx_id' => $pbxId, 'ok' => $result['ok']],
        ]);
        return $result;
    }

    public function activate(int $tenantId, int $pbxId, ?int $userId = null): void
    {
        $test = $this->testAmi($tenantId, $pbxId, $userId);
        if (!$test['ok']) {
            throw new \RuntimeException('Cannot activate PBX: ' . $test['message']);
        }
        $this->pbx->activate($tenantId, $pbxId);
        $this->audit->log($tenantId, 'ops.pbx.activate', $userId, 'pbx_server', $pbxId);
        EventBus::instance()->emit([
            'type' => EventType::OPS_PBX_ACTIVATED,
            'tenant_id' => $tenantId,
            'payload' => ['pbx_id' => $pbxId],
        ]);
    }

    /** @return array<string, mixed> */
    public function dialplanPackageInfo(): array
    {
        $root = dirname(__DIR__, 3);
        $base = $root . '/deploy/asterisk';
        $files = ['extensions_rcc.conf', 'queues_rcc.conf', 'pjsip_rcc.conf', 'rtp_rcc.conf', 'INSTALL.md'];
        $present = [];
        foreach ($files as $f) {
            $present[$f] = is_file($base . '/' . $f);
        }
        return [
            'path' => 'deploy/asterisk',
            'files' => $present,
            'install_doc' => is_file($base . '/INSTALL.md') ? 'deploy/asterisk/INSTALL.md' : null,
        ];
    }

    private function resolveSecret(string $ref): string
    {
        $ref = trim($ref);
        if ($ref === '') {
            return (string) (getenv('RCC_AMI_PASS') ?: '');
        }
        $val = getenv($ref);
        return $val !== false ? (string) $val : '';
    }
}
