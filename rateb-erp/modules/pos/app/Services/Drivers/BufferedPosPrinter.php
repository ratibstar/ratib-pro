<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services\Drivers;

use Rateb\App\Pos\Contracts\PosPrinterInterface;

/**
 * Browser-oriented receipt buffer printer.
 * Persists last print payload for client pickup/retry — does not claim ESC/POS hardware.
 * Selected only when RATEB_POS_PRINTER=buffer (default remains NullPosPrinter).
 */
final class BufferedPosPrinter implements PosPrinterInterface
{
    public function printReceipt(array $payload): bool
    {
        $root = defined('RATEB_ROOT') ? RATEB_ROOT : dirname(__DIR__, 5);
        $dir = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'pos';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $path = $dir . DIRECTORY_SEPARATOR . 'last-receipt-buffer.json';
        $row = [
            'queued_at' => gmdate('c'),
            'device_id' => $this->deviceId(),
            'payload' => $payload,
        ];
        $json = json_encode($row, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return false;
        }
        $tmp = $path . '.tmp';
        if (@file_put_contents($tmp, $json) === false) {
            return false;
        }

        return @rename($tmp, $path) || (@file_put_contents($path, $json) !== false);
    }

    public function deviceId(): string
    {
        return 'buffer-printer';
    }
}
