# Phase P2 — Warm Offline Identity Hardening

Additive hardening on top of Phase P1. No Foundation / SDK / IDB redesign.

## Adds

- Device trust (fingerprint, nickname, trusted/revoked/lost/disabled, force logout)
- Identity rotation / renew before expiry
- Multi-device isolation (per-device vault + keys + JTI)
- Session TTL (unlock / idle / max offline)
- Security: anti-rollback, identity version, nonce, vault integrity, clock skew
- Audit events + Security → Offline Devices admin
- Additive device APIs under `/api/v1/offline/devices/*`

## Frozen

- SDK 14.2.0
- IndexedDB DB_VERSION = 2
- ReplayEngine / Queue / Conflict / POS isolation
