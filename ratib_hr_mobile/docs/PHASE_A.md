# Phase A — MobileConfig + White-label runtime

**Status:** COMPLETE  
**Depends on:** Phase A0  
**Next:** Phase B (native device QA) or Phase C (Attendance) per roadmap

## Objective

Consume ERP `GET /api/mobile/config` as the single runtime source for tenant branding and feature flags in `ratib_hr_mobile`.

## Architecture

```
Login / restore
    → AuthPort (ERP token)
    → MePort (employee)
    → MobileConfigurationService.refreshAfterLogin()
         → MobileConfigPort.fetchRemote()  → GET /api/mobile/config
         → CacheStore write (replace)
         → on network fail → read cache
    → ShellNavPolicy(config, role) → tabs / more items
    → BrandThemeFactory(config.themeColorHex)
```

Widgets never call ERP. They read `AppLocator.mobileConfiguration`.

## Configuration flow

1. User authenticates (`POST /api/v1/auth/token`).
2. Employee resolved (`GET /api/v1/hr/me`).
3. Config fetched (`GET /api/mobile/config`) — `company_id` from token only.
4. On **200**: brand + features applied; cache replaced.
5. On **403**: `mobile_disabled` — session cleared; login blocked.
6. On **network/timeout**: use last cached config if present.

## Files (main)

| Area | Path |
|------|------|
| Model | `lib/core/mobile_config/mobile_app_configuration.dart` |
| Service | `lib/core/mobile_config/mobile_configuration_service.dart` |
| Port | `lib/core/contracts/mobile_config_port.dart` |
| Adapter | `lib/core/adapters/erp_mobile_config_adapter.dart` |
| Cache | `lib/core/adapters/shared_preferences_cache_store.dart` |
| Shell policy | `lib/core/shell/shell_nav_policy.dart` |
| Theme | `lib/core/theme/brand_theme_factory.dart` |
| Tests | `test/phase_a_mobile_config_test.dart` |

## Explicit non-goals (Phase A)

- Offline business actions (attendance/leave queue)
- Store signing
- Touching `rateb_mobile` / Capacitor / Tracking
- New ERP APIs
