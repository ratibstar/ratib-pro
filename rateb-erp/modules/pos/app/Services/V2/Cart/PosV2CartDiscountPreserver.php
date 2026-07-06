<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Services\V2\Cart;

use Rateb\App\Pos\Services\PosRegisterCartService;

/** Preserves V1 discount fields when normalizing session cart lines (T11). */
final class PosV2CartDiscountPreserver
{
    public function __construct(
        private readonly PosRegisterCartService $cart = new PosRegisterCartService(),
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $lines
     * @return array<int, array<string, mixed>>
     */
    public function normalizePreservingDiscounts(array $lines): array
    {
        $preserved = $this->indexDiscountFields($lines);
        $normalized = $this->cart->normalizeLines($lines);

        return $this->restoreDiscountFields($normalized, $preserved, $lines);
    }

    /**
     * @param array<int, array<string, mixed>> $lines
     * @return array<string, array{discount_amount?: float, discount_percent?: float}>
     */
    private function indexDiscountFields(array $lines): array
    {
        $indexed = [];
        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $id = (string) ($line['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $entry = [];
            if ((float) ($line['discount_amount'] ?? 0) > 0) {
                $entry['discount_amount'] = (float) $line['discount_amount'];
            }
            if ((float) ($line['discount_percent'] ?? 0) > 0) {
                $entry['discount_percent'] = (float) $line['discount_percent'];
            }
            if ($entry !== []) {
                $indexed[$id] = $entry;
            }
        }

        return $indexed;
    }

    /**
     * @param array<int, array<string, mixed>> $normalized
     * @param array<string, array{discount_amount?: float, discount_percent?: float}> $preserved
     * @param array<int, array<string, mixed>> $source
     * @return array<int, array<string, mixed>>
     */
    private function restoreDiscountFields(array $normalized, array $preserved, array $source): array
    {
        $sourceById = [];
        foreach ($source as $line) {
            if (!is_array($line)) {
                continue;
            }
            $id = (string) ($line['id'] ?? '');
            if ($id !== '') {
                $sourceById[$id] = $line;
            }
        }

        foreach ($normalized as &$line) {
            $id = (string) ($line['id'] ?? '');
            $fromSource = $sourceById[$id] ?? null;
            if (is_array($fromSource)) {
                if (isset($fromSource['discount_amount'])) {
                    $line['discount_amount'] = $fromSource['discount_amount'];
                }
                if (isset($fromSource['discount_percent'])) {
                    $line['discount_percent'] = $fromSource['discount_percent'];
                }
                continue;
            }

            $saved = $preserved[$id] ?? null;
            if (is_array($saved)) {
                if (isset($saved['discount_amount'])) {
                    $line['discount_amount'] = $saved['discount_amount'];
                }
                if (isset($saved['discount_percent'])) {
                    $line['discount_percent'] = $saved['discount_percent'];
                }
            }
        }
        unset($line);

        return $normalized;
    }
}
