<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Services\V2\Register\Providers;

use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;
use Rateb\App\Pos\DTO\V2\Register\PosV2ReceiptSettingsContext;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2PosSettingsPortInterface;

final class ReceiptSettingsProvider
{
    public function __construct(
        private readonly PosV2PosSettingsPortInterface $settings,
    ) {
    }

    public function provide(PosV2RequestContext $context): ?PosV2ReceiptSettingsContext
    {
        $register = $context->register;
        $merged = $this->settings->loadMerged($register->companyId, $register->branchId);

        if (!$merged->hasV2()) {
            return null;
        }

        $printing = $merged->v2['printing'] ?? null;
        if (!is_array($printing) || $printing === []) {
            return null;
        }

        return new PosV2ReceiptSettingsContext(
            autoPrintReceipt: $this->optionalBool($printing['auto_print_receipt'] ?? null),
            copies: $this->optionalInt($printing['copies'] ?? null),
            includeBarcode: $this->optionalBool($printing['include_barcode'] ?? null),
            footerText: $this->optionalString($printing['footer_text'] ?? null),
        );
    }

    private function optionalString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string !== '' ? $string : null;
    }

    private function optionalInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private function optionalBool(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        return (bool) $value;
    }
}
