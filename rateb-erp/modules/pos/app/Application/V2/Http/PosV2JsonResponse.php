<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Application\V2\Http;

use Rateb\App\Core\Response;

/** Immutable JSON response DTO for V2 API controllers. */
final readonly class PosV2JsonResponse
{
    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     */
    public function __construct(
        public int $statusCode,
        public array $body,
        public array $headers = [],
    ) {
    }

    public function send(): void
    {
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        Response::json($this->body, $this->statusCode);
    }
}
