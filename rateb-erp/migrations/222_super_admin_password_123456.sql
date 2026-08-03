-- Set default super-admin password to 123456 (admin@rateb.sa)
UPDATE rateb_users
SET password = '$2y$10$7qR7yib4llgToR8eILDO5e3ovQA8lsjA3k8sJfJ2LZ0tak3QrczJW'
WHERE email = 'admin@rateb.sa';
