Staged Rollout Process

1) Keep `RATIB_INFRA_DRY_RUN=1` and verify queue/worker health.
2) Configure `RATIB_INFRA_TENANT_ALLOWLIST` with a small pilot set.
3) Enable provider sandbox mode first; live mode off.
4) Validate provider diagnostics + prelaunch-health.
5) Enable live mode per provider one at a time.
6) Expand allowlist gradually while tracking deployment audits.
7) Keep emergency-disable runbook ready at every stage.

