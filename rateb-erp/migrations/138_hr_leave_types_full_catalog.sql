-- Seed full HR leave type catalog per company (138)
SET NAMES utf8mb4;

INSERT INTO rateb_leave_types (company_id, code, name, paid, days_per_year, status)
SELECT c.id, 'emergency', 'Emergency leave', 1, 5, 'active'
FROM rateb_companies c
WHERE NOT EXISTS (SELECT 1 FROM rateb_leave_types lt WHERE lt.company_id = c.id AND lt.code = 'emergency');

INSERT INTO rateb_leave_types (company_id, code, name, paid, days_per_year, status)
SELECT c.id, 'maternity', 'Maternity leave', 1, 70, 'active'
FROM rateb_companies c
WHERE NOT EXISTS (SELECT 1 FROM rateb_leave_types lt WHERE lt.company_id = c.id AND lt.code = 'maternity');

INSERT INTO rateb_leave_types (company_id, code, name, paid, days_per_year, status)
SELECT c.id, 'paternity', 'Paternity leave', 1, 3, 'active'
FROM rateb_companies c
WHERE NOT EXISTS (SELECT 1 FROM rateb_leave_types lt WHERE lt.company_id = c.id AND lt.code = 'paternity');

INSERT INTO rateb_leave_types (company_id, code, name, paid, days_per_year, status)
SELECT c.id, 'hajj', 'Hajj leave', 1, 15, 'active'
FROM rateb_companies c
WHERE NOT EXISTS (SELECT 1 FROM rateb_leave_types lt WHERE lt.company_id = c.id AND lt.code = 'hajj');

INSERT INTO rateb_leave_types (company_id, code, name, paid, days_per_year, status)
SELECT c.id, 'marriage', 'Marriage leave', 1, 5, 'active'
FROM rateb_companies c
WHERE NOT EXISTS (SELECT 1 FROM rateb_leave_types lt WHERE lt.company_id = c.id AND lt.code = 'marriage');

INSERT INTO rateb_leave_types (company_id, code, name, paid, days_per_year, status)
SELECT c.id, 'bereavement', 'Bereavement leave', 1, 5, 'active'
FROM rateb_companies c
WHERE NOT EXISTS (SELECT 1 FROM rateb_leave_types lt WHERE lt.company_id = c.id AND lt.code = 'bereavement');

INSERT INTO rateb_leave_types (company_id, code, name, paid, days_per_year, status)
SELECT c.id, 'study', 'Study leave', 1, NULL, 'active'
FROM rateb_companies c
WHERE NOT EXISTS (SELECT 1 FROM rateb_leave_types lt WHERE lt.company_id = c.id AND lt.code = 'study');

INSERT INTO rateb_leave_types (company_id, code, name, paid, days_per_year, status)
SELECT c.id, 'exam', 'Exam leave', 1, NULL, 'active'
FROM rateb_companies c
WHERE NOT EXISTS (SELECT 1 FROM rateb_leave_types lt WHERE lt.company_id = c.id AND lt.code = 'exam');

INSERT INTO rateb_leave_types (company_id, code, name, paid, days_per_year, status)
SELECT c.id, 'compensatory', 'Compensatory leave', 1, NULL, 'active'
FROM rateb_companies c
WHERE NOT EXISTS (SELECT 1 FROM rateb_leave_types lt WHERE lt.company_id = c.id AND lt.code = 'compensatory');

INSERT INTO rateb_leave_types (company_id, code, name, paid, days_per_year, status)
SELECT c.id, 'work_injury', 'Work injury leave', 1, NULL, 'active'
FROM rateb_companies c
WHERE NOT EXISTS (SELECT 1 FROM rateb_leave_types lt WHERE lt.company_id = c.id AND lt.code = 'work_injury');

INSERT INTO rateb_leave_types (company_id, code, name, paid, days_per_year, status)
SELECT c.id, 'iddah', 'Iddah leave', 1, 130, 'active'
FROM rateb_companies c
WHERE NOT EXISTS (SELECT 1 FROM rateb_leave_types lt WHERE lt.company_id = c.id AND lt.code = 'iddah');
