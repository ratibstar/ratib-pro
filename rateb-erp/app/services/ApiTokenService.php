<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\ApiToken;
use Rateb\App\Models\User;

final class ApiTokenService
{
    public function createToken(int $userId, string $name, ?int $expiresDays = 365): array
    {
        $user = (new User())->find($userId);
        if (!$user) {
            throw new \RuntimeException('User not found');
        }

        $plain = bin2hex(random_bytes(32));
        $hash = hash('sha256', $plain);

        $expiresAt = $expiresDays ? date('Y-m-d H:i:s', time() + ($expiresDays * 86400)) : null;
        $modules = [];
        $branchId = null;
        if (!empty($user['company_id'])) {
            $modules = (new PlanLimitService())->getLimits((int) $user['company_id'])['modules'] ?? [];
        }
        if (function_exists('rateb_resolve_create_branch_id')) {
            $resolved = rateb_resolve_create_branch_id();
            if ($resolved > 0) {
                $branchId = $resolved;
            }
        }
        if ($branchId === null) {
            $portalBranch = (int) (\Rateb\App\Core\SessionManager::get('rateb_portal_branch_id', 0) ?? 0);
            if ($portalBranch > 0) {
                $branchId = $portalBranch;
            }
        }

        (new ApiToken())->create([
            'user_id' => $userId,
            'company_id' => $user['company_id'],
            'branch_id' => $branchId,
            'token_hash' => $hash,
            'name' => $name,
            'abilities' => json_encode($modules),
            'expires_at' => $expiresAt,
        ]);

        return ['token' => $plain, 'expires_at' => $expiresAt];
    }

    public function validateToken(string $plainToken): ?array
    {
        $hash = hash('sha256', $plainToken);
        $token = (new ApiToken())->queryOne(
            'SELECT t.*, u.is_super_admin, u.status AS user_status FROM rateb_api_tokens t JOIN rateb_users u ON u.id = t.user_id WHERE t.token_hash = :h LIMIT 1',
            ['h' => $hash]
        );

        if (!$token) {
            return null;
        }

        if ((string) ($token['user_status'] ?? '') !== 'active') {
            return null;
        }

        if ((int) ($token['is_super_admin'] ?? 0) === 1) {
            return null;
        }

        if (!empty($token['expires_at']) && strtotime((string) $token['expires_at']) < time()) {
            return null;
        }

        (new ApiToken())->queryOne(
            'UPDATE rateb_api_tokens SET last_used_at = NOW() WHERE id = :id',
            ['id' => (int) $token['id']]
        );

        return $token;
    }
}
