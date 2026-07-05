<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Services\V2\Register\Providers;

use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;
use Rateb\App\Pos\DTO\V2\Register\PosV2ProfilesContext;
use Rateb\App\Pos\PosModule;

final class ProfilesProvider
{
    /** @var list<string> */
    private readonly array $availableProfiles;

    /**
     * @param list<string>|null $availableProfiles
     */
    public function __construct(?array $availableProfiles = null)
    {
        $this->availableProfiles = $availableProfiles ?? $this->loadProfiles();
    }

    public function provide(PosV2RequestContext $context): PosV2ProfilesContext
    {
        return new PosV2ProfilesContext(
            active: $context->register->profile(),
            available: $this->availableProfiles,
        );
    }

    /** @return list<string> */
    private function loadProfiles(): array
    {
        $path = PosModule::rootPath() . '/config/v2/feature-flags.php';
        if (!is_file($path)) {
            return [];
        }

        $config = require $path;
        $profiles = is_array($config) ? ($config['profiles'] ?? []) : [];

        return is_array($profiles) ? array_values(array_filter(array_map('strval', $profiles))) : [];
    }
}
