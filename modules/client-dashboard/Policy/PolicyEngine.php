<?php
/**
 * Feature flags, maintenance, kill-switches — file/env driven, defaults permissive.
 */
declare(strict_types=1);

final class RATEB_ClientDashboard_PolicyEngine
{
    /** @var array<string, mixed> */
    private $policy;

    public function __construct()
    {
        $this->policy = self::loadPolicy();
    }

    /**
     * @return array<string, mixed>
     */
    private static function loadPolicy(): array
    {
        $defaults = [
            'maintenance_mode' => false,
            'kill_switch_mutations' => false,
            'async_default' => false,
            'allowed_actions' => null,
        ];

        $env = getenv('RATEB_CLIENT_HUB_POLICY_JSON');
        if (is_string($env) && $env !== '') {
            $j = json_decode($env, true);
            if (is_array($j)) {
                return array_merge($defaults, $j);
            }
        }

        $path = dirname(__DIR__) . '/config/policy.json';
        if (is_readable($path)) {
            $raw = @file_get_contents($path);
            if (is_string($raw) && $raw !== '') {
                $j = json_decode($raw, true);
                if (is_array($j)) {
                    return array_merge($defaults, $j);
                }
            }
        }

        return $defaults;
    }

    /**
     * @return array{allowed: bool, code: string, message: string}
     */
    public function assertMutationAllowed(string $verb): array
    {
        if (!empty($this->policy['maintenance_mode']) && $verb !== 'open_ticket') {
            return [
                'allowed' => false,
                'code' => 'maintenance_mode',
                'message' => 'Client hub mutations paused (maintenance).',
            ];
        }
        if (!empty($this->policy['kill_switch_mutations'])) {
            return [
                'allowed' => false,
                'code' => 'kill_switch',
                'message' => 'Mutations disabled by operations policy.',
            ];
        }
        $allowed = $this->policy['allowed_actions'] ?? null;
        if (is_array($allowed) && !empty($allowed) && !in_array($verb, $allowed, true)) {
            return [
                'allowed' => false,
                'code' => 'action_not_permitted',
                'message' => 'Action blocked by tenant policy.',
            ];
        }

        return ['allowed' => true, 'code' => 'ok', 'message' => ''];
    }

    /**
     * Sanitized policy snapshot for clients (no secrets).
     *
     * @return array<string, mixed>
     */
    public function publicSnapshot(): array
    {
        return [
            'maintenance_mode' => (bool) ($this->policy['maintenance_mode'] ?? false),
            'async_default' => (bool) ($this->policy['async_default'] ?? false),
            'mutations_globally_disabled' => (bool) ($this->policy['kill_switch_mutations'] ?? false),
        ];
    }

    public function preferAsyncQueue(): bool
    {
        return !empty($this->policy['async_default']);
    }
}
