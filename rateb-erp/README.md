# RATEB ERP — Medical Procurement & Healthcare ERP

Pure PHP 7.4–8.2 SaaS platform with multi-tenant isolation, Super Admin panel, Company portal, and REST API v1.

## Requirements

- PHP 7.4 – 8.2 with PDO MySQL
- MySQL 5.7+ / MariaDB 10.3+
- Apache with `mod_rewrite` (or nginx equivalent)
- No Composer required

## Installation (cPanel / VPS)

1. Upload the `rateb-erp/` folder to your web root (alongside existing RATIB files).
2. Configure database credentials in the project `.env` file (shared with main RATIB app):
   ```
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_NAME=your_database
   DB_USER=your_user
   DB_PASS=your_password
   ```
3. Run migrations:
   ```bash
   php rateb-erp/migrations/run.php
   ```
4. Set document root or access via:
   - **Super Admin:** `https://yourdomain.com/rateb-erp/public/admin`
   - **Company Portal:** `https://yourdomain.com/rateb-erp/public/company`
   - **API:** `https://yourdomain.com/rateb-erp/public/api/v1`

5. Ensure `rateb-erp/storage/logs` and `rateb-erp/storage/uploads` are writable (755).

### Default Super Admin

- Email: `admin@rateb.sa`
- Password: `password` (change immediately after first login)

## Control Panel Integration

The RATEB ERP menu (`fa-hospital` icon) is automatically added to the Control Panel sidebar with submenu:

Dashboard, Companies, Subscriptions, Procurement, Inventory, Suppliers, Assets, Contracts, Reports, Settings

## Architecture

```
rateb-erp/
├── app/          # Controllers, models, services (MVC + OOP)
├── views/        # PHP templates only (no inline CSS/JS)
├── public/       # Web root (index.php, assets)
├── config/       # App & database config, i18n
├── routes/       # Web, company, API routes
├── migrations/   # SQL schema & seeds
└── storage/      # Logs & uploads
```

## Security

- PDO prepared statements
- CSRF tokens on all forms
- XSS escaping via `View::escape()`
- Session hardening (httponly, samesite)
- Rate limiting on login
- Audit logs & login activity
- 2FA-ready (`two_factor_secret`, `two_factor_enabled` on users)
- API Bearer token authentication

## API v1

Obtain token:
```http
POST /rateb-erp/public/api/v1/auth/token
Content-Type: application/json

{"email":"admin@rateb.sa","password":"password"}
```

Use token:
```http
GET /rateb-erp/public/api/v1/dashboard
Authorization: Bearer YOUR_TOKEN
```

Alternative proxy path: `/api/v1/rateb/`

## Multi-Tenancy

All company data includes `company_id`. Tenant scoping is enforced in models via `TenantContext`. Super admins bypass tenant scope for platform management.

## Themes & i18n

- Light / Dark / Auto theme (CSS variables + localStorage)
- English & Arabic with RTL support
- Switch locale via top bar or `?` locale routes

## Company Setup

1. Super Admin creates a company (`admin/companies`)
2. Assign a plan and activate the company
3. Create a company user (non super-admin) linked to `company_id`
4. Company user logs in at `/company/login`

## License

Proprietary — RATEB Platform
