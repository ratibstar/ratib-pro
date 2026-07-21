#!/usr/bin/env bash
# Phase L1 — macOS iOS production build prep (no certificates / no secrets / no upload).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if [[ "$(uname -s)" != "Darwin" ]]; then
  echo "ERROR: iOS build requires macOS + Xcode. Current host: $(uname -s)" >&2
  echo "See docs/PHASE_L1.md" >&2
  exit 1
fi

echo "== L1 preflight (static; no signing) =="
PROD_XCCONFIG="ios/Flutter/Production.xcconfig"
SCHEME="ios/Runner.xcodeproj/xcshareddata/xcschemes/Production.xcscheme"
PBX="ios/Runner.xcodeproj/project.pbxproj"

if [[ ! -f "$PROD_XCCONFIG" ]]; then
  echo "ERROR: missing $PROD_XCCONFIG" >&2
  exit 1
fi
if ! grep -q 'PRODUCT_BUNDLE_IDENTIFIER = sa.rateb.hr.mobile' "$PROD_XCCONFIG"; then
  echo "ERROR: Production.xcconfig Bundle ID must be sa.rateb.hr.mobile" >&2
  exit 1
fi
if [[ ! -f "$SCHEME" ]]; then
  echo "ERROR: missing Production.xcscheme" >&2
  exit 1
fi
if ! grep -q 'ArchiveAction' "$SCHEME" || ! grep -q 'buildConfiguration = "Release"' "$SCHEME"; then
  echo "ERROR: Production.xcscheme must Archive with Release" >&2
  exit 1
fi
if ! grep -q 'Flutter/Production.xcconfig' "$PBX"; then
  echo "ERROR: project.pbxproj must reference Production.xcconfig" >&2
  exit 1
fi
echo "OK: Bundle ID sa.rateb.hr.mobile · Production.xcscheme Archive=Release"

if [[ ! -f ios/Runner/GoogleService-Info.plist ]]; then
  echo "WARN: ios/Runner/GoogleService-Info.plist missing — copy locally from Firebase (see .example). Do not commit."
fi

echo "== flutter clean / pub get =="
flutter clean
flutter pub get

echo "== flutter build ios --release --no-codesign =="
flutter build ios --release --no-codesign --dart-define=APP_FLAVOR=production

cat <<'EOF'

OK: release iOS build completed (--no-codesign).

Next (operator only — certificates stay on this Mac):
  1. open ios/Runner.xcworkspace
  2. Scheme: Production · Destination: Any iOS Device
  3. Signing & Capabilities → select Development Team (local)
  4. Confirm Bundle ID sa.rateb.hr.mobile
  5. Product → Archive → Distribute → App Store Connect / TestFlight
  6. Copy ExportOptions.plist.example → local ExportOptions.plist (Team ID local only)

Checklist: docs/PHASE_L1.md
Never commit .p12, profiles, AuthKey .p8, or GoogleService-Info.plist
EOF
