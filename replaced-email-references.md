# Replaced Public Email References

Target canonical email:

- `info@rateb.sa`

## Replacements Applied

- `includes/ratib-home-public-footer.php`
  - `mailto:ratibstar@gmail.com` -> `mailto:info@rateb.sa`
  - Visible footer email text updated to `info@rateb.sa`
- `includes/ratib-about-profile-data.php`
  - Company profile contact email updated to `info@rateb.sa`
- `includes/ratib-about-sections.php`
  - "Talk to Solutions Team" mailto updated to `info@rateb.sa`
- `pages/about.php`
  - Schema.org `Organization` `contactPoint.email` updated to `info@rateb.sa`
- `pages/customer-portal.php`
  - Help/contact email link and visible value updated to `info@rateb.sa`
- `js/chat-widget.js`
  - Public support contact answer email updated from misspelled Gmail to `info@rateb.sa`

## Validation

- Public-facing search scope (`pages/`, `includes/`, `js/`, `public/`) no longer contains:
  - `ratibstar@gmail.com`
  - fallback Gmail contact strings
  - hardcoded legacy public contact Gmail references

## Intentionally Not Changed

- Backend normalization comment in `api/notifications/notifications-api.php` still mentions Gmail equivalence.
  - This is non-public runtime guidance and does not affect visible branding.
- SMTP/config/env mail transport values were not changed.
  - No mail transport logic or backend transactional mailers were modified.

