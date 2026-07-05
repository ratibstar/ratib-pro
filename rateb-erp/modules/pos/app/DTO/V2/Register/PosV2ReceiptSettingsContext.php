<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Register;

/** Receipt / printing settings from stored POS configuration. */
final readonly class PosV2ReceiptSettingsContext
{
    public function __construct(
        public ?bool $autoPrintReceipt,
        public ?int $copies,
        public ?bool $includeBarcode,
        public ?string $footerText,
    ) {
    }

    /** @return array<string, bool|int|string|null> */
    public function toArray(): array
    {
        return [
            'auto_print_receipt' => $this->autoPrintReceipt,
            'copies' => $this->copies,
            'include_barcode' => $this->includeBarcode,
            'footer_text' => $this->footerText,
        ];
    }
}
