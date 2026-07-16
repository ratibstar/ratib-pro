# Phase 10 — Identity Module Charter

**Status:** Binding  
**Authority:** Online ERP is Authentication Authority  
**Module:** `identity` BusinessModule (`RatebOfflineV2Identity`)

## Permanent rule

Identity Module is **never** Source of Truth for user credentials.

## May store

Sealed identity · claims · RBAC snapshot · device trust · unlock metadata · local config · derived local session · security metadata

## Must never store

Passwords · password hashes · cookies · PHP sessions · bearer/API/JWT/refresh/OAuth tokens · TOTP/recovery/reset/verification secrets · any server-authenticating credential

## Scope

Local unlock · device trust · claims · RBAC snapshot · online enroll bridge (session cookies only) · diagnostics/health/settings/services/events/contributions

## Forbidden

Platform layer edits · Offline V1 reuse · LoginController/SessionManager · credential sync · server authentication from the module
