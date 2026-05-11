Emergency Disable Runbook (Infrastructure Marketplace)

Purpose:
- Immediately stop risky provider operations while preserving existing SaaS/payment flows.

Steps:
1) Set `RATIB_INFRA_EXECUTION_KILL_SWITCH=1`.
2) Call `POST /api/infrastructure-marketplace/provider-activation.php` with:
   - `action=emergency_disable`
   - `provider_type` in (`hosting`, `registrar`, `dns`, `ssl`)
3) Keep workers running briefly to flush non-destructive loops, then restart workers.
4) Verify with `GET /api/infrastructure-marketplace/prelaunch-health.php`.
5) Confirm queue does not increase (`ops-queue` depth stable/decreasing).

Rollback:
- Set kill switch back to `0`.
- Re-enable providers with scoped activation rows only (no global enable).

