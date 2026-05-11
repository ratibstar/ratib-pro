Worker Deployment Guidance

- Use supervised runtime (systemd/supervisor/docker).
- Ensure unique worker names per process.
- Configure memory thresholds and max-loop recycle.
- Monitor heartbeat freshness and queue pressure continuously.

Validation:
- `validate-queue-workers.php`
- dashboard worker panel + release audit panel

Failure handling:
- alerts for worker failures and queue saturation
- stale heartbeat detection in prelaunch-health checks

