Queue Execution Flow (Phase 3)

1) Enqueue:
- `DatabaseQueueDispatcher::enqueue()` stores a `QUEUED` job.

2) Worker Poll:
- Worker calls `recoverExpiredLocks()` then `lockNext()`.
- Locked job transitions to `RUNNING`.

3) Execute:
- `ProvisioningExecutionEngine::process()` validates tenant isolation and executes step orchestration.
- Metrics + structured logs are emitted.

4) Success:
- State transitions `RUNNING -> COMPLETED`.
- Job marked successful with processed timestamp.

5) Failure:
- Worker calls `fail()` with attempts/max_attempts.
- If attempts remain: state -> `RETRYING` with delayed availability.
- If exhausted: state -> `DEAD_LETTER` and `reconcile_required=1`.

6) Reconciliation:
- Operator/automation transitions dead jobs into `RECONCILING`.
- After resolution: `RECONCILING -> RUNNING|COMPLETED|FAILED|CANCELLED`.

