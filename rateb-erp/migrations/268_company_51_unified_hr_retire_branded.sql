-- Company #51 (Al-Arfaj): retire separate HR APK (sa.rateb.hr.mobile.c51).
-- Use unified RATEB HR (sa.rateb.hr.mobile) + activation code EHU8-FWJU like other platform companies.
-- Removes branded keys only; company data and activation code unchanged.

UPDATE rateb_companies
SET settings = JSON_REMOVE(settings, '$.mobile_branded.hr')
WHERE id = 51
  AND JSON_EXTRACT(settings, '$.mobile_branded.hr') IS NOT NULL;

UPDATE rateb_companies
SET settings = JSON_REMOVE(settings, '$.mobile_branded_specs.hr')
WHERE id = 51
  AND JSON_EXTRACT(settings, '$.mobile_branded_specs.hr') IS NOT NULL;

UPDATE rateb_companies
SET settings = JSON_REMOVE(settings, '$.mobile_branded_queue.hr')
WHERE id = 51
  AND JSON_EXTRACT(settings, '$.mobile_branded_queue.hr') IS NOT NULL;
