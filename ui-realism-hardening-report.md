# UI realism hardening report

**Date:** 2026-05-18  
**Target:** ~10–15% further reduction in visual intensity (cumulative with enterprise-realism pass)

---

## Principles

- **Calm surfaces** over dramatic trust theater  
- **Readable ops UI** over “classified console” aesthetics  
- **Explicit sample state** over implied live production  

---

## CSS changes

### `css/pages/about-enterprise.css`

| Element | Before | After |
|---------|--------|-------|
| Profile distinct banner | Purple gradient + glow shadow | Flat slate bar, no shadow |
| Hero glow | Blue/violet dual radial | Single muted slate radial |
| Page label badge | Violet glow box-shadow | None |
| `.rateb-about-gradient` | Bright gradient text | Slate text + subtle underline |
| `.rateb-about-shot--glow` | 60px blue glow | Simple drop shadow |
| Arch layer SVG | Animated pulsing dots, violet gradient stroke | Static dots, `#475569` stroke |
| Arch card hover | Blue glow shadow | Border only, no glow |
| Profile nav current pill | Violet gradient + glow | Flat slate fill |

### `css/pages/enterprise-trust-layer.css`

| Element | Before | After |
|---------|--------|-------|
| Trust section background | Vertical gradient wash | Flat `rgba(12,18,32)` |
| Hero strip / indicator “ok” | Bright green `#6ee7b7` | Muted `#94a3b8` |
| Home title gradient override | (already softened) | Retained |

### `css/pages/home-public.css`

| Element | Before | After |
|---------|--------|-------|
| `.rateb-pill--live` | Green live-stream styling | Muted slate (when used) |
| Ops section | No disclaimer style | `.rateb-ops__disclaimer` mono line |

### `css/pages/operational-proof.css` (new)

- Neutral borders `#475569`  
- No gradients on cards  
- `.rateb-op-sample` uppercase mono badges  

---

## Markup / copy UI changes

| Location | Change |
|----------|--------|
| Home hero dash | “Sample” instead of “Live”; `ws-demo-01` |
| Home ops panel | `sample.ops.panel`; “Sample operational data” pill; removed “streaming” |
| Home analytics | Disclaimer on cards 2–4 (card 1 already had) |
| Profile metrics | Illustrative disclaimer under KPI row |
| Profile telemetry stream | Sample event stream label |

---

## Diagram visual language

New SVGs use:

- Background `#0f172a`  
- Strokes `#475569` / `#64748b`  
- Labels “sample” / “illustrative” in subtitle text  
- No purple/blue marketing gradients  

---

## Remaining intensity (acceptable)

- Dark theme overall (brand choice)  
- Mono tags for structure (ops panels)  
- Existing program-preview SVGs in carousel (can be toned in a later pass)  

---

## Recommended next steps (optional)

1. Replace `program-preview-*.svg` gradients with slate diagrams matching new set.  
2. Add sanitized PNG exports from staging (blur PII) into `assets/images/ops-sanitized/`.  
3. Lighten `body` background on profile from `#12081f` to `#0c1220` for parity with home ops section.  
