# New Agency Domain Onboarding Checklist

Use this checklist when onboarding a new custom agency domain (example: `https://www.domain.com`) without changing system architecture.

## 1) Domain and DNS

- [ ] Domain is purchased/owned and active.
- [ ] `A` record for root (`@`) points to your server IP.
- [ ] `www` record points correctly (`A` or `CNAME`).
- [ ] DNS propagation confirmed.

## 2) cPanel Hosting Setup

- [ ] Add domain in cPanel (Addon Domain or Alias) on the same Ratib hosting account.
- [ ] Document root points to the same Ratib project root.
- [ ] SSL is installed and valid for `https://www.domain.com`.

## 3) Pre-check URLs

- [ ] `https://www.domain.com/pages/login.php` opens successfully.
- [ ] `https://www.domain.com/` opens without SSL/browser warnings.

## 4) Database Setup

- [ ] Target database exists.
- [ ] Database user exists.
- [ ] Database user is added to the target database.
- [ ] Required privileges are granted.
- [ ] Final DB values are confirmed exactly:
  - host
  - port
  - username
  - password
  - database name

## 5) Control Panel Agency Record

In `Manage Agencies`:

- [ ] Country is correct.
- [ ] `Site URL` is set to exact domain URL (for example `https://www.domain.com`).
- [ ] `DB Host` is correct (usually `localhost`).
- [ ] `DB Port` is correct (usually `3306`).
- [ ] `DB User` is exact.
- [ ] `DB Pass` is exact.
- [ ] `DB Name` is exact.
- [ ] Agency is `Active`.
- [ ] Agency is not `Suspended` (or use Mark Paid/Unsuspend flow).

## 6) Control Open Flow Test

- [ ] Click `Open` from `Manage Agencies`.
- [ ] Agency opens through control SSO without manual login prompt (when control session is valid).
- [ ] URL host matches the agency domain.
- [ ] Dashboard loads with correct agency/country context.

## 7) Error Code Mapping (if blocked)

- `DB_CONNECT_FAILED` -> Fix DB credentials or DB permissions.
- `AGENCY_SUSPENDED` -> Mark Paid or Unsuspend agency.
- `AGENCY_INACTIVE` -> Activate agency.
- `AGENCY_SITE_URL_MISSING` -> Add `Site URL`.
- `AGENCY_SITE_URL_MISMATCH` -> Correct `Site URL` and domain mapping.
- `AGENCY_MAPPING_MISSING` -> Verify agency record and mapping in control tables.

## 8) Final Business Validation

- [ ] Agency admin login works.
- [ ] Data shown is from the correct agency DB.
- [ ] Logout and login keep correct agency URL behavior.
- [ ] Core modules needed by agency load successfully.

---

## Quick Fill Template (copy per agency)

- Agency Name:
- Country:
- Site URL:
- DB Host:
- DB Port:
- DB User:
- DB Pass:
- DB Name:
- Active (1/0):
- Suspended (1/0):
- Open Test (pass/fail):
- Login Test (pass/fail):
- Notes:
