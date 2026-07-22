# Offline Guide (Universal)

After install, disconnect Internet. Branch continues on **SQLite only**:

- Login, Dashboard, POS, Inventory, Accounting, HR, Procurement, Reports  
- Printing, QR, barcode (local GD / local renderer)

No cloud required. `RATEB_RUNTIME=branch` in `storage/branch/serve.env`.

When Internet returns, **RATEB Hybrid Sync** detects connectivity and resumes push/pull/conflict/audit automatically — no user action.
