#!/usr/bin/env bash
# Phase B2 — macOS-only iOS release validation (no store secrets committed).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if [[ "$(uname -s)" != "Darwin" ]]; then
  echo "ERROR: iOS build requires macOS + Xcode. Current host: $(uname -s)" >&2
  exit 1
fi

flutter clean
flutter pub get

if [[ ! -f ios/Runner/GoogleService-Info.plist ]]; then
  echo "WARN: ios/Runner/GoogleService-Info.plist missing — push soft-fails until copied from Firebase Console (see .example)."
fi

flutter build ios --release --no-codesign --dart-define=APP_FLAVOR=production

echo "OK: ios build --release --no-codesign completed."
echo "Next: open ios/Runner.xcworkspace, select Production scheme, Archive with Development Team."
