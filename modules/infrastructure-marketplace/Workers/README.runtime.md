Infrastructure Provisioning Worker Runtime

Entry point:
- `modules/infrastructure-marketplace/Workers/InfrastructureProvisioningWorker.php`

Environment:
- `RATEB_INFRA_MARKETPLACE_ENABLED=1`
- `RATEB_INFRA_QUEUE_DRIVER=database`
- `RATEB_INFRA_DB_DSN`, `RATEB_INFRA_DB_USER`, `RATEB_INFRA_DB_PASS` (or legacy DB_* constants)
- `RATEB_INFRA_WORKER_NAME`
- `RATEB_INFRA_LOCK_TTL_SECONDS`
- `RATEB_INFRA_WORKER_MEMORY_MAX`

systemd example:
- `ExecStart=/usr/bin/php /path/modules/infrastructure-marketplace/Workers/InfrastructureProvisioningWorker.php`
- `Restart=always`

supervisor example:
- `command=php /path/modules/infrastructure-marketplace/Workers/InfrastructureProvisioningWorker.php`
- `autorestart=true`

docker runtime example:
- worker container command: `php modules/infrastructure-marketplace/Workers/InfrastructureProvisioningWorker.php`
- healthcheck can read `rateb_infra_worker_heartbeats`.

