<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Diagnostics;

use Ratib\InfrastructureMarketplace\Config\ModuleConfig;
use Ratib\InfrastructureMarketplace\Http\Clients\CurlHttpClient;
use Ratib\InfrastructureMarketplace\Security\Secrets\SecretManager;

final class ProviderDiagnosticsService
{
    private readonly CurlHttpClient $http;

    public function __construct()
    {
        $this->http = new CurlHttpClient(10);
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
        $base = ModuleConfig::cpanelWhmBaseUrl();
        if ($base === null) {
            return ['name' => 'cpanel_connectivity', 'status' => 'WARN', 'message' => 'base_url_missing'];
        }
        try {
            $resp = $this->http->get($base . '/json-api/version', ['Accept' => 'application/json'], ['api.version' => 1]);
            return [
                'name' => 'cpanel_connectivity',
                'status' => $resp->statusCode() < 500 ? 'PASS' : 'WARN',
                'http_status' => $resp->statusCode(),
                'token_configured' => ModuleConfig::cpanelWhmToken() !== null,
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
        $token = $secret->getSecret('RATIB_INFRA_CLOUDFLARE', 'API_TOKEN') ?? getenv('RATIB_INFRA_CLOUDFLARE_API_TOKEN');
        try {
            $resp = $this->http->get('https://api.cloudflare.com/client/v4/user/tokens/verify', [
                'Authorization' => 'Bearer ' . (is_string($token) ? trim($token) : ''),
                'Accept' => 'application/json',
            ]);
            return [
                'name' => 'cloudflare_connectivity',
                'status' => ($resp->statusCode() >= 200 && $resp->statusCode() < 500) ? 'PASS' : 'WARN',
                'http_status' => $resp->statusCode(),
                'token_present' => is_string($token) && $token !== '',
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
        $apiUser = $secret->getSecret('RATIB_INFRA_NAMECHEAP', 'API_USER') ?? getenv('RATIB_INFRA_NAMECHEAP_API_USER');
        $apiKey = $secret->getSecret('RATIB_INFRA_NAMECHEAP', 'API_KEY') ?? getenv('RATIB_INFRA_NAMECHEAP_API_KEY');
        $user = $secret->getSecret('RATIB_INFRA_NAMECHEAP', 'USERNAME') ?? getenv('RATIB_INFRA_NAMECHEAP_USERNAME');
        $clientIp = getenv('RATIB_INFRA_NAMECHEAP_CLIENT_IP');
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
            return [
                'name' => 'namecheap_reachability',
                'status' => $resp->statusCode() < 500 ? 'PASS' : 'WARN',
                'http_status' => $resp->statusCode(),
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
        $dir = ModuleConfig::providerLiveEnabled('letsencrypt_ssl')
            ? 'https://acme-v02.api.letsencrypt.org/directory'
            : 'https://acme-staging-v02.api.letsencrypt.org/directory';
        try {
            $resp = $this->http->get($dir, ['Accept' => 'application/json']);
            return ['name' => 'acme_reachability', 'status' => $resp->statusCode() < 500 ? 'PASS' : 'WARN', 'http_status' => $resp->statusCode()];
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
}

