Separated data — each link has its own database (no conflict).

DirectAdmin (rateb.sa server):
- https://rateb.sa             → config/env/rateb_sa.php             (DB: admin_rateb)
- https://bangladesh.rateb.sa  → config/env/bangladesh_rateb_sa.php  (DB: admin_bangladesh)
- ERP tables                  → admin_rateb-erp
- Countries                   → admin_ethiopia, admin_kenya, admin_nepal, …

Set DB_PASS (and optional overrides) in public_html/.env on the server.
MySQL user is usually: admin

How it works:
- The app looks at the browser URL and loads config/env/{host}.php (dots = underscores).
- Each file sets DB_*, SITE_URL, BASE_URL for that link only.

Add a new link (e.g. saudi.rateb.sa):
1. Copy config/env/default.php
2. Save as config/env/saudi_rateb_sa.php
3. Edit: set DB_NAME=admin_saudi (or your DB), SITE_URL, BASE_URL.
