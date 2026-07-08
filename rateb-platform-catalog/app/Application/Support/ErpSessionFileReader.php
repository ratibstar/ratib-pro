<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Support;

/**
 * Reads RATEB ERP session payload (rateb_erp cookie) from ERP session files
 * without requiring catalog PHP to share session.save_path with ERP.
 */
final class ErpSessionFileReader
{
    /**
     * @param list<string>|null $pathOverride Testing / explicit ERP session directories.
     */
    public function __construct(
        private readonly ?array $pathOverride = null
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function read(): array
    {
        $sessionId = isset($_COOKIE['rateb_erp']) ? (string) $_COOKIE['rateb_erp'] : '';
        if ($sessionId === '' || !preg_match('/^[a-zA-Z0-9,-]{1,128}$/', $sessionId)) {
            return [];
        }

        foreach ($this->sessionPaths() as $dir) {
            $resolved = realpath($dir);
            if ($resolved === false && is_dir($dir)) {
                $resolved = $dir;
            }
            if ($resolved === false) {
                continue;
            }

            $file = $resolved . '/sess_' . $sessionId;
            if (!is_readable($file)) {
                continue;
            }

            $payload = file_get_contents($file);
            if (!is_string($payload) || $payload === '') {
                continue;
            }

            $decoded = $this->decodePayload($payload);
            if ($decoded !== []) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function sessionPaths(): array
    {
        return $this->pathOverride ?? CatalogSession::erpSessionSavePathCandidates();
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePayload(string $payload): array
    {
        $result = [];
        $chunks = explode(';', rtrim($payload, ';'));
        foreach ($chunks as $chunk) {
            if ($chunk === '') {
                continue;
            }

            $pipe = strpos($chunk, '|');
            if ($pipe === false) {
                continue;
            }

            $key = substr($chunk, 0, $pipe);
            if ($key === '') {
                continue;
            }

            $serialized = substr($chunk, $pipe + 1);
            if ($serialized !== '' && !str_ends_with($serialized, ';')) {
                $serialized .= ';';
            }
            $value = @unserialize($serialized, ['allowed_classes' => false]);
            if ($value === false && $serialized !== 'b:0;') {
                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }
}
