Separated data — each link has its own database (no conflict).

Current links:
- https://bangladesh.out.ratib.sa  → config/env/bangladesh_out_ratib_sa.php  (DB: outratib_out, has Bangla)
- https://out.ratib.sa           → config/env/out_ratib_sa.php             (DB: outratib_out, no Bangla)

How it works:
- The app looks at the browser URL and loads config/env/{host}.php (dots = underscores).
- Each file sets DB_*, SITE_URL, BASE_URL for that link only.

Add a new link (e.g. saudi.out.ratib.sa):
1. Copy config/env/default.php
2. Save as config/env/saudi_out_ratib_sa.php
3. Edit: set DB_NAME, DB_USER, DB_PASS, SITE_URL, BASE_URL for that server.
