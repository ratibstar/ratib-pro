# Branch Certification Guide

```bash
php -d extension=pdo_sqlite -d extension=sqlite3 bin/hybrid-branch-certify.php
```

Every item must PASS (installer, runtime, SQLite, login, RBAC, modules, hybrid sync/service, diagnostics, backup, recovery, registration, health, update, audit, security).

Enterprise suite:

```bash
php -d extension=pdo_sqlite -d extension=sqlite3 bin/hybrid-phase-d-enterprise-verify.php
```

Expect: `VERDICT: ENTERPRISE_PASS`.
