<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Bootstrap;

use Rateb\App\Pos\DTO\V2\Context\PosV2RegisterContext;
use Rateb\App\Pos\DTO\V2\Register\RegisterBootstrapResponse;

/** Bootstrap API meta envelope (version + profile). */
final readonly class PosV2BootstrapMeta
{
    public function __construct(
        public string $version,
        public string $profile,
    ) {
    }

    public static function fromRegisterContext(PosV2RegisterContext $register): self
    {
        return new self(
            version: '2',
            profile: $register->profile(),
        );
    }

    public static function fromBootstrapResponse(RegisterBootstrapResponse $response): self
    {
        return new self(
            version: '2',
            profile: $response->profile,
        );
    }

    /** @return array{version: string, profile: string} */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'profile' => $this->profile,
        ];
    }
}
