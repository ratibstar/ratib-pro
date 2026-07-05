<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Repositories\V2\Adapters;

use Rateb\App\Pos\PosModule;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2LocalePortInterface;

/** Locale and regional defaults for POS V2 register context. */
final class ErpLocaleAdapter implements PosV2LocalePortInterface
{
    /** @var array<string, mixed> */
    private array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? $this->loadConfig();
    }

    public function locale(): string
    {
        if (function_exists('rateb_locale')) {
            $locale = (string) rateb_locale();
            if ($locale !== '') {
                return $locale;
            }
        }

        return (string) ($this->config['locale'] ?? 'ar');
    }

    public function isRtl(): bool
    {
        if (function_exists('rateb_is_rtl')) {
            return (bool) rateb_is_rtl();
        }

        return $this->locale() === 'ar';
    }

    public function timezone(): string
    {
        $tz = date_default_timezone_get();

        return $tz !== '' ? $tz : (string) ($this->config['timezone'] ?? 'Asia/Riyadh');
    }

    public function currency(): string
    {
        return (string) ($this->config['currency'] ?? 'SAR');
    }

    /** @return array<string, mixed> */
    private function loadConfig(): array
    {
        $path = PosModule::rootPath() . '/config/v2/register-context.php';
        if (!is_file($path)) {
            return [];
        }

        $config = require $path;

        return is_array($config) ? $config : [];
    }
}
