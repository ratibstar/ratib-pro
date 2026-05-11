Provider Outage Recovery Flow

1) Detect outage:
- `providers.php` health snapshot shows `unavailable`
- alerts emitted via `InfrastructureAlertingService`

2) Contain:
- emergency-disable affected provider type
- keep tenant allowlist narrow

3) Failover:
- adjust provider activation priority weights
- validate capability discovery (`providers.php`)

4) Recovery verification:
- run `prelaunch-health.php`
- run domain search/connectivity diagnostics for impacted provider

5) Restore:
- re-enable provider in sandbox mode first
- re-enable live mode for pilot tenant allowlist

