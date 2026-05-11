<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Audit;

final class RuntimeConfigAuditLogger
{
    private string $file;

    public function __construct(?string $file = null)
    {
        $this->file = $file ?? (dirname(__DIR__) . '/Audit/runtime-config-audit.jsonl');
    }

    /**
     * @param array<string, mixed> $record
     */
    public function append(array $record): void
    {
        $dir = dirname($this->file);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return;
        }

        $line = json_encode($record, JSON_UNESCAPED_SLASHES);
        if (!is_string($line)) {
            return;
        }

        $fp = @fopen($this->file, 'ab');
        if (!is_resource($fp)) {
            return;
        }

        try {
            if (!@flock($fp, LOCK_EX)) {
                return;
            }
            @fwrite($fp, $line . PHP_EOL);
        } finally {
            @flock($fp, LOCK_UN);
            @fclose($fp);
        }
    }
}

