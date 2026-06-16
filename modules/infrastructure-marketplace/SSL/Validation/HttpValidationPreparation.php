<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\SSL\Validation;

final class HttpValidationPreparation
{
    /**
     * @return array<string, string>
     */
    public function challengeFile(string $token, string $value): array
    {
        return [
            'path' => '/.well-known/acme-challenge/' . $token,
            'content' => $value,
        ];
    }
}

