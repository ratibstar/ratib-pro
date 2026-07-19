# Phase 0 — Foundation checklist

## Delivered

- [x] New project `ratib_hr_mobile` (not `rateb_mobile`, not Capacitor)
- [x] Folder architecture per brief
- [x] Material 3 enterprise theme
- [x] AR / EN localization (AR default)
- [x] Routing skeleton (`go_router`)
- [x] 5-tab ESS navigation shell
- [x] Placeholder destinations for all MVP routes
- [x] Core stubs: network / storage / security (flags only)
- [x] Dependency declarations (unwired)
- [x] Architecture + coding standards docs

## Not delivered (by design)

- [ ] ERP API adapters
- [ ] Models / DTO mapping
- [ ] Auth against ERP
- [ ] Feature screen implementations
- [ ] Offline engine wiring
- [ ] `android/` / `ios/` platforms (`flutter create .` required)

## Exit criteria for Phase 0

**Approved** by product/architecture owner → then Phase 1 may implement **Login + thin auth adapter only**, still under Controlled GO unlock criteria (U1–U8).

## Wait

Do not implement features until Phase 0 is explicitly approved.
