<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Repositories\SessionRepository;
use App\Services\AuthorizationService;
use RuntimeException;

final class AccessMiddleware
{
    public function __construct(
        private readonly AuthorizationService $authorization,
        private readonly SessionRepository $sessions
    ) {
    }

    /** @return array<string, mixed> */
    public function resolveCurrentUser(): array
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId <= 0 || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
            throw new RuntimeException('Authentication required', 401);
        }

        return [
            'id' => $userId,
            'role_id' => (int) ($_SESSION['role_id'] ?? 0),
            'country_id' => (int) ($_SESSION['country_id'] ?? $_SESSION['control_country_id'] ?? 0),
            'allowed_country_ids' => is_array($_SESSION['allowed_country_ids'] ?? null) ? $_SESSION['allowed_country_ids'] : null,
        ];
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $payload
     * @param callable(array<string, mixed>):array<string, mixed> $next
     * @return array<string, mixed>
     */
    public function handle(array $user, string $permission, array $payload, callable $next): array
    {
        $this->authorization->enforce($user, $permission);
        $this->authorization->enforceCountryScope($user, $payload);

        $sessionId = (string) session_id();
        $ipAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $deviceInfo = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        $this->sessions->recordSessionActivity((int) ($user['id'] ?? 0), $ipAddress, $deviceInfo, $sessionId);

        return $next($payload);
    }
}
