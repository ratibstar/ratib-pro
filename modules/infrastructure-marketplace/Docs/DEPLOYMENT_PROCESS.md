Infrastructure Marketplace Deployment Process (Phase 5.2)

1) Apply migrations through `006_release_safety.sql`.
2) Run CLI verifier:
   - `php modules/infrastructure-marketplace/Cli/InfrastructureLaunchVerifier.php --strict --json`
3) Ensure gate pass before rollout.
4) Record deployment audit automatically via verifier.
5) Start/verify worker supervision.
6) Roll out by tenant allowlist only.

CI integration examples:
- `php modules/infrastructure-marketplace/Release/Deployment/validate-migrations.php`
- `php modules/infrastructure-marketplace/Release/Deployment/validate-queue-workers.php`
- `php modules/infrastructure-marketplace/Release/Deployment/validate-providers.php`
- `php modules/infrastructure-marketplace/Release/Deployment/validate-release.php --strict`

