<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories;

use Ratib\ContactCenter\App\Core\Database;
use Ratib\ContactCenter\App\Domain\Softphone\Enums\SoftphoneCallStatus;
use Ratib\ContactCenter\App\Domain\Softphone\Enums\SoftphoneDirection;

final class AgentSipSessionRepository
{
    public function upsertOnline(
        int $tenantId,
        int $agentId,
        string $extension,
        string $domain,
        ?string $sessionToken,
        ?string $userAgent
    ): void {
        $stmt = Database::connection()->prepare(
            'INSERT INTO rcc_agent_sip_sessions
             (agent_id, tenant_id, sip_extension, sip_domain, status, session_token, user_agent, last_ping, registered_at)
             VALUES (:aid, :tid, :ext, :dom, \'online\', :tok, :ua, NOW(3), NOW(3))
             ON DUPLICATE KEY UPDATE
               sip_extension = VALUES(sip_extension),
               sip_domain = VALUES(sip_domain),
               status = \'online\',
               session_token = VALUES(session_token),
               user_agent = VALUES(user_agent),
               last_ping = NOW(3),
               registered_at = COALESCE(registered_at, NOW(3)),
               updated_at = NOW(3)'
        );
        $stmt->execute([
            'aid' => $agentId,
            'tid' => $tenantId,
            'ext' => $extension,
            'dom' => $domain,
            'tok' => $sessionToken,
            'ua' => $userAgent,
        ]);
    }

    public function setOffline(int $tenantId, int $agentId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE rcc_agent_sip_sessions SET status = \'offline\', updated_at = NOW(3)
             WHERE tenant_id = :tid AND agent_id = :aid'
        );
        $stmt->execute(['tid' => $tenantId, 'aid' => $agentId]);
    }

    public function ping(int $tenantId, int $agentId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE rcc_agent_sip_sessions SET last_ping = NOW(3), status = \'online\'
             WHERE tenant_id = :tid AND agent_id = :aid'
        );
        $stmt->execute(['tid' => $tenantId, 'aid' => $agentId]);
    }
}
