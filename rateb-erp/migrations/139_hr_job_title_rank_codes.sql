-- Normalize legacy job title codes to hierarchy rank JT-01..JT-09 (139)
SET NAMES utf8mb4;

UPDATE rateb_hr_job_titles AS jt
LEFT JOIN rateb_hr_job_titles AS x
  ON x.company_id = jt.company_id AND x.code = 'JT-01' AND x.id <> jt.id
SET jt.code = 'JT-01'
WHERE jt.code IN ('GM', 'gm')
  AND x.id IS NULL;

UPDATE rateb_hr_job_titles AS jt
LEFT JOIN rateb_hr_job_titles AS x
  ON x.company_id = jt.company_id AND x.code = 'JT-02' AND x.id <> jt.id
SET jt.code = 'JT-02'
WHERE jt.code IN ('HR', 'hr')
  AND x.id IS NULL;

UPDATE rateb_hr_job_titles AS jt
LEFT JOIN rateb_hr_job_titles AS x
  ON x.company_id = jt.company_id AND x.code = 'JT-03' AND x.id <> jt.id
SET jt.code = 'JT-03'
WHERE jt.code IN ('ACC', 'acc')
  AND x.id IS NULL;

UPDATE rateb_hr_job_titles AS jt
LEFT JOIN rateb_hr_job_titles AS x
  ON x.company_id = jt.company_id AND x.code = 'JT-04' AND x.id <> jt.id
SET jt.code = 'JT-04'
WHERE jt.code IN ('PROC', 'proc')
  AND x.id IS NULL;

UPDATE rateb_hr_job_titles AS jt
LEFT JOIN rateb_hr_job_titles AS x
  ON x.company_id = jt.company_id AND x.code = 'JT-05' AND x.id <> jt.id
SET jt.code = 'JT-05'
WHERE jt.code IN ('WH', 'wh')
  AND x.id IS NULL;

UPDATE rateb_hr_job_titles AS jt
LEFT JOIN rateb_hr_job_titles AS x
  ON x.company_id = jt.company_id AND x.code = 'JT-06' AND x.id <> jt.id
SET jt.code = 'JT-06'
WHERE jt.code IN ('DRV', 'drv')
  AND x.id IS NULL;

UPDATE rateb_hr_job_titles AS jt
LEFT JOIN rateb_hr_job_titles AS x
  ON x.company_id = jt.company_id AND x.code = 'JT-07' AND x.id <> jt.id
SET jt.code = 'JT-07'
WHERE jt.code IN ('TEC', 'tec')
  AND x.id IS NULL;

UPDATE rateb_hr_job_titles AS jt
LEFT JOIN rateb_hr_job_titles AS x
  ON x.company_id = jt.company_id AND x.code = 'JT-08' AND x.id <> jt.id
SET jt.code = 'JT-08'
WHERE jt.code IN ('ADM', 'adm')
  AND x.id IS NULL;

UPDATE rateb_hr_job_titles AS jt
LEFT JOIN rateb_hr_job_titles AS x
  ON x.company_id = jt.company_id AND x.code = 'JT-09' AND x.id <> jt.id
SET jt.code = 'JT-09'
WHERE jt.code IN ('SAL', 'sal')
  AND x.id IS NULL;
