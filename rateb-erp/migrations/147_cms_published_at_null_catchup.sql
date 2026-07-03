-- CMS datetime columns: coerce invalid published_at to NULL (MySQL strict mode).
UPDATE rateb_cms_blog_articles
SET published_at = NULL
WHERE published_at IS NOT NULL
  AND CAST(published_at AS CHAR) IN ('', '0000-00-00 00:00:00');

UPDATE rateb_cms_pages
SET published_at = NULL
WHERE published_at IS NOT NULL
  AND CAST(published_at AS CHAR) IN ('', '0000-00-00 00:00:00');
