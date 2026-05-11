<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\SSL\Adapters;

use Ratib\InfrastructureMarketplace\Domain\Contracts\SslProviderInterface;
use Ratib\InfrastructureMarketplace\Domain\TenantContext;
use Ratib\InfrastructureMarketplace\Http\Clients\CurlHttpClient;
use Ratib\InfrastructureMarketplace\Http\Contracts\HttpClientInterface;
use Ratib\InfrastructureMarketplace\Security\Rollout\ProviderRolloutPolicy;

final class LetsEncryptSslAdapter implements SslProviderInterface
{
    private HttpClientInterface $http;
    private ProviderRolloutPolicy $rollout;

    public function __construct(
        ?HttpClientInterface $http = null,
        ?ProviderRolloutPolicy $rollout = null
    ) {
        $this->http = $http ?? new CurlHttpClient();
        $this->rollout = $rollout ?? new ProviderRolloutPolicy();
    }

    public function provisionCertificate(TenantContext $tenant, string $fqdn, array $options = []): array
    {
        if (!$this->rollout->canExecute($tenant, 'letsencrypt_ssl')) {
            return ['provider' => 'letsencrypt_ssl', 'state' => 'disabled_by_rollout'];
        }
        $directory = $this->directoryUrl();
        $resp = $this->http->get($directory, ['Accept' => 'application/json']);
        if (!$resp->isSuccess()) {
            return [
                'provider' => 'letsencrypt_ssl',
                'state' => 'acme_unreachable',
                'retryable' => true,
                'error_class' => 'provider_unavailable',
            ];
        }
        return [
            'provider' => 'letsencrypt_ssl',
            'state' => 'validation_prepared',
            'mode' => $this->rollout->executionMode('letsencrypt_ssl'),
            'fqdn' => strtolower($fqdn),
            'challenge_strategy' => (string) ($options['challenge'] ?? 'dns-01'),
            'acme_directory' => $directory,
            'async' => true,
        ];
    }

    public function revokeCertificate(TenantContext $tenant, string $externalReference): array
    {
        if (!$this->rollout->canExecute($tenant, 'letsencrypt_ssl')) {
            return ['provider' => 'letsencrypt_ssl', 'state' => 'disabled_by_rollout'];
        }
        return [
            'provider' => 'letsencrypt_ssl',
            'state' => 'revoke_prepared',
            'reference' => $externalReference,
            'async' => true,
        ];
    }

    public function getCapabilityMatrix(): array
    {
        return [
            'provider' => 'letsencrypt_ssl',
            'ready' => true,
            'supports' => [
                'dns_01' => true,
                'http_01' => true,
                'renewal_tracking' => true,
                'reconciliation' => true,
            ],
        ];
    }

    private function directoryUrl(): string
    {
        $custom = getenv('RATIB_INFRA_LE_ACME_DIRECTORY');
        if (is_string($custom) && trim($custom) !== '') {
            return trim($custom);
        }
        if ($this->rollout->executionMode('letsencrypt_ssl') === 'live') {
            return 'https://acme-v02.api.letsencrypt.org/directory';
        }
        return 'https://acme-staging-v02.api.letsencrypt.org/directory';
    }
}

