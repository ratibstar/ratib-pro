<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Context;

/**
 * Full V2 request envelope passed into controllers (immutable).
 */
final readonly class PosV2RequestContext
{
    public function __construct(
        public string $httpMethod,
        public string $requestPath,
        public string $channel,
        public PosV2RegisterContext $register,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'http_method' => $this->httpMethod,
            'request_path' => $this->requestPath,
            'channel' => $this->channel,
            'register' => $this->register->toArray(),
        ];
    }
}
