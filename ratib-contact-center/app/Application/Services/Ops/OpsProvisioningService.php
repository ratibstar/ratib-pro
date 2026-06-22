<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services\Ops;

use Ratib\ContactCenter\App\Core\Database;
use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventType;

final class OpsProvisioningService
{
    public function __construct(private readonly OpsAuditService $audit = new OpsAuditService())
    {
    }

    /** @return list<array<string, mixed>> */
    public function listSipExtensions(int $tenantId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, tenant_id, agent_id, extension, sip_username, sip_password_ref, sip_domain, wss_uri, webrtc_enabled, status
             FROM rcc_sip_extensions WHERE tenant_id = :tid ORDER BY extension ASC'
        );
        $stmt->execute(['tid' => $tenantId]);
        return $stmt->fetchAll() ?: [];
    }

    /** @param array<string, mixed> $data */
    public function saveSipExtension(int $tenantId, array $data, ?int $userId = null): array
    {
        $pdo = Database::connection();
        $id = isset($data['id']) ? (int) $data['id'] : 0;
        $fields = [
            'agent_id' => isset($data['agent_id']) ? (int) $data['agent_id'] : null,
            'extension' => (string) ($data['extension'] ?? ''),
            'sip_username' => (string) ($data['sip_username'] ?? $data['extension'] ?? ''),
            'sip_password_ref' => (string) ($data['sip_password_ref'] ?? ''),
            'sip_domain' => (string) ($data['sip_domain'] ?? ''),
            'wss_uri' => $data['wss_uri'] ?? null,
            'webrtc_enabled' => !empty($data['webrtc_enabled']) ? 1 : 0,
            'status' => (string) ($data['status'] ?? 'active'),
        ];
        if ($fields['extension'] === '' || $fields['sip_domain'] === '') {
            throw new \InvalidArgumentException('extension and sip_domain required');
        }
        if ($id > 0) {
            $stmt = $pdo->prepare(
                'UPDATE rcc_sip_extensions SET agent_id=:aid, extension=:ext, sip_username=:user, sip_password_ref=:pref,
                 sip_domain=:dom, wss_uri=:wss, webrtc_enabled=:webrtc, status=:st, updated_at=NOW()
                 WHERE id=:id AND tenant_id=:tid'
            );
            $stmt->execute([
                'aid' => $fields['agent_id'], 'ext' => $fields['extension'], 'user' => $fields['sip_username'],
                'pref' => $fields['sip_password_ref'], 'dom' => $fields['sip_domain'], 'wss' => $fields['wss_uri'],
                'webrtc' => $fields['webrtc_enabled'], 'st' => $fields['status'], 'id' => $id, 'tid' => $tenantId,
            ]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO rcc_sip_extensions
                 (tenant_id, agent_id, extension, sip_username, sip_password_ref, sip_domain, wss_uri, webrtc_enabled, status)
                 VALUES (:tid,:aid,:ext,:user,:pref,:dom,:wss,:webrtc,:st)'
            );
            $stmt->execute([
                'tid' => $tenantId, 'aid' => $fields['agent_id'], 'ext' => $fields['extension'],
                'user' => $fields['sip_username'], 'pref' => $fields['sip_password_ref'], 'dom' => $fields['sip_domain'],
                'wss' => $fields['wss_uri'], 'webrtc' => $fields['webrtc_enabled'], 'st' => $fields['status'],
            ]);
            $id = (int) $pdo->lastInsertId();
        }
        $this->audit->log($tenantId, 'ops.sip.save', $userId, 'sip_extension', $id, ['extension' => $fields['extension']]);
        EventBus::instance()->emit([
            'type' => EventType::OPS_SIP_UPDATED,
            'tenant_id' => $tenantId,
            'payload' => ['sip_extension_id' => $id],
        ]);
        return ['id' => $id] + $fields;
    }

    public function deleteSipExtension(int $tenantId, int $id, ?int $userId = null): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE rcc_sip_extensions SET status='inactive', updated_at=NOW() WHERE tenant_id=:tid AND id=:id"
        );
        $stmt->execute(['tid' => $tenantId, 'id' => $id]);
        $this->audit->log($tenantId, 'ops.sip.delete', $userId, 'sip_extension', $id);
    }

    /** @return list<array<string, mixed>> */
    public function listQueues(int $tenantId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT id, code, name, name_ar, sla_target_seconds, status, strategy FROM rcc_queues WHERE tenant_id=:tid ORDER BY code'
        );
        $stmt->execute(['tid' => $tenantId]);
        $queues = $stmt->fetchAll() ?: [];
        $memberStmt = $pdo->prepare(
            'SELECT agent_id FROM rcc_queue_members WHERE tenant_id = :tid AND queue_id = :qid'
        );
        foreach ($queues as &$queue) {
            $memberStmt->execute(['tid' => $tenantId, 'qid' => (int) $queue['id']]);
            $queue['member_agent_ids'] = array_map(
                static fn ($id) => (int) $id,
                $memberStmt->fetchAll(\PDO::FETCH_COLUMN) ?: []
            );
        }
        unset($queue);
        return $queues;
    }

    /** @param array<string, mixed> $data */
    public function saveQueue(int $tenantId, array $data, ?int $userId = null): array
    {
        $pdo = Database::connection();
        $id = isset($data['id']) ? (int) $data['id'] : 0;
        $code = (string) ($data['code'] ?? '');
        $name = (string) ($data['name'] ?? '');
        if ($code === '' || $name === '') {
            throw new \InvalidArgumentException('code and name required');
        }
        if ($id > 0) {
            $stmt = $pdo->prepare(
                'UPDATE rcc_queues SET code=:code, name=:name, name_ar=:name_ar, sla_target_seconds=:sla, status=:st, strategy=:strat, updated_at=NOW()
                 WHERE id=:id AND tenant_id=:tid'
            );
            $stmt->execute([
                'code' => $code, 'name' => $name, 'name_ar' => $data['name_ar'] ?? null,
                'sla' => (int) ($data['sla_target_seconds'] ?? 300), 'st' => (string) ($data['status'] ?? 'active'),
                'strat' => (string) ($data['strategy'] ?? 'rrmemory'), 'id' => $id, 'tid' => $tenantId,
            ]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO rcc_queues (tenant_id, code, name, name_ar, sla_target_seconds, status, strategy)
                 VALUES (:tid,:code,:name,:name_ar,:sla,:st,:strat)'
            );
            $stmt->execute([
                'tid' => $tenantId, 'code' => $code, 'name' => $name, 'name_ar' => $data['name_ar'] ?? null,
                'sla' => (int) ($data['sla_target_seconds'] ?? 300), 'st' => (string) ($data['status'] ?? 'active'),
                'strat' => (string) ($data['strategy'] ?? 'rrmemory'),
            ]);
            $id = (int) $pdo->lastInsertId();
        }
        $this->audit->log($tenantId, 'ops.queue.save', $userId, 'queue', $id, ['code' => $code]);
        EventBus::instance()->emit([
            'type' => EventType::OPS_QUEUE_UPDATED,
            'tenant_id' => $tenantId,
            'queue_id' => $id,
            'payload' => ['code' => $code],
        ]);
        return ['id' => $id, 'code' => $code, 'name' => $name];
    }

    /** @param list<int> $agentIds */
    public function saveQueueMembers(int $tenantId, int $queueId, array $agentIds, ?int $userId = null): void
    {
        $pdo = Database::connection();
        $pdo->prepare('DELETE FROM rcc_queue_members WHERE tenant_id=:tid AND queue_id=:qid')->execute(['tid' => $tenantId, 'qid' => $queueId]);
        $ins = $pdo->prepare(
            'INSERT INTO rcc_queue_members (tenant_id, queue_id, agent_id, penalty, is_paused) VALUES (:tid,:qid,:aid,0,0)'
        );
        foreach ($agentIds as $aid) {
            $aid = (int) $aid;
            if ($aid > 0) {
                $ins->execute(['tid' => $tenantId, 'qid' => $queueId, 'aid' => $aid]);
            }
        }
        $this->audit->log($tenantId, 'ops.queue.members', $userId, 'queue', $queueId, ['agents' => $agentIds]);
    }

    /** @return list<array<string, mixed>> */
    public function listIvrFlows(int $tenantId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, name, is_active, entry_node_id, default_locale, created_at, updated_at
             FROM rcc_ivr_flows WHERE tenant_id=:tid ORDER BY id DESC'
        );
        $stmt->execute(['tid' => $tenantId]);
        return $stmt->fetchAll() ?: [];
    }

    /** @param array<string, mixed> $flow @param list<array<string, mixed>> $nodes */
    public function saveIvrFlow(int $tenantId, array $flow, array $nodes, ?int $userId = null): array
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $flowId = isset($flow['id']) ? (int) $flow['id'] : 0;
            $name = (string) ($flow['name'] ?? 'Production IVR');
            if ($flowId > 0) {
                $pdo->prepare('UPDATE rcc_ivr_flows SET name=:name, updated_at=NOW() WHERE id=:id AND tenant_id=:tid')
                    ->execute(['name' => $name, 'id' => $flowId, 'tid' => $tenantId]);
                $pdo->prepare('DELETE FROM rcc_ivr_nodes WHERE flow_id=:fid')->execute(['fid' => $flowId]);
            } else {
                $pdo->prepare(
                    'INSERT INTO rcc_ivr_flows (tenant_id, name, is_active, default_locale) VALUES (:tid,:name,0,:locale)'
                )->execute(['tid' => $tenantId, 'name' => $name, 'locale' => (string) ($flow['default_locale'] ?? 'ar')]);
                $flowId = (int) $pdo->lastInsertId();
            }
            $entryNodeId = null;
            $ins = $pdo->prepare(
                'INSERT INTO rcc_ivr_nodes (flow_id, type, payload, sort_order, timeout_seconds, max_retries)
                 VALUES (:fid,:type,:payload,:ord,:timeout,:retries)'
            );
            foreach ($nodes as $i => $node) {
                $type = (string) ($node['type'] ?? $node['node_type'] ?? 'play_message');
                $payload = $node['payload'] ?? $node['config'] ?? $node['config_json'] ?? [];
                $ins->execute([
                    'fid' => $flowId,
                    'type' => $type,
                    'payload' => json_encode(is_array($payload) ? $payload : ['message' => (string) $payload], JSON_UNESCAPED_UNICODE),
                    'ord' => (int) ($node['sort_order'] ?? $i),
                    'timeout' => (int) ($node['timeout_seconds'] ?? 10),
                    'retries' => (int) ($node['max_retries'] ?? 3),
                ]);
                if ($i === 0) {
                    $entryNodeId = (int) $pdo->lastInsertId();
                }
            }
            if ($entryNodeId !== null) {
                $pdo->prepare('UPDATE rcc_ivr_flows SET entry_node_id=:nid WHERE id=:fid AND tenant_id=:tid')
                    ->execute(['nid' => $entryNodeId, 'fid' => $flowId, 'tid' => $tenantId]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        $this->audit->log($tenantId, 'ops.ivr.save', $userId, 'ivr_flow', $flowId);
        EventBus::instance()->emit(['type' => EventType::OPS_IVR_UPDATED, 'tenant_id' => $tenantId, 'payload' => ['flow_id' => $flowId]]);
        return ['id' => $flowId, 'name' => $name];
    }

    public function publishIvrFlow(int $tenantId, int $flowId, ?int $userId = null): void
    {
        $pdo = Database::connection();
        $pdo->prepare('UPDATE rcc_ivr_flows SET is_active=0 WHERE tenant_id=:tid')->execute(['tid' => $tenantId]);
        $pdo->prepare('UPDATE rcc_ivr_flows SET is_active=1, updated_at=NOW() WHERE tenant_id=:tid AND id=:id')
            ->execute(['tid' => $tenantId, 'id' => $flowId]);
        $this->audit->log($tenantId, 'ops.ivr.publish', $userId, 'ivr_flow', $flowId);
        EventBus::instance()->emit(['type' => EventType::OPS_IVR_PUBLISHED, 'tenant_id' => $tenantId, 'payload' => ['flow_id' => $flowId]]);
    }

    /** @return list<array<string, mixed>> */
    public function listAgents(int $tenantId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT a.id, a.user_id, a.extension, a.display_name, a.email, a.is_senior, a.status,
                    u.email AS user_email
             FROM rcc_agents a
             LEFT JOIN rcc_users u ON u.id = a.user_id AND u.tenant_id = a.tenant_id
             WHERE a.tenant_id=:tid ORDER BY a.display_name'
        );
        $stmt->execute(['tid' => $tenantId]);
        return $stmt->fetchAll() ?: [];
    }

    /** @param array<string, mixed> $data */
    public function provisionAgent(int $tenantId, array $data, ?int $userId = null): array
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $email = (string) ($data['email'] ?? '');
            $displayName = (string) ($data['display_name'] ?? '');
            $extension = (string) ($data['extension'] ?? '');
            if ($email === '' || $displayName === '' || $extension === '') {
                throw new \InvalidArgumentException('email, display_name, extension required');
            }
            $uid = isset($data['user_id']) ? (int) $data['user_id'] : 0;
            if ($uid < 1) {
                $stmt = $pdo->prepare('SELECT id FROM rcc_users WHERE tenant_id=:tid AND email=:email LIMIT 1');
                $stmt->execute(['tid' => $tenantId, 'email' => $email]);
                $uid = (int) ($stmt->fetchColumn() ?: 0);
            }
            if ($uid < 1) {
                $hash = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
                $pdo->prepare(
                    'INSERT INTO rcc_users (tenant_id, email, password_hash, full_name, locale, status)
                     VALUES (:tid,:email,:hash,:name,:locale,\'active\')'
                )->execute([
                    'tid' => $tenantId, 'email' => $email, 'hash' => $hash,
                    'name' => $displayName, 'locale' => (string) ($data['locale'] ?? 'ar'),
                ]);
                $uid = (int) $pdo->lastInsertId();
                $roleId = (int) ($pdo->query("SELECT id FROM rcc_roles WHERE slug='agent' AND tenant_id IS NULL LIMIT 1")->fetchColumn() ?: 3);
                $pdo->prepare('INSERT IGNORE INTO rcc_user_roles (user_id, role_id, tenant_id) VALUES (:uid,:rid,:tid)')
                    ->execute(['uid' => $uid, 'rid' => $roleId, 'tid' => $tenantId]);
            }
            $agentId = isset($data['agent_id']) ? (int) $data['agent_id'] : 0;
            if ($agentId > 0) {
                $pdo->prepare(
                    'UPDATE rcc_agents SET user_id=:uid, extension=:ext, display_name=:name, email=:email, status=:st, updated_at=NOW()
                     WHERE id=:id AND tenant_id=:tid'
                )->execute([
                    'uid' => $uid, 'ext' => $extension, 'name' => $displayName, 'email' => $email,
                    'st' => (string) ($data['status'] ?? 'active'), 'id' => $agentId, 'tid' => $tenantId,
                ]);
            } else {
                $pdo->prepare(
                    'INSERT INTO rcc_agents (tenant_id, user_id, extension, display_name, email, is_senior, status)
                     VALUES (:tid,:uid,:ext,:name,:email,:senior,\'active\')'
                )->execute([
                    'tid' => $tenantId, 'uid' => $uid, 'ext' => $extension, 'name' => $displayName,
                    'email' => $email, 'senior' => !empty($data['is_senior']) ? 1 : 0,
                ]);
                $agentId = (int) $pdo->lastInsertId();
            }
            if (!empty($data['queue_ids']) && is_array($data['queue_ids'])) {
                foreach ($data['queue_ids'] as $qid) {
                    $qid = (int) $qid;
                    if ($qid > 0) {
                        $pdo->prepare(
                            'INSERT IGNORE INTO rcc_queue_members (tenant_id, queue_id, agent_id) VALUES (:tid,:qid,:aid)'
                        )->execute(['tid' => $tenantId, 'qid' => $qid, 'aid' => $agentId]);
                    }
                }
            }
            if (!empty($data['provision_sip'])) {
                $sipDomain = (string) ($data['sip_domain'] ?? '');
                $wss = $data['wss_uri'] ?? null;
                $pref = (string) ($data['sip_password_ref'] ?? ('RCC_SIP_EXT_' . $extension));
                if ($sipDomain !== '') {
                    $pdo->prepare(
                        'INSERT INTO rcc_sip_extensions (tenant_id, agent_id, extension, sip_username, sip_password_ref, sip_domain, wss_uri, webrtc_enabled, status)
                         VALUES (:tid,:aid,:ext,:ext,:pref,:dom,:wss,1,\'active\')
                         ON DUPLICATE KEY UPDATE agent_id=VALUES(agent_id), sip_domain=VALUES(sip_domain), wss_uri=VALUES(wss_uri), status=\'active\', updated_at=NOW()'
                    )->execute(['tid' => $tenantId, 'aid' => $agentId, 'ext' => $extension, 'pref' => $pref, 'dom' => $sipDomain, 'wss' => $wss]);
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        $this->audit->log($tenantId, 'ops.agent.provision', $userId, 'agent', $agentId, ['email' => $email]);
        EventBus::instance()->emit(['type' => EventType::OPS_AGENT_PROVISIONED, 'tenant_id' => $tenantId, 'agent_id' => $agentId, 'payload' => ['extension' => $extension]]);
        return ['agent_id' => $agentId, 'user_id' => $uid, 'extension' => $extension];
    }
}
