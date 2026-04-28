<?php
declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;

final class ModeResolver
{
    public const GOVERNMENT_MODE = 'government';
    public const COMMERCIAL_MODE = 'commercial';

    public function __construct(private readonly array $config)
    {
    }

    public function resolve(): string
    {
        $defaultMode = strtolower((string) ($this->config['default_mode'] ?? self::COMMERCIAL_MODE));

        $mode = $defaultMode;
        if (!in_array($mode, [self::GOVERNMENT_MODE, self::COMMERCIAL_MODE], true)) {
            throw new InvalidArgumentException('Invalid system mode.');
        }

        return $mode;
    }
}
