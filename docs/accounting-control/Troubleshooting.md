# Troubleshooting

## UI loads but all zeros

- Event store empty — ingest or replay events first.
- Wrong company filter — set company ID and Apply.

## Drift / Reconciliation SQL errors (`Unknown column 'payload'`)

- Run schema catchup: open Control Center (auto) or `public/run-accounting-schema-catchup.php`.
- Confirm Phase 5 migration applied on `admin_rateb`.

## Gateway OFF in Settings

- Set `ACCOUNTING_GATEWAY_ENABLED=1` in `.env`, or enable `ACCOUNTING_EVENT_STORE_ENABLED=1` (gateway defaults from event store in `config/accounting.php`).

## 403 on API

- Missing permission slug — run migration 151.
- Invalid CSRF on POST — refresh page.

## Export empty

- No rows for current filters — widen date range or company.
- Projections/consolidation: run rebuild/run first.

## Diagnostics FAIL

- Open **Diagnostics** screen — each row shows missing file/table.
- Deploy `app/Accounting/` beside `rateb-erp/` on server.

## Mixed Arabic/English numerals

- Hard refresh after deploy (`ratib-erp-build.txt` marker).
- EN mode uses Western numerals on managed inputs.
