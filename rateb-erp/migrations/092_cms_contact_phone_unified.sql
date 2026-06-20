-- Unified marketing: correct public contact phone (WhatsApp / topbar)
SET NAMES utf8mb4;

UPDATE rateb_cms_contact_settings
SET phone = '+966 599863868'
WHERE phone = '' OR phone = '+966 11 000 0000' OR phone LIKE '+966 11%';
