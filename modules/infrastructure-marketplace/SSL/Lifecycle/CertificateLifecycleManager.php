<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\SSL\Lifecycle;

use Ratib\InfrastructureMarketplace\SSL\Validation\DnsValidationPreparation;
use Ratib\InfrastructureMarketplace\SSL\Validation\HttpValidationPreparation;

final class CertificateLifecycleManager
{
    private DnsValidationPreparation $dnsValidation;
    private HttpValidationPreparation $httpValidation;

    public function __construct(DnsValidationPreparation $dnsValidation, HttpValidationPreparation $httpValidation) {
        $this->dnsValidation = $dnsValidation;
        $this->httpValidation = $httpValidation;
    }


    /**
     * @return array<string, mixed>
     */
    public function prepareValidation(string $fqdn, string $token): array
    {
        return [
            'state' => CertificateState::REQUESTED,
            'fqdn' => strtolower($fqdn),
            'dns' => $this->dnsValidation->challengeRecord($fqdn, $token),
            'http' => $this->httpValidation->challengeFile($token, $token),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function renewalPlan(string $fqdn, \DateTimeInterface $expiresAt): array
    {
        return [
            'fqdn' => strtolower($fqdn),
            'expires_at' => $expiresAt->format(DATE_ATOM),
            'state' => CertificateState::RENEWAL_DUE,
            'reconcile_required' => true,
        ];
    }
}

