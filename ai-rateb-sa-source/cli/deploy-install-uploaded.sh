#!/bin/bash
set -eu
DOC="/home/admin/domains/ai.rateb.sa"
SRC="${DOC}/tmp-upload-batch"
install() { mv -f "$SRC/$1" "$DOC/$2"; }
install platform_settings_schema.php migrations/platform_settings_schema.php
install company_hr_schema.php migrations/company_hr_schema.php
install public-gate.php app/public-gate.php
install hr-lib.php app/hr/hr-lib.php
install hr-overview.php admin/hr-overview.php
install settings.php admin/settings.php
install auth.php admin/auth.php
install users.php admin/users.php
install user.php admin/user.php
install bootstrap.php admin/bootstrap.php
install schema.php app/schema.php
install nav.php app/nav.php
install index.php index.php
install register.php app/auth/register.php
install forgot-password.php app/auth/forgot-password.php
install apply-hr-platform-schema.php cli/apply-hr-platform-schema.php
install platform-public-gate.php tests/platform-public-gate.php
install hr-tenant-smoke.php tests/hr-tenant-smoke.php
rmdir "$SRC" 2>/dev/null || true
echo INSTALL_BATCH_OK
