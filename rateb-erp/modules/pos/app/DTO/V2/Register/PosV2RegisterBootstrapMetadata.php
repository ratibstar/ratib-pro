<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Register;

/** Request-scoped bootstrap metadata. */
final readonly class PosV2RegisterBootstrapMetadata
{
    public function __construct(
        public string $version,
        public string $channel,
        public string $httpMethod,
        public string $requestPath,
    ) {
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'channel' => $this->channel,
            'http_method' => $this->httpMethod,
            'request_path' => $this->requestPath,
        ];
    }
}
