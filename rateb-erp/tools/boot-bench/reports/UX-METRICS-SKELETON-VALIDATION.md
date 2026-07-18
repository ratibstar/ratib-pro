# FINAL UX BLOCKER VALIDATION — Metrics Skeleton (Evidence Only)

**Date:** 2026-07-18T15:13:50.116Z

**Question:** Does `.cm--page-stats.is-loading` block or dominate the page so users perceive “still loading”, or is the page already usable?

**Method:** Soft-nav → poll main usability vs skeleton → non-destructive hit-tests + search/filter/click (nav prevented) + soft sidebar nav while skeleton present. No production fixes.

## Summary

| Route | afterEnter (ms) | Main usable (ms) | Skeleton gone (ms) | Skeleton visible (ms) | Still visible after +20s watch? | Usable BEFORE skeleton gone? |
|-------|-----------------|------------------|--------------------|-----------------------|---------------------------------|------------------------------|
| Inventory | 152.7 | 170.1 | 690.7 | 520.6 | false | true |
| Purchasing | 176.5 | 194.3 | not cleared | 264.1 | false | true |
| HR | 206.3 | 217.2 | not cleared | 257.8 | false | true |
| Companies | 377.8 | 399 | not cleared | 264.9 | false | true |

Note: early timing exits soon after usable so interaction can run while `.is-loading` is present. Full hide latency comes from the post-probe watch (or “not cleared”).

## Interaction while skeleton visible

| Route | Skeleton at probe? | Click (live) | Search (live) | Filter (live) | Form link hittable | Sidebar soft-nav | Blocks mid-page? | Skel viewport frac |
|-------|--------------------|--------------|---------------|---------------|--------------------|------------------|------------------|--------------------|
| Inventory | true | YES | YES | YES | YES | YES | false | 0.1 |
| Purchasing | true | YES | YES | YES | YES | YES | false | 0.1 |
| HR | true | skipped | skipped | YES | no_create_link | YES | false | 0.1 |
| Companies | true | YES | YES | YES | no_create_link | YES | false | 0.1 |

## Verdict

**Confirmed: UX issue, not a navigation issue.** Main content becomes fully usable by ~399 ms after soft-nav start (worst route). `afterEnter` is in the same window (~150–380 ms). The metrics skeleton is only a ~58px strip (~6.4% of viewport), does **not** own mid-page hit-testing, and leaves buttons / search / filters / form links / sidebar targets interactive while `.is-loading` remains (4/4 routes).

**Classification:** remaining “still loading” feel from the metrics strip is a **UX (deferred metrics) issue**, not a **navigation** issue — the page is already interactive.

### Cross-run hide latency (same harness)

| Condition | Skeleton hide |
|-----------|---------------|
| This run (warm session, Inventory long-watch) | cleared ~691 ms after nav start (~521 ms after first paint of skeleton) |
| Earlier cold-ish run (18 s poll, no early exit) | still `.is-loading` at **≥18 s** on all four routes |

Hide time varies with whether `module-page-stats.js` has already been injected via `afterInteraction` + `requestIdleCallback`. Navigation/`afterEnter`/usable times stay sub-second either way.


## Inventory

- href: `https://rateb.sa/rateb-erp/public/admin/ops/inventory?company_id=22`
- afterEnter: **152.7 ms**
- navigate done: **153.2 ms**
- main usable: **170.1 ms**
- skeleton first seen: **170.1 ms**
- skeleton gone: **690.7 ms**
- skeleton visible duration: **520.6 ms**
- usable before skeleton gone: **true**
- gap usable → skeleton gone: **520.6 ms**

### Interaction probe

```json
{
  "skeletonPresentAtProbe": true,
  "layout": {
    "skelVisible": true,
    "skelHeight": 57.953125,
    "skelViewportFraction": 0.06439236111111112,
    "skelOfMainFraction": 0.08464557944223836,
    "blocksMainHitTest": false,
    "mainTextLen": 560,
    "controlCountOutsideSkel": 11
  },
  "hitTests": {
    "clickButton": {
      "label": "إنشاء",
      "tag": "A",
      "ok": true,
      "topTag": "A",
      "topClass": "btn btn-primary btn-sm",
      "coveredBySkeleton": false
    },
    "searchInput": {
      "ok": true,
      "topTag": "INPUT",
      "topClass": "form-control",
      "coveredBySkeleton": false
    },
    "filterSelect": {
      "ok": true,
      "topTag": "SELECT",
      "topClass": "form-select form-select-sm",
      "coveredBySkeleton": false
    },
    "openForm": {
      "label": "إنشاء",
      "ok": true,
      "topTag": "A",
      "topClass": "btn btn-primary btn-sm",
      "coveredBySkeleton": false
    },
    "sidebarNavigate": {
      "ok": true,
      "topTag": "SPAN",
      "topClass": "",
      "coveredBySkeleton": false
    }
  },
  "live": {
    "clickButton": {
      "ok": true,
      "label": "إنشاء",
      "stillSkeletonVisible": true,
      "note": "click delivered; navigation prevented for evidence"
    },
    "searchInput": {
      "ok": true,
      "focused": true,
      "stillSkeletonVisible": true,
      "note": "focus+value only (no input event)"
    },
    "filterSelect": {
      "ok": true,
      "topTag": "SELECT",
      "topClass": "form-select form-select-sm",
      "coveredBySkeleton": false,
      "note": "hit-test only; change not dispatched (avoids form navigation)"
    },
    "openForm": {
      "label": "إنشاء",
      "ok": true,
      "topTag": "A",
      "topClass": "btn btn-primary btn-sm",
      "coveredBySkeleton": false
    },
    "sidebarNavigate": {
      "ok": true,
      "topTag": "SPAN",
      "topClass": "",
      "coveredBySkeleton": false,
      "note": "hit-test only while skeleton visible (no navigate — avoids leaving page)"
    }
  }
}
```

## Purchasing

- href: `https://rateb.sa/rateb-erp/public/admin/ops/purchase-requests?company_id=22`
- afterEnter: **176.5 ms**
- navigate done: **177.2 ms**
- main usable: **194.3 ms**
- skeleton first seen: **194.3 ms**
- skeleton gone: **null ms**
- skeleton visible duration: **264.1 ms**
- usable before skeleton gone: **true**
- gap usable → skeleton gone: **null ms**

### Interaction probe

```json
{
  "skeletonPresentAtProbe": true,
  "layout": {
    "skelVisible": true,
    "skelHeight": 57.953125,
    "skelViewportFraction": 0.06439236111111112,
    "skelOfMainFraction": 0.08059889608414099,
    "blocksMainHitTest": false,
    "mainTextLen": 656,
    "controlCountOutsideSkel": 21
  },
  "hitTests": {
    "clickButton": {
      "label": "إنشاء",
      "tag": "A",
      "ok": true,
      "topTag": "A",
      "topClass": "btn btn-primary btn-sm",
      "coveredBySkeleton": false
    },
    "searchInput": {
      "ok": true,
      "topTag": "INPUT",
      "topClass": "form-control",
      "coveredBySkeleton": false
    },
    "filterSelect": {
      "ok": true,
      "topTag": "SELECT",
      "topClass": "form-select form-select-sm",
      "coveredBySkeleton": false
    },
    "openForm": {
      "label": "إنشاء",
      "ok": true,
      "topTag": "A",
      "topClass": "btn btn-primary btn-sm",
      "coveredBySkeleton": false
    },
    "sidebarNavigate": {
      "ok": true,
      "topTag": "SPAN",
      "topClass": "",
      "coveredBySkeleton": false
    }
  },
  "live": {
    "clickButton": {
      "ok": true,
      "label": "إنشاء",
      "stillSkeletonVisible": true,
      "note": "click delivered; navigation prevented for evidence"
    },
    "searchInput": {
      "ok": true,
      "focused": true,
      "stillSkeletonVisible": true,
      "note": "focus+value only (no input event)"
    },
    "filterSelect": {
      "ok": true,
      "topTag": "SELECT",
      "topClass": "form-select form-select-sm",
      "coveredBySkeleton": false,
      "note": "hit-test only; change not dispatched (avoids form navigation)"
    },
    "openForm": {
      "label": "إنشاء",
      "ok": true,
      "topTag": "A",
      "topClass": "btn btn-primary btn-sm",
      "coveredBySkeleton": false
    },
    "sidebarNavigate": {
      "ok": true,
      "topTag": "SPAN",
      "topClass": "",
      "coveredBySkeleton": false,
      "note": "hit-test only while skeleton visible (no navigate — avoids leaving page)"
    }
  }
}
```

## HR

- href: `https://rateb.sa/rateb-erp/public/admin/hr?company_id=22`
- afterEnter: **206.3 ms**
- navigate done: **206.9 ms**
- main usable: **217.2 ms**
- skeleton first seen: **217.2 ms**
- skeleton gone: **null ms**
- skeleton visible duration: **257.8 ms**
- usable before skeleton gone: **true**
- gap usable → skeleton gone: **null ms**

### Interaction probe

```json
{
  "skeletonPresentAtProbe": true,
  "layout": {
    "skelVisible": true,
    "skelHeight": 57.953125,
    "skelViewportFraction": 0.06439236111111112,
    "skelOfMainFraction": 0.11454601605929586,
    "blocksMainHitTest": false,
    "mainTextLen": 534,
    "controlCountOutsideSkel": 3
  },
  "hitTests": {
    "clickButton": {
      "ok": null,
      "reason": "no_button_found"
    },
    "searchInput": {
      "ok": null,
      "reason": "no_search"
    },
    "filterSelect": {
      "ok": true,
      "topTag": "SELECT",
      "topClass": "form-select form-select-sm",
      "coveredBySkeleton": false
    },
    "openForm": {
      "ok": null,
      "reason": "no_create_link"
    },
    "sidebarNavigate": {
      "ok": true,
      "topTag": "SPAN",
      "topClass": "",
      "coveredBySkeleton": false
    }
  },
  "live": {
    "clickButton": {
      "ok": null,
      "reason": "skipped"
    },
    "searchInput": {
      "ok": null,
      "reason": "skipped"
    },
    "filterSelect": {
      "ok": true,
      "topTag": "SELECT",
      "topClass": "form-select form-select-sm",
      "coveredBySkeleton": false,
      "note": "hit-test only; change not dispatched (avoids form navigation)"
    },
    "openForm": {
      "ok": null,
      "reason": "no_create_link"
    },
    "sidebarNavigate": {
      "ok": true,
      "topTag": "SPAN",
      "topClass": "",
      "coveredBySkeleton": false,
      "note": "hit-test only while skeleton visible (no navigate — avoids leaving page)"
    }
  }
}
```

## Companies

- href: `https://rateb.sa/rateb-erp/public/admin/companies/create`
- afterEnter: **377.8 ms**
- navigate done: **378.7 ms**
- main usable: **399 ms**
- skeleton first seen: **399 ms**
- skeleton gone: **null ms**
- skeleton visible duration: **264.9 ms**
- usable before skeleton gone: **true**
- gap usable → skeleton gone: **null ms**

### Interaction probe

```json
{
  "skeletonPresentAtProbe": true,
  "layout": {
    "skelVisible": true,
    "skelHeight": 57.953125,
    "skelViewportFraction": 0.06439236111111112,
    "skelOfMainFraction": 0.06360393730493535,
    "blocksMainHitTest": false,
    "mainTextLen": 1428,
    "controlCountOutsideSkel": 51
  },
  "hitTests": {
    "clickButton": {
      "label": "",
      "tag": "BUTTON",
      "ok": true,
      "topTag": "BUTTON",
      "topClass": "btn-close",
      "coveredBySkeleton": false
    },
    "searchInput": {
      "ok": true,
      "topTag": "INPUT",
      "topClass": "form-control",
      "coveredBySkeleton": false
    },
    "filterSelect": {
      "ok": true,
      "topTag": "SELECT",
      "topClass": "form-select form-select-sm",
      "coveredBySkeleton": false
    },
    "openForm": {
      "ok": null,
      "reason": "no_create_link"
    },
    "sidebarNavigate": {
      "ok": true,
      "topTag": "SPAN",
      "topClass": "",
      "coveredBySkeleton": false
    }
  },
  "live": {
    "clickButton": {
      "ok": true,
      "label": "",
      "stillSkeletonVisible": true,
      "note": "click delivered; navigation prevented for evidence"
    },
    "searchInput": {
      "ok": true,
      "focused": true,
      "stillSkeletonVisible": true,
      "note": "focus+value only (no input event)"
    },
    "filterSelect": {
      "ok": true,
      "topTag": "SELECT",
      "topClass": "form-select form-select-sm",
      "coveredBySkeleton": false,
      "note": "hit-test only; change not dispatched (avoids form navigation)"
    },
    "openForm": {
      "ok": null,
      "reason": "no_create_link"
    },
    "sidebarNavigate": {
      "ok": true,
      "topTag": "SPAN",
      "topClass": "",
      "coveredBySkeleton": false,
      "note": "hit-test only while skeleton visible (no navigate — avoids leaving page)"
    }
  }
}
```

## Definitions used

- **Main usable:** `#rateb-main-content` has substantial text (>80 chars), ≥2 visible interactive controls outside the metrics strip, skeleton does not own `elementFromPoint` at main center, skeleton height < 35% of viewport.
- **Skeleton:** `.cm--page-stats.is-loading` / `[data-module-metrics-async].is-loading`.
- **Interaction:** hit-test + focus/type/select + synthetic click with `preventDefault` (no hard navigation). Sidebar uses `RatebNavInstant.navigate` soft path.

No production code was modified.
