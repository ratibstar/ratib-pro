# Security & Compliance Trust Center — Implementation Report

**Date:** 2026-05-18  
**Canonical URL:** `/security-compliance/`  
**Purpose:** Enterprise procurement-ready public trust center for regulated workforce program infrastructure.

---

## Summary

A new public trust center page positions **RATEB** as infrastructure suitable for regulated workforce operations and enterprise procurement review. The page follows the same chrome pattern as the company profile (`about.php`): shared mega-nav, public footer, `about-enterprise.css` tokens, and dedicated trust-center styling.

**Wording policy:** Copy uses *designed for*, *supports*, and *architecture includes*. No SOC 2, ISO, or other third-party certification claims unless separately documented in a signed enterprise agreement. An explicit disclaimer appears in the hero.

---

## Files Created

| File | Role |
|------|------|
| `pages/security-compliance.php` | Page template: SEO meta, JSON-LD `WebPage`, chrome includes, sticky jump nav, asset cache-busting |
| `includes/rateb-security-compliance-data.php` | Content config: meta, hero, 7 sections, procurement CTAs, disclaimer, contact |
| `includes/rateb-security-compliance-sections.php` | PHP renderers: hero, feature grids, isolation diagram, procurement block |
| `css/pages/security-compliance.css` | Trust-center layout (banner, hero, isolation diagram, procurement cards, scroll reveal hooks) |
| `js/pages/security-compliance.js` | Lightweight `data-rateb-reveal` scroll animations |

---

## Files Modified

| File | Change |
|------|--------|
| `.htaccess` | Pretty URL rewrites; LiteSpeed `CacheDisable` and `RATEB_NOCACHE` for page and assets |
| `includes/rateb-mega-nav-resolve.php` | `security_compliance` → `/security-compliance/` |
| `includes/rateb-mega-nav-config.php` | Company mega panel + Security mega panel trust-center links |
| `includes/rateb-home-public-footer.php` | Legal column link to trust center |
| `includes/site-content-home-data.php` | CMS key `home.footer.link.legal.security_compliance` |

---

## Routing & Caching

```
/security-compliance     → 301 → /security-compliance/
/security-compliance/    → pages/security-compliance.php
/pages/security-compliance/ → 302 → /security-compliance/
```

Cache bypass mirrors profile/about: no-store headers on the PHP page plus `.htaccess` rules for `/security-compliance`, `security-compliance.css`, and `security-compliance.js`.

---

## Page Sections (anchor IDs)

| # | Section | Anchor |
|---|---------|--------|
| 1 | Security Overview | `#security-overview` |
| 2 | Compliance & Governance | `#compliance-governance` |
| 3 | Data Isolation | `#data-isolation` |
| 4 | Authentication & Access | `#authentication` |
| 5 | Operational Reliability | `#operational-reliability` |
| 6 | Infrastructure Notes | `#infrastructure` |
| 7 | Procurement & Enterprise Review | `#procurement` |

Sticky jump nav in `<main>` links to all anchors.

---

## Procurement CTAs

All CTAs use `mailto:info@rateb.sa` with distinct subjects:

- **Request Security Brief** — `RATEB — Request Security Brief`
- **Request Architecture Review** — `RATEB — Request Architecture Review`
- **Contact Enterprise Team** — `RATEB — Enterprise Team Inquiry`

WhatsApp enterprise line linked in the procurement footer note.

---

## Navigation Integration

- **Mega nav → Company:** “Security & compliance” (`href_key: security_compliance`)
- **Mega nav → Security:** “Security & compliance center” (first item under Trust & protect)
- **Footer → Legal:** “Security & compliance” → `/security-compliance/`

On the trust page, `$ratebHomeNavHrefPrefix` points to `home.php` so hash links in chrome resolve to the marketing home.

---

## SEO Metadata

- `<title>`: Security & Compliance — RATEB Trust Center
- `<meta name="description">`: architecture/governance/isolation positioning (no certification claims)
- `rel="canonical"`: `{baseUrl}/security-compliance/`
- Open Graph: `title`, `description`, `type=website`, `url`
- JSON-LD: `WebPage` with `isPartOf` → `WebSite`, `about` → `Organization`

---

## Design System

- **Base:** `home-public.css`, `rateb-mega-nav.css`
- **Enterprise tokens:** `about-enterprise.css` (glass cards, typography, buttons, section headers)
- **Page-specific:** `security-compliance.css` (trust banner, isolation diagram, procurement grid)
- **Body class:** `rateb-trust-page` on `rateb-saas-home`
- **Tone:** Dense feature grids, mono accents, government-tech palette aligned with company profile

---

## Verification

- [x] PHP syntax: `pages/security-compliance.php`, `rateb-security-compliance-data.php`, `rateb-security-compliance-sections.php` — no errors
- [ ] Browser smoke test on production/staging after deploy
- [ ] Confirm `.htaccess` active on host (LiteSpeed/cPanel)
- [ ] Validate mega-nav links from home and trust page

---

## Deploy Notes

1. Upload all files listed above plus modified includes and `.htaccess`.
2. Purge CDN / LiteSpeed cache for `/security-compliance/` if a stale shell appears.
3. Optional: bump `public/rateb-build.txt` if your deploy script uses it for asset rev.

---

## Out of Scope (intentional)

- No changes under `Designed/`
- No SMTP, env, or backend mailer changes
- No invented certifications or compliance badges
- No new API endpoints — page is static marketing/trust content

---

## Contact Reference

Public enterprise contact on page: **info@rateb.sa**
