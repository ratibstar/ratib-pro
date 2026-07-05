<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Bootstrap;

use Rateb\App\Pos\DTO\V2\Context\PosV2RegisterContext;
use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;

/** Bootstrap API payload — register context only (T05). */
final readonly class PosV2BootstrapResponseData
{
    public function __construct(
        public PosV2RegisterContext $register,
    ) {
    }

    public static function fromRequestContext(PosV2RequestContext $context): self
    {
        return new self($context->register);
    }

    /** @return array{register: array<string, mixed>} */
    public function toArray(): array
    {
        return [
            'register' => $this->register->toArray(),
        ];
    }
}
