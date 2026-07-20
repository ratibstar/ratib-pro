-- Phase I.1 — Push foundation columns on mobile device registry
-- Additive only; unique identity (company_id, client_app, device_id) unchanged.

ALTER TABLE rateb_mobile_devices
    ADD COLUMN push_provider VARCHAR(16) NOT NULL DEFAULT 'none' AFTER push_token,
    ADD COLUMN locale VARCHAR(16) NULL AFTER push_provider;
