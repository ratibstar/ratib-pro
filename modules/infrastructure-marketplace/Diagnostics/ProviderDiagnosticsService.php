<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Diagnostics;

use RATEB\InfrastructureMarketplace\Config\ModuleConfig;
use RATEB\InfrastructureMarketplace\Http\Clients\CurlHttpClient;
use RATEB\InfrastructureMarketplace\Providers\Activation\ProviderActivationRegistry;
use RATEB\InfrastructureMarketplace\Security\Secrets\SecretManager;

final class ProviderDiagnosticsService
{
    private CurlHttpClient $http;
    private ?ProviderActivationRegistry $activations;
    private SecretManager $secret;

    public function __construct(?\PDO $pdo = null)
    {
        $this->http = new CurlHttpClient(10);
        $this->activations = $pdo !== null ? new ProviderActivationRegistry($pdo) : null;
        $this->secret = SecretManager::withDefaultProviders($pdo);
    }

    /**
     * @return array<string, mixed>
     */
    public function verify(): array
    {
        $checks = [];

        $checks[] = $this->cpanelCheck();
        $checks[] = $this->cloudflareCheck($this->secret);
        $checks[] = $this->namecheapCheck($this->secret);
        $checks[] = $this->acmeCheck();
        $checks[] = $this->dnsPropagationSupportCheck();

        return ['checks' => $checks];
    }

    /**
     * @return array<string, mixed>
     */
    private function cpanelCheck(): array
    {
        $started = microtime(true);
        $requestId = bin2hex(random_bytes(8));
        if ($this->providerDisabled('hosting')) {
            return $this->withTiming(['name' => 'cpanel_connectivity', 'status' => 'WARN', 'message' => 'disabled_provider', 'request_id' => $requestId], $started);
        }
        $base = ModuleConfig::cpanelWhmBaseUrl();
        if ($base === null) {
            return $this->withTiming(['name' => 'cpanel_connectivity', 'status' => 'WARN', 'message' => 'missing_config', 'request_id' => $requestId], $started);
        }
        $credentialsReady = ModuleConfig::cpanelWhmUsername() !== null && ModuleConfig::cpanelWhmToken() !== null;
        if (!$credentialsReady) {
            return $this->withTiming(['name' => 'cpanel_connectivity', 'status' => 'WARN', 'message' => 'credentials_missing', 'request_id' => $requestId], $started);
        }
        $user = ModuleConfig::cpanelWhmUsername();
        $token = ModuleConfig::cpanelWhmToken();
        try {
            $resp = $this->http->get(
                $base . '/json-api/version',
                [
                    'Accept' => 'application/json',
                    'Authorization' => 'whm ' . $user . ':' . $token,
                ],
                ['api.version' => 1]
            );
            if (in_array($resp->statusCode(), [401, 403], true)) {
                return $this->withTiming(['name' => 'cpanel_connectivity', 'status' => 'WARN', 'message' => 'invalid_credentials', 'http_status' => $resp->statusCode(), 'request_id' => $requestId], $started);
            }
            return $this->withTiming([
                'name' => 'cpanel_connectivity',
                'status' => $resp->isSuccess() ? 'PASS' : 'WARN',
                'http_status' => $resp->statusCode(),
                'token_configured' => true,
                'message' => $resp->isSuccess() ? 'ok' : 'unexpected_response',
                'request_id' => $requestId,
            ], $started);
        } catch (\Throwable $e) {
            return $this->withTiming(['name' => 'cpanel_connectivity', 'status' => 'WARN', 'message' => 'unreachable', 'request_id' => $requestId], $started);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function cloudflareCheck(SecretManager $secret): array
    {
        $started = microtime(true);
        $requestId = bin2hex(random_bytes(8));
        if ($this->providerDisabled('dns') || $this->providerFlagsDisableExecution('cloudflare_dns')) {
            return $this->withTiming(['name' => 'cloudflare_connectivity', 'status' => 'PASS', 'message' => 'disabled_provider', 'request_id' => $requestId], $started);
        }
        $token = $secret->getSecret('RATEB_INFRA_CLOUDFLARE', 'API_TOKEN') ?? getenv('RATEB_INFRA_CLOUDFLARE_API_TOKEN');
        if (!is_string($token) || trim($token) === '') {
            return $this->withTiming(['name' => 'cloudflare_connectivity', 'status' => 'WARN', 'message' => 'credentials_missing', 'request_id' => $requestId], $started);
        }
        try {
            $resp = $this->http->get('https://api.cloudflare.com/client/v4/user/tokens/verify', [
                'Authorization' => 'Bearer ' . trim($token),
                'Accept' => 'application/json',
            ]);
            if (in_array($resp->statusCode(), [401, 403], true)) {
                return [
                    'name' => 'cloudflare_connectivity',
                    'status' => 'WARN',
                    'message' => 'invalid_credentials',
                    'http_status' => $resp->statusCode(),
                    'token_present' => true,
                    'request_id' => $requestId,
                    'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                ];
            }
            return $this->withTiming([
                'name' => 'cloudflare_connectivity',
                'status' => $resp->isSuccess() ? 'PASS' : 'WARN',
                'http_status' => $resp->statusCode(),
                'token_present' => true,
                'message' => $resp->isSuccess() ? 'ok' : 'invalid_credentials',
                'request_id' => $requestId,
            ], $started);
        } catch (\Throwable $e) {
            return $this->withTiming(['name' => 'cloudflare_connectivity', 'status' => 'WARN', 'message' => 'unreachable', 'request_id' => $requestId], $started);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function namecheapCheck(SecretManager $secret): array
    {
        $started = microtime(true);
        $requestId = bin2hex(random_bytes(8));
        if ($this->providerDisabled('registrar') || $this->providerFlagsDisableExecution('namecheap')) {
            return $this->withTiming(['name' => 'namecheap_reachability', 'status' => 'PASS', 'message' => 'disabled_provider', 'request_id' => $requestId], $started);
        }
        $apiUser = ModuleConfig::namecheapCredential('api_user');
        $apiKey = ModuleConfig::namecheapCredential('api_key');
        $user = ModuleConfig::namecheapCredential('username');
        $clientIp = ModuleConfig::namecheapCredential('client_ip');
        $ready = is_string($apiUser) && $apiUser !== '' && is_string($apiKey) && $apiKey !== '' && is_string($user) && $user !== '' && is_string($clientIp) && $clientIp !== '';
        if (!$ready) {
            return $this->withTiming(['name' => 'namecheap_reachability', 'status' => 'WARN', 'message' => 'credentials_missing', 'request_id' => $requestId], $started);
        }
        $base = ModuleConfig::providerLiveEnabled('namecheap')
            ? 'https://api.namecheap.com/xml.response'
            : 'https://api.sandbox.namecheap.com/xml.response';
        try {
            $resp = $this->http->get($base, ['Accept' => 'application/xml'], [
                'ApiUser' => $apiUser,
                'ApiKey' => $apiKey,
                'UserName' => $user,
                'ClientIp' => $clientIp,
                'Command' => 'namecheap.domains.check',
                'DomainList' => 'example.com',
            ]);
            if (in_array($resp->statusCode(), [401, 403], true)) {
                return $this->withTiming(['name' => 'namecheap_reachability', 'status' => 'WARN', 'message' => 'invalid_credentials', 'http_status' => $resp->statusCode(), 'request_id' => $requestId], $started);
            }
            if (stripos($resp->body(), 'Authentication error') !== false || stripos($resp->body(), '<Errors>') !== false) {
                return $this->withTiming(['name' => 'namecheap_reachability', 'status' => 'WARN', 'message' => 'invalid_credentials', 'http_status' => $resp->statusCode(), 'request_id' => $requestId], $started);
            }
            return $this->withTiming([
                'name' => 'namecheap_reachability',
                'status' => $resp->statusCode() >= 200 && $resp->statusCode() < 300 ? 'PASS' : 'WARN',
                'http_status' => $resp->statusCode(),
                'message' => $resp->statusCode() >= 200 && $resp->statusCode() < 300 ? 'ok' : 'unexpected_response',
                'request_id' => $requestId,
            ], $started);
        } catch (\Throwable $e) {
            return $this->withTiming(['name' => 'namecheap_reachability', 'status' => 'WARN', 'message' => 'unreachable', 'request_id' => $requestId], $started);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function acmeCheck(): array
    {
        $started = microtime(true);
        $requestId = bin2hex(random_bytes(8));
        if ($this->providerDisabled('ssl') || $this->providerFlagsDisableExecution('letsencrypt_ssl')) {
            return $this->withTiming(['name' => 'acme_reachability', 'status' => 'WARN', 'message' => 'disabled_provider', 'request_id' => $requestId], $started);
        }
        $dir = ModuleConfig::providerLiveEnabled('letsencrypt_ssl')
            ? 'https://acme-v02.api.letsencrypt.org/directory'
            : 'https://acme-staging-v02.api.letsencrypt.org/directory';
        try {
            $resp = $this->http->get($dir, ['Accept' => 'application/json']);
            return $this->withTiming([
                'name' => 'acme_reachability',
                'status' => $resp->isSuccess() ? 'PASS' : 'WARN',
                'http_status' => $resp->statusCode(),
                'message' => $resp->isSuccess() ? 'ok' : 'unexpected_response',
                'request_id' => $requestId,
            ], $started);
        } catch (\Throwable $e) {
            return $this->withTiming(['name' => 'acme_reachability', 'status' => 'WARN', 'message' => 'unreachable', 'request_id' => $requestId], $started);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function dnsPropagationSupportCheck(): array
    {
        return [
            'name' => 'dns_propagation_test_support',
            'status' => function_exists('dns_get_record') ? 'PASS' : 'WARN',
            'message' => function_exists('dns_get_record') ? 'supported' : 'dns_get_record_unavailable',
            'duration_ms' => 0,
            'request_id' => bin2hex(random_bytes(8)),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function withTiming(array $payload, float $startedAt): array
    {
        $payload['duration_ms'] = (int) round((microtime(true) - $startedAt) * 1000);

        return $payload;
    }

    private function providerDisabled(string $providerType): bool
    {
        if ($this->activations === null) {
            return false;
        }

        return $this->activations->activeForScope($providerType, null, null) === [];
    }

    private function providerFlagsDisableExecution(string $providerKey): bool
    {
        return !ModuleConfig::providerLiveEnabled($providerKey) && !ModuleConfig::providerSandboxEnabled($providerKey);
    }
}

