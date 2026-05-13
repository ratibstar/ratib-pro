<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Diagnostics;

use Ratib\InfrastructureMarketplace\Config\ModuleConfig;
use Ratib\InfrastructureMarketplace\Http\Clients\CurlHttpClient;
use Ratib\InfrastructureMarketplace\Providers\Activation\ProviderActivationRegistry;
use Ratib\InfrastructureMarketplace\Security\Secrets\SecretManager;

final class ProviderDiagnosticsService
{
    private CurlHttpClient $http;
    private ?ProviderActivationRegistry $activations;

    public function __construct(?\PDO $pdo = null)
    {
        $this->http = new CurlHttpClient(10);
        $this->activations = $pdo !== null ? new ProviderActivationRegistry($pdo) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function verify(): array
    {
        $secret = SecretManager::withEnvProvider();
        $checks = [];

        $checks[] = $this->cpanelCheck();
        $checks[] = $this->cloudflareCheck($secret);
        $checks[] = $this->namecheapCheck($secret);
        $checks[] = $this->acmeCheck();
        $checks[] = $this->dnsPropagationSupportCheck();

        return ['checks' => $checks];
    }

    /**
     * @return array<string, mixed>
     */
    private function cpanelCheck(): array
    {
        if ($this->providerDisabled('hosting')) {
            return ['name' => 'cpanel_connectivity', 'status' => 'WARN', 'message' => 'disabled_provider'];
        }
        $base = ModuleConfig::cpanelWhmBaseUrl();
        if ($base === null) {
            return ['name' => 'cpanel_connectivity', 'status' => 'WARN', 'message' => 'missing_config'];
        }
        $credentialsReady = ModuleConfig::cpanelWhmUsername() !== null && ModuleConfig::cpanelWhmToken() !== null;
        if (!$credentialsReady) {
            return ['name' => 'cpanel_connectivity', 'status' => 'WARN', 'message' => 'credentials_missing'];
        }
        try {
            $resp = $this->http->get($base . '/json-api/version', ['Accept' => 'application/json'], ['api.version' => 1]);
            if (in_array($resp->statusCode(), [401, 403], true)) {
                return ['name' => 'cpanel_connectivity', 'status' => 'WARN', 'message' => 'invalid_credentials', 'http_status' => $resp->statusCode()];
            }
            return [
                'name' => 'cpanel_connectivity',
                'status' => $resp->isSuccess() ? 'PASS' : 'WARN',
                'http_status' => $resp->statusCode(),
                'token_configured' => true,
                'message' => $resp->isSuccess() ? 'ok' : 'unexpected_response',
            ];
        } catch (\Throwable $e) {
            return ['name' => 'cpanel_connectivity', 'status' => 'WARN', 'message' => 'unreachable'];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function cloudflareCheck(SecretManager $secret): array
    {
        if ($this->providerDisabled('dns') || $this->providerFlagsDisableExecution('cloudflare_dns')) {
            return ['name' => 'cloudflare_connectivity', 'status' => 'WARN', 'message' => 'disabled_provider'];
        }
        $token = $secret->getSecret('RATIB_INFRA_CLOUDFLARE', 'API_TOKEN') ?? getenv('RATIB_INFRA_CLOUDFLARE_API_TOKEN');
        if (!is_string($token) || trim($token) === '') {
            return ['name' => 'cloudflare_connectivity', 'status' => 'WARN', 'message' => 'credentials_missing'];
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
                ];
            }
            return [
                'name' => 'cloudflare_connectivity',
                'status' => $resp->isSuccess() ? 'PASS' : 'WARN',
                'http_status' => $resp->statusCode(),
                'token_present' => true,
                'message' => $resp->isSuccess() ? 'ok' : 'invalid_credentials',
            ];
        } catch (\Throwable $e) {
            return ['name' => 'cloudflare_connectivity', 'status' => 'WARN', 'message' => 'unreachable'];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function namecheapCheck(SecretManager $secret): array
    {
        if ($this->providerDisabled('registrar') || $this->providerFlagsDisableExecution('namecheap')) {
            return ['name' => 'namecheap_reachability', 'status' => 'WARN', 'message' => 'disabled_provider'];
        }
        $apiUser = ModuleConfig::namecheapSecretFromRuntime('api_user')
            ?: ($secret->getSecret('RATIB_INFRA_NAMECHEAP', 'API_USER') ?? getenv('RATIB_INFRA_NAMECHEAP_API_USER'));
        $apiKey = ModuleConfig::namecheapSecretFromRuntime('api_key')
            ?: ($secret->getSecret('RATIB_INFRA_NAMECHEAP', 'API_KEY') ?? getenv('RATIB_INFRA_NAMECHEAP_API_KEY'));
        $user = ModuleConfig::namecheapSecretFromRuntime('username')
            ?: ($secret->getSecret('RATIB_INFRA_NAMECHEAP', 'USERNAME') ?? getenv('RATIB_INFRA_NAMECHEAP_USERNAME'));
        $clientIp = ModuleConfig::namecheapSecretFromRuntime('client_ip') ?: getenv('RATIB_INFRA_NAMECHEAP_CLIENT_IP');
        $ready = is_string($apiUser) && $apiUser !== '' && is_string($apiKey) && $apiKey !== '' && is_string($user) && $user !== '' && is_string($clientIp) && $clientIp !== '';
        if (!$ready) {
            return ['name' => 'namecheap_reachability', 'status' => 'WARN', 'message' => 'credentials_missing'];
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
                return ['name' => 'namecheap_reachability', 'status' => 'WARN', 'message' => 'invalid_credentials', 'http_status' => $resp->statusCode()];
            }
            if (stripos($resp->body(), 'Authentication error') !== false || stripos($resp->body(), '<Errors>') !== false) {
                return ['name' => 'namecheap_reachability', 'status' => 'WARN', 'message' => 'invalid_credentials', 'http_status' => $resp->statusCode()];
            }
            return [
                'name' => 'namecheap_reachability',
                'status' => $resp->statusCode() >= 200 && $resp->statusCode() < 300 ? 'PASS' : 'WARN',
                'http_status' => $resp->statusCode(),
                'message' => $resp->statusCode() >= 200 && $resp->statusCode() < 300 ? 'ok' : 'unexpected_response',
            ];
        } catch (\Throwable $e) {
            return ['name' => 'namecheap_reachability', 'status' => 'WARN', 'message' => 'unreachable'];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function acmeCheck(): array
    {
        if ($this->providerDisabled('ssl') || $this->providerFlagsDisableExecution('letsencrypt_ssl')) {
            return ['name' => 'acme_reachability', 'status' => 'WARN', 'message' => 'disabled_provider'];
        }
        $dir = ModuleConfig::providerLiveEnabled('letsencrypt_ssl')
            ? 'https://acme-v02.api.letsencrypt.org/directory'
            : 'https://acme-staging-v02.api.letsencrypt.org/directory';
        try {
            $resp = $this->http->get($dir, ['Accept' => 'application/json']);
            return [
                'name' => 'acme_reachability',
                'status' => $resp->isSuccess() ? 'PASS' : 'WARN',
                'http_status' => $resp->statusCode(),
                'message' => $resp->isSuccess() ? 'ok' : 'unexpected_response',
            ];
        } catch (\Throwable $e) {
            return ['name' => 'acme_reachability', 'status' => 'WARN', 'message' => 'unreachable'];
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
        ];
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

