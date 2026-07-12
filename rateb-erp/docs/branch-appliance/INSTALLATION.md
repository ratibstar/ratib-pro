# Installation Guide

## Requirements

- PHP 8.1+
- Extensions: `pdo`, `pdo_sqlite`, `sqlite3`, `json`, `mbstring`, `openssl`
- Writable `storage/branch/`

## Cold-start (no Internet)

From `rateb-erp/`:

```bash
php -d extension=pdo_sqlite -d extension=sqlite3 bin/hybrid-branch-appliance-install.php
```

Recreate (destroys local DB):

```bash
php -d extension=pdo_sqlite -d extension=sqlite3 bin/hybrid-branch-appliance-install.php --force
```

## What the installer does

1. Verifies PHP / extensions / SQLite / permissions  
2. Creates branch directories  
3. Generates device identity, branch UUID, encryption keys  
4. Writes `storage/branch/serve.env` (runtime + sync + service config)  
5. Creates SQLite DB, applies schema, seeds admin  
6. Verifies integrity  

Default login: `admin@branch.test` / `123456` (username `admin`).

## Start UI

```bash
php -d extension=pdo_sqlite -d extension=sqlite3 bin/hybrid-branch-serve.php
```
