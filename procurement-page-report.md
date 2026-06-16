# Procurement & Legal Page — Implementation Report

**Date:** 2026-05-18  
**Canonical URL:** `/procurement-legal/`  
**Purpose:** Formal enterprise reference for government buyers, procurement teams, international partners, and compliance reviewers.

---

## Summary

A procurement-facing page provides verifiable company identity, engagement process, cross-links to security and architecture documentation, tenant separation summary, legal scope notes, procurement CTAs, and official escalation contact at **info@rateb.sa**. Wording avoids fabricated licenses, certifications, or government partnerships.

---

## Files Created

| File | Role |
|------|------|
| `pages/procurement-legal.php` | Page template, SEO, JSON-LD `WebPage` + Organization contact |
| `includes/rateb-procurement-legal-data.php` | All section copy, CTAs, links |
| `includes/rateb-procurement-legal-sections.php` | Section renderers |
| `css/pages/procurement-legal.css` | Formal slate/stone enterprise styling |
| `js/pages/procurement-legal.js` | Scroll reveal |

---

## Files Modified

| File | Change |
|------|--------|
| `.htaccess` | Rewrites + cache bypass |
| `includes/rateb-mega-nav-resolve.php` | `procurement_legal` → `/procurement-legal/` |
| `includes/rateb-mega-nav-config.php` | Company panel link |
| `includes/rateb-home-public-footer.php` | Legal column link |
| `includes/site-content-home-data.php` | `home.footer.link.legal.procurement` |

---

## Routing

```
/procurement-legal      → 301 → /procurement-legal/
/procurement-legal/     → pages/procurement-legal.php
/pages/procurement-legal/ → 302 → /procurement-legal/
```

---

## Sections

| # | Section | Anchor |
|---|---------|--------|
| 1 | Company Identity | `#company-identity` |
| 2 | Enterprise Engagement | `#enterprise-engagement` |
| 3 | Security & Governance | `#security-governance` |
| 4 | Data & Tenant Boundaries | `#data-tenant-boundaries` |
| 5 | Legal & Operational Notes | `#legal-operational-notes` |
| 6 | Procurement Requests | `#procurement-requests` |
| 7 | Contact & Escalation | `#contact-escalation` |

---

## Procurement CTAs (mailto:info@rateb.sa)

- Request Company Deck  
- Request Enterprise Demo  
- Request Architecture Brief  
- Contact Enterprise Team  

---

## Cross-links

- Banner → `/security-compliance/`, `/architecture/`, marketing home  
- Section 3 → full trust center and architecture pages  
- Company identity aligned with `rateb-about-profile-data.php` (CR under NDA, VAT on invoice)

---

## Defensive wording

- Hero notice: no implied government partnerships or uncertified compliance claims  
- CR: on file, available under NDA  
- VAT: on invoice / formal request  
- Security summary: no SOC 2 / ISO on public pages unless in signed agreement  

---

## Verification

- [x] PHP syntax lint passed  
- [ ] Post-deploy browser check  
- [ ] LiteSpeed cache purge after upload  
