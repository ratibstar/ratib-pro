#!/bin/bash
# RATIB Contact Center — start WebSocket realtime hub (background)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
LOG="${RCC_REALTIME_LOG:-$ROOT/storage/logs/rcc-realtime-hub.log}"
mkdir -p "$(dirname "$LOG")"
PHP="${RCC_PHP_BIN:-php}"
export RCC_REALTIME_HUB_HOST="${RCC_REALTIME_HUB_HOST:-127.0.0.1}"
export RCC_REALTIME_HUB_PORT="${RCC_REALTIME_HUB_PORT:-9701}"
export RCC_WEBSOCKET_HOST="${RCC_WEBSOCKET_HOST:-0.0.0.0}"
export RCC_WEBSOCKET_PORT="${RCC_WEBSOCKET_PORT:-9702}"
nohup "$PHP" "$ROOT/bin/rcc-realtime-hub.php" >>"$LOG" 2>&1 &
echo "RCC Realtime Hub started PID=$! (log: $LOG)"
