Infrastructure Provisioning Worker Runtime

Entry point:
- `modules/infrastructure-marketplace/Workers/InfrastructureProvisioningWorker.php`

Environment:
- `RATIB_INFRA_MARKETPLACE_ENABLED=1`
- `RATIB_INFRA_QUEUE_DRIVER=database`
- `RATIB_INFRA_DB_DSN`, `RATIB_INFRA_DB_USER`, `RATIB_INFRA_DB_PASS` (or legacy DB_* constants)
- `RATIB_INFRA_WORKER_NAME`
- `RATIB_INFRA_LOCK_TTL_SECONDS`
- `RATIB_INFRA_WORKER_MEMORY_MAX`

systemd example:
- `ExecStart=/usr/bin/php /path/modules/infrastructure-marketplace/Workers/InfrastructureProvisioningWorker.php`
- `Restart=always`

supervisor example:
- `command=php /path/modules/infrastructure-marketplace/Workers/InfrastructureProvisioningWorker.php`
- `autorestart=true`

docker runtime example:
- worker container command: `php modules/infrastructure-marketplace/Workers/InfrastructureProvisioningWorker.php`
- healthcheck can read `ratib_infra_worker_heartbeats`.

