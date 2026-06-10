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

        (new ApiToken())->create([
            'user_id' => $userId,
            'company_id' => $user['company_id'],
            'token_hash' => $hash,
            'name' => $name,
            'abilities' => json_encode(['*']),
            'expires_at' => $expiresAt,
        ]);

        return ['token' => $plain, 'expires_at' => $expiresAt];
    }

    public function validateToken(string $plainToken): ?array
    {
        $hash = hash('sha256', $plainToken);
        $token = (new ApiToken())->queryOne(
            'SELECT t.*, u.is_super_admin FROM rateb_api_tokens t JOIN rateb_users u ON u.id = t.user_id WHERE t.token_hash = :h LIMIT 1',
            ['h' => $hash]
        );

        if (!$token) {
            return null;
        }

        if (!empty($token['expires_at']) && strtotime((string) $token['expires_at']) < time()) {
            return null;
        }

        $upd = (new ApiToken())->queryOne(
            'UPDATE rateb_api_tokens SET last_used_at = NOW() WHERE id = :id',
            ['id' => (int) $token['id']]
        );

        return $token;
    }
}
