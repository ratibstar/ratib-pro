# Phase PX — Offline V2 Performance Architecture Audit

**Scope:** Audit only. No bug fixes. No redesign. No business-logic changes.  
**Architecture Freeze:** ACTIVE  
**Identity boundary:** Online ERP remains the only Authentication Authority. Identity stores sealed identity / claims / RBAC snapshot / device trust / unlock metadata only — never passwords, hashes, cookies, JWTs, TOTP, WebAuthn server credentials, API tokens, or other authentication secrets. BusinessModules use only `module.identity.*` APIs.

**Measured:** 2026-07-17 · production · commit `72e3d52f`  
**Tool:** `rateb-erp/tools/boot-bench/phase-px-architecture-audit.js`  
**Evidence:** `tools/boot-bench/reports/phase-px-architecture-audit.json`

---

## Executive answer

Offline V2 is not slower because it waits on the network. It is slower because it pays a **local platform tax** that Online ERP never pays on the client:

| Work Online does | Work Offline V2 does instead |
|---|---|
| One HTML response with usable UI | Build a client platform (HCI → Runtime → Router → Shell) |
| Server owns DB / auth / page data | Browser opens SQLite WASM + OPFS layout + package pointer |
| Progressive HTML paint = content | First paint is chrome/scaffold; content arrives after Promise chains |
| Thin page JS | Sync + SDK + Business Framework + Identity + module activation |

Network latency was removed. It was replaced by **CPU, WASM, OPFS, Promise orchestration, and incomplete product state** (for example Inventory renders `inv_identity_not_enrolled` until enrollment exists).

---

## 1. Comparative measurements (production)

Unauthenticated Online `/admin` redirects to Login. That is the fair “what the browser paints” comparison without inventing credentials.

| Scenario | FP / FCP | Usable signal | Notes |
|---|---:|---:|---|
| Online Login warm | **228 ms** | Login form present | SSR HTML already contains brand + form |
| Online Login cold | 3664 ms | Login form | Dominated by **3157 ms TTFB** |
| Offline V2 Home warm | **96 ms** FCP · Shell **206 ms** | Shell chrome + Home route | SPA platform still starts on critical path |
| Offline V2 Inventory warm | **36 ms** FCP · Route **123 ms** | Inventory route mounted | Still shows enrollment gate text |
| Offline V2 Home offline | **48 ms** FCP · Shell **85 ms** | Shell Ready | Fast once SW-cached |
| Offline V2 Inventory offline | **44 ms** FCP · Route **231 ms** | Inventory after DB+Identity | Local storage path |

**Observation:** Offline FCP can beat Online Login FCP. Perception still favors Online because Online’s first paint is a **complete product surface**, while Offline’s first paint is a **host shell** waiting on Runtime/Router/DB/Identity for meaningful ERP content.

---

## 2. Offline V2 layered startup (warm Inventory)

Marks from production warm `#/inventory`:

| Layer | Ready (ms) | On critical path for Shell? | Notes |
|---|---:|---|---|
| HCI global present | 20 | Yes (script) | No OPFS yet at this mark |
| Interactive / shell DOM rebuild | 21 | Yes | `Shell.create().mount()` rebuilds over static HTML |
| Service Worker ready | 23 | No (parallel) | Registration only |
| Runtime | 66 | **Yes** | `Runtime.start()` awaited inside `Shell.mount` |
| Router | 66 | **Yes** | `router.init` + bootstrap navigate |
| Shell Ready | 66 | Gate | After Runtime + Router |
| PM script | 75 | Background | Loaded even on Home |
| Sync / SDK / BM Framework | 76 | Background | Loaded even when unused |
| SQLite open | 121 | Blocks module route | WASM + hci-persist open |
| Identity + Inventory activate | 123 | Blocks requested route | Serial activate chain |
| Background Ready | 123 | After UI | Continues after Shell |

Home warm (no business module):

| Layer | Ready (ms) |
|---|---:|
| Interactive | 91 |
| Shell / Route Ready | 206 |
| PM | 235 |
| SQLite | 353 |
| Background Ready | 353 |

---

## 3. Startup timeline — warm Inventory (installed)

```text
0 ──────── 3 ms     document (SW cache / tiny TTFB)
3 ─────── 21 ms     CSS + HCI + Runtime + Router + Shell + boot parse
21 ms               INTERACTIVE (shell chrome rebuilt)
21 ────── 66 ms     Runtime.start (ensureLayout + package + health)
                    + Router.init (manifest + navigate)
66 ms               SHELL READY (builtin bootstrap may flash first)
66 ───── 121 ms     PM + Sync + SDK + Framework + SQLite WASM/open
121 ──── 123 ms     Identity activate → Inventory activate → ROUTE READY
123+                Sync idle / diagnostics unused
```

---

## 4. Flame graph (installed warm Inventory)

```text
Total ~123 ms to requested route

[==== Document/TTFB 0–3 ====]
        [== Critical scripts 3–21 ==]
              | Interactive 21
              [======== Runtime.start + Router.init 21–66 ========]
                                                    | Shell Ready 66
                                                    [==== Platform + SQLite 66–121 ====]
                                                                              [= ID+Inv =]
                                                                                    | 123
```

Critical (user-blocking) work is **Runtime.start + Router.init**, not network.

Background (module-blocking) work is **SQLite WASM/open + Identity/Inventory activation**.

---

## 5. CPU / JS / storage / DOM / events / promises

| Signal | Offline V2 (warm Inventory) | Online Login warm |
|---|---|---|
| Long tasks | **0** observed | **0** |
| Long-task CPU total | 0 ms | 0 ms |
| Script count | 11 | 7 |
| Resource count | 18 | 8 |
| Document transfer | ~0 (SW) | 3221 B transfer / 10225 B decoded |
| IndexedDB business data | **Not used** (by design) | N/A |
| Storage estimate usage | ~3.6 MB | N/A (login) |
| OPFS probe | ok · ~1 ms listing | N/A |
| DOM | Client rebuild of shell + outlet mount | Server HTML parse only |
| Custom events | `rateb-v2-*` readiness cascade (~12) | None |
| Promise chains | Shell.mount → Runtime.start → Router.init → background Promise.all → serial module activate | Mostly resource load |

**SQLite:** mode `hci-persist` (not OPFS VFS). Cost is WASM decode (~865 KB) + deserialize from HCI bytes + migrate.  
**IndexedDB:** Offline V2 business path does not use IDB; residual browser storage usage is OPFS/Cache/SW.

---

## 6. Unnecessary startup work (current architecture)

These are audit findings, not change requests:

1. **`Runtime.start()` on every Shell mount** runs `ensureLayout` + `loadActivePackage` + `runHealthChecks`/`verifyLayout` before Shell Ready — even for Home.
2. **HCI `ensureLayout` is serial** (18 directory creates + 5 file bootstraps) and repeats whenever Runtime starts.
3. **`verifyLayout` re-walks the same OPFS tree** after ensureLayout during health checks.
4. **PM / Sync / SDK / Business Framework load on Home** though Home has no business module.
5. **Shell rebuilds DOM** over an already-painted static skeleton (double first paint work).
6. **Router fetches `route-manifest.json`** before Shell Ready (cached, still async gate).
7. **Identity + Inventory activate serially** after DB; Inventory cannot show real data without enrollment — user sees an error-like workspace first.
8. **Toast “Shell ready”** and loading affordances compete with first meaningful content.
9. **Cold SQLite WASM** remains the largest single resource (~402 KB transfer / 865 KB decoded) whenever storage initializes.
10. **No product-level “online-like” HTML document** for the active ERP surface — perception remains SPA-platform, not page.

---

## 7. Synchronous / eagerly-awaited work that could be deferred

(Architecture recommendations only — freeze forbids implementing here.)

| Eager today | Why it blocks perception | Deferral opportunity (without breaking AF 2.1) |
|---|---|---|
| `Runtime.start` inside `Shell.mount` | Shell Ready waits on OPFS package/health | Mount shell + builtin routes first; start Runtime when first service needs it |
| `ensureLayout` full walk | Serial OPFS | Cache “layout ensured” session flag; skip verify on warm path |
| `loadActivePackage` + SHA | Disk + hash before UI | Lazy until package-dependent API called |
| `runHealthChecks` / `verifyLayout` | Extra OPFS reads | Idle / diagnostics only |
| PM script on Home | Parse cost | Load on first stage/activate |
| Sync engine on Home | Parse cost | Load on first enqueue/pull |
| SDK + Business Framework on Home | Parse cost | Load when first module route requested |
| SQLite open before Identity | WASM cost | Open when first `db.*` or module activate needs it (already partly background; still gates module route) |
| Identity activate before Inventory UI | Enrollment gate | Show shell + skeleton workspace; activate Identity on unlock/enroll action only |
| Router manifest network/cache read | Async gate | Inline minimal builtin routes; fetch extensions later |

AF 2.1 constraint: Identity may still never become an authentication authority or store credentials — deferral must keep Online ERP as sole auth authority and keep Identity as local sealed cache only.

---

## 8. Why Online feels faster

1. **First paint = product.** Online Login HTML already contains brand, copy, and the login form. Offline first paint is platform chrome.
2. **No local database tax.** Online queries MySQL/PHP on the server after submit; Offline must boot SQLite WASM for local ERP modules.
3. **No OPFS platform bootstrap.** Online never creates 18 directories and runtime package pointers in the browser.
4. **Fewer client frameworks.** Online uses thin page JS; Offline must instantiate Runtime, Router, Shell, SDK, Business Framework.
5. **Content completeness vs scaffold.** Even when Offline Shell Ready is numerically fast, the workspace often shows host/status text or `inv_identity_not_enrolled`, which feels unfinished compared with Online’s finished page.
6. **Expectation mismatch.** Users compare Offline to Online ERP pages. Offline V2 Host is still a platform host with sparse builtin routes, not a full SSR admin surface.

Numerically, Offline offline-reload Shell Ready (**85 ms**) can beat Online warm Login FCP (**228 ms**). Perception is about **meaning density at first paint**, not only milliseconds.

---

## 9. TOP 20 startup bottlenecks

Ranked by impact on perceived Offline vs Online speed (installed / module path first).

| Rank | Bottleneck | Layer | Est. cost (warm/cold) | Type |
|---:|---|---|---|---|
| 1 | Runtime.start on Shell.mount critical path | Runtime | ~45–115 ms warm | Promise chain |
| 2 | HCI ensureLayout serial OPFS | HCI | inside Runtime.start | OPFS |
| 3 | Health verifyLayout duplicate OPFS walk | Runtime/HCI | inside Runtime.start | OPFS |
| 4 | loadActivePackage + SHA of runtime.pkg | Runtime/PM | inside Runtime.start | Disk/CPU |
| 5 | Router manifest fetch + init before Shell Ready | Router | ~tens of ms | Fetch/JSON |
| 6 | SQLite WASM download/parse | SQLite | ~250–315 ms cold resource | CPU/Network |
| 7 | SQLite open + hci-persist deserialize/migrate | SQLite | ~40–150 ms installed | CPU/Disk |
| 8 | Identity → Inventory serial activate | Identity/BM | ~10–20 ms after DB | Promise chain |
| 9 | Critical script parse (hci/runtime/router/shell/boot) | Host | ~20–90 ms | JS execution |
| 10 | Eager Sync/SDK/Framework load on Home | Sync/SDK/BM | ~10–30 ms parse | JS execution |
| 11 | Eager PM load when not staging | PM | ~10–20 ms | JS execution |
| 12 | Shell DOM rebuild over static skeleton | Shell | ~1–5 ms + layout | DOM |
| 13 | Missing enrollment → empty/error workspace | Identity/Inventory | perception | Product state |
| 14 | Cold origin TTFB on first visit | Network | ~0.3–3 s | Network |
| 15 | First-visit SW install/precache contention | SW | hundreds ms cold | IO |
| 16 | Background module script fetch for deep links | BM | after Shell | Network/Cache |
| 17 | Event readiness cascade / toast noise | Shell/Events | small | Event dispatch |
| 18 | Loading indicator after content already visible | Shell | perception | DOM |
| 19 | Duplicate readiness marks/events | Boot | small | Events |
| 20 | Sparse builtin routes vs Online page density | Product/Host | perception | Architecture |

---

## 10. Before vs theoretical optimum

| Metric (installed) | Current (measured) | Theoretical optimum (audit) | Gap |
|---|---:|---:|---|
| First paint | 36–96 ms | 20–40 ms | Mostly done |
| Interactive chrome | 21–91 ms | 20–40 ms | Small |
| Shell Ready | 66–206 ms | **30–60 ms** | Runtime/health still on path |
| Builtin route | 66–206 ms | **30–60 ms** | Tied to Shell Ready |
| Inventory route | 123–231 ms | **80–150 ms** | DB+Identity still serial |
| Home background complete | 353 ms | **defer forever if unused** | PM/Sync/DB eager |
| Meaningful ERP content | often enrollment-gated | show cached claims UI or clear unlock CTA | Product readiness |

---

## 11. Prioritized optimization roadmap (recommendations only)

### P0 — Perception (host orchestration only; freeze-safe)

1. Split **chrome-ready** from **runtime-ready**: paint/nav shell without awaiting package/health.
2. Inline builtin routes; fetch extended manifest later.
3. Do not load PM/Sync/SDK/Framework until a module or sync API is requested.
4. Keep diagnostics behind flag (already largely true).

### P1 — Storage path (SQLite/HCI contracts unchanged)

5. Open SQLite only when a module/service needs DB (Home should never pay WASM).
6. Memoize ensureLayout success for the session; never verifyLayout on warm interactive path.
7. Prefer already-open DB singleton (opening latch exists; ensure all callers use it).

### P2 — Module readiness (AF 2.1 preserved)

8. For Inventory/Sales/etc., render shell workspace immediately; activate Identity via published APIs only when unlock/enroll is required.
9. Never block first workspace paint on full module self-tests (already removed from critical path).
10. Keep Online ERP as sole Authentication Authority; Identity remains local sealed cache only.

### P3 — Product parity (future phase; out of PX)

11. Increase first-paint meaning density (real nav labels, last route snapshot) without SSR PHP.
12. Align Offline UX copy so empty/enrollment states feel intentional, not broken.

---

## 12. Architecture compliance statement

- No production Offline V2 modules redesigned in this phase.
- No Identity credential storage introduced or recommended.
- Online ERP remains Authentication Authority.
- Recommendations stay at host orchestration, deferral, and measurement — not frozen-layer redesign.
- Offline V1 untouched.

**Phase PX status:** COMPLETE (audit-only).  
**Implementation:** explicitly out of scope.
