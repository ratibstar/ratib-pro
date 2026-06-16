<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Security;

/**
 * Simple fixed-window rate limiter (per-IP / per-user buckets) using temp files + flock.
 */
final class ControlPlaneRateLimiter
{
    public static function allow(string $bucketKey, int $maxHits, int $windowSeconds): bool
    {
        if ($maxHits <= 0) {
            return true;
        }
        $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'rateb-infra-rl';
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            return true;
        }
        $file = $dir . DIRECTORY_SEPARATOR . hash('sha256', $bucketKey) . '.json';
        $now = microtime(true);
        $window = $windowSeconds > 0 ? $windowSeconds : 60;

        $fp = @fopen($file, 'c+');
        if (!is_resource($fp)) {
            return true;
        }
        try {
            if (!@flock($fp, LOCK_EX)) {
                return true;
            }
            $raw = stream_get_contents($fp);
            /** @var list<float> $hits */
            $hits = [];
            if (is_string($raw) && trim($raw) !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $t) {
                        if (is_numeric($t)) {
                            $hits[] = (float) $t;
                        }
                    }
                }
            }
            $cutoff = $now - $window;
            $hits = array_values(array_filter($hits, static fn(float $t): bool => $t >= $cutoff));
            if (count($hits) >= $maxHits) {
                return false;
            }
            $hits[] = $now;
            rewind($fp);
            ftruncate($fp, 0);
            fwrite($fp, json_encode($hits, JSON_UNESCAPED_SLASHES));
            fflush($fp);
            return true;
        } finally {
            @flock($fp, LOCK_UN);
            @fclose($fp);
        }
    }
}
