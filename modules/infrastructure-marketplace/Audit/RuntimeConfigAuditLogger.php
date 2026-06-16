<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Audit;

final class RuntimeConfigAuditLogger
{
    private string $file;

    public function __construct(?string $file = null)
    {
        if ($file !== null) {
            $this->file = $file;
            return;
        }
        $fromEnv = getenv('RATEB_INFRA_RUNTIME_AUDIT_PATH');
        if (is_string($fromEnv) && trim($fromEnv) !== '') {
            $this->file = trim($fromEnv);
            return;
        }
        $root = dirname(__DIR__, 3);
        $rp = realpath($root);
        $base = $rp !== false ? $rp : $root;
        $this->file = $base . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'infrastructure-marketplace'
            . DIRECTORY_SEPARATOR . 'runtime-config-audit.jsonl';
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

