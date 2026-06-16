<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\SSL\Lifecycle;

final class CertificateState
{
    public const REQUESTED = 'REQUESTED';
    public const DNS_VALIDATION_PENDING = 'DNS_VALIDATION_PENDING';
    public const HTTP_VALIDATION_PENDING = 'HTTP_VALIDATION_PENDING';
    public const WAITING_ISSUER = 'WAITING_ISSUER';
    public const ACTIVE = 'ACTIVE';
    public const RENEWAL_DUE = 'RENEWAL_DUE';
    public const EXPIRED = 'EXPIRED';
    public const FAILED = 'FAILED';
    public const RECONCILING = 'RECONCILING';
}

