Release Verification Process

Primary gate:
- `InfrastructureLaunchVerifier` (strict mode for production)

PASS criteria:
- zero FAIL checks
- no stale worker heartbeats
- queue and dead-letter thresholds below limits
- required tables/assets present

Operational verification:
- `GET /api/infrastructure-marketplace/prelaunch-health.php`
- `GET /api/infrastructure-marketplace/deployment-audit.php`
- `GET /api/infrastructure-marketplace/ops-queue.php`

Drill verification:
- `POST /api/infrastructure-marketplace/recovery-drill.php` (dry-run only)

