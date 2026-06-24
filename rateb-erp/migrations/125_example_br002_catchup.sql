-- RATEB ERP — fix BR002 branch if 124 INSERT failed on address UNHEX
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

SET @ex_cid = (SELECT id FROM rateb_companies WHERE slug = 'example-medical' LIMIT 1);

INSERT INTO rateb_branches (company_id, name, code, address, phone, email, status, is_main)
SELECT @ex_cid,
       CONVERT(UNHEX('D981D8B1D8B920D8ACD8AFD8A9') USING utf8mb4),
       'BR002', CONVERT(UNHEX('D8ACD8AFD8A9202D20D8A7D984D8B1D98AD8A7D8B6') USING utf8mb4), '+966500000102', 'jeddah@example.rateb.sa', 'active', 0
FROM DUAL
WHERE @ex_cid IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM rateb_branches WHERE company_id = @ex_cid AND code = 'BR002' LIMIT 1);

SET @jed_bid = (SELECT id FROM rateb_branches WHERE company_id = @ex_cid AND code = 'BR002' LIMIT 1);

INSERT INTO rateb_user_branches (user_id, branch_id)
SELECT u.id, @jed_bid FROM rateb_users u
WHERE u.email = 'branch@example.rateb.sa' AND @jed_bid IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM rateb_user_branches ub WHERE ub.user_id = u.id AND ub.branch_id = @jed_bid);
