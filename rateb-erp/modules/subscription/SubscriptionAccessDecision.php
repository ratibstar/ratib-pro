<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

/**
 * Immutable allow/deny result for subscription enforcement (Phase 7B).
 */
final readonly class SubscriptionAccessDecision
{
    public const ALLOW = 'ALLOW';
    public const DENY = 'DENY';

    public function __construct(
        private string $decision,
        private string $reason,
        private int $companyId,
        private string $requestUri,
        private ?string $redirectPath = null,
    ) {
    }

    public static function allow(int $companyId, string $requestUri, string $reason): self
    {
        return new self(self::ALLOW, $reason, $companyId, $requestUri, null);
    }

    public static function deny(
        int $companyId,
        string $requestUri,
        string $reason,
        string $redirectPath
    ): self {
        return new self(self::DENY, $reason, $companyId, $requestUri, $redirectPath);
    }

    public function allowed(): bool
    {
        return $this->decision === self::ALLOW;
    }

    public function denied(): bool
    {
        return $this->decision === self::DENY;
    }

    public function decision(): string
    {
        return $this->decision;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function companyId(): int
    {
        return $this->companyId;
    }

    public function requestUri(): string
    {
        return $this->requestUri;
    }

    public function redirectPath(): ?string
    {
        return $this->redirectPath;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'decision' => $this->decision,
            'reason' => $this->reason,
            'company_id' => $this->companyId,
            'request_uri' => $this->requestUri,
            'redirect_path' => $this->redirectPath,
            'timestamp' => gmdate('c'),
        ];
    }
}
