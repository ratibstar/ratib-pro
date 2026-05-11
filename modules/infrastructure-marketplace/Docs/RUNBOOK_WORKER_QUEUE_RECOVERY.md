Worker + Queue Recovery Guidance

Worker restart flow:
1) Check stale heartbeats via `prelaunch-health.php`.
2) Restart worker supervisor/systemd units.
3) Verify heartbeat freshness and queue depth trend.

Queue rebuild guidance:
1) Inspect queue with `ops-queue.php`.
2) Retry dead-letter jobs via `ops-retry-job.php` (targeted).
3) Replay specific jobs via `ops-replay-job.php` only in controlled windows.
4) Use `recovery-drill.php` in dry-run mode for procedure validation.

Reconciliation recovery:
1) Focus jobs in `RECONCILING` + `DEAD_LETTER`.
2) Trace each job with `ops-job-trace.php`.
3) Requeue/replay with explicit admin actor audit context.

Rollback checklist:
- set kill switch ON
- stop new order intake routes if needed
- pause worker services
- preserve audit/log tables

