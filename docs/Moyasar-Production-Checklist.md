# Moyasar Production Checklist

Operational status for RATIB ERP Moyasar payment gateway. **No API keys or secrets in this file.**

## Status

| Step | Status |
|------|--------|
| Migration applied (`220_payment_gateway_infrastructure.sql`) | ✅ |
| Secrets configured (`config/env/moyasar.secrets.php`) | ⏳ Pending user input |
| Sandbox mode enabled and tested | ⏳ Pending |
| Webhook URL registered in Moyasar dashboard | ⏳ Pending |
| Production mode enabled | ⏳ Pending |

## Tables (migration 220)

- `rateb_payment_gateway_settings`
- `rateb_payment_transactions`
- `rateb_payment_webhooks`

## Post-secrets steps

1. Set `MOYASAR_ENABLED=1` and sandbox keys in `config/env/moyasar.secrets.php` (server only).
2. Admin → Payment Gateways: enable Moyasar, mode **sandbox**.
3. Register webhook: `POST /api/v1/payments/webhooks/moyasar` (full production URL under `/rateb-erp/public/`).
4. Set `MOYASAR_WEBHOOK_SECRET` from Moyasar dashboard.
5. Portal: Pay Online on a test invoice → confirm webhook → verify `rateb_payments` and accounting entry.
6. Switch to production keys and `MOYASAR_MODE=production` only after sandbox sign-off.

## Do not

- Commit `config/env/moyasar.secrets.php`
- Enable production without webhook secret (fail-closed in code)
