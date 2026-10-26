#!/usr/bin/env bash
# Manage the ai.rateb.sa video worker cron entry of the account that runs this script.
# The deploy workflow pipes it over SSH; it is never uploaded to the web root.
#   bash video-cron.sh install <target>   add or replace the tagged entry for <target>
#   bash video-cron.sh remove             delete only the tagged entry
#   bash video-cron.sh wait <seconds>     wait for running worker passes to exit
# Every other crontab line is kept unchanged and in order; if the result cannot be
# verified, the backup taken before the change is restored. The entry does not start the
# worker while ~/.rateb-ai/video-worker.hold exists (left by a deploy that stopped midway).
set -euo pipefail

php_bin=/usr/local/bin/php
state="$HOME/.rateb-ai"
tag='# rateb-ai-video-worker'
tag_re='(^|[[:space:]])# rateb-ai-video-worker$'

count_tagged() { grep -cE "$tag_re" "$1" || true; }
untagged() { grep -vE "$tag_re" "$1" || true; }

work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT
current="$work/current"
next="$work/next"

read_crontab() {
  if ! crontab -l > "$current" 2> "$work/err"; then
    if grep -qi 'no crontab' "$work/err"; then
      : > "$current"
    else
      echo "CRON=error: could not read the current crontab; nothing changed"
      exit 1
    fi
  fi
}

backup_crontab() {
  mkdir -p "$state"
  chmod 700 "$state"
  backup="$state/crontab.backup.$(date -u +%Y%m%dT%H%M%SZ).$$"
  cp "$current" "$backup"
  chmod 600 "$backup"
  ls -1t "$state"/crontab.backup.* 2>/dev/null | tail -n +6 | xargs -r rm -f
  echo "CRON_BACKUP=$backup"
}

# Confirm the written crontab has the expected number of tagged lines and that every
# other line is identical to what was there before; otherwise restore the backup.
verify_or_restore() {
  local expected="$1" installed="$work/installed"
  if ! crontab -l > "$installed" 2> /dev/null; then
    : > "$installed"
  fi
  if [ "$(count_tagged "$installed")" != "$expected" ] || [ "$(untagged "$current")" != "$(untagged "$installed")" ]; then
    crontab "$backup"
    echo "CRON=error: verification failed; the previous crontab was restored"
    exit 1
  fi
}

refuse() {
  echo "CRON=refused: $1"
  exit 1
}

owned_by_me() {
  [ "$(stat -c %u "$1")" = "$(id -u)" ]
}

case "${1:-}" in
  install)
    target="${2:-}"
    home_real="$(cd "$HOME" && pwd -P)"
    case "$target" in
      "$home_real/domains/ai.rateb.sa" | "$home_real/domains/ai.rateb.sa/public_html" | "$home_real/domains/ai.rateb.sa/private_html") ;;
      *) refuse "the target is not ai.rateb.sa inside this account's home directory" ;;
    esac
    [ -d "$target" ] && [ "$(cd "$target" && pwd -P)" = "$target" ] || refuse "the target is not a real directory"
    [ -f "$target/cron/video-worker.php" ] || refuse "cron/video-worker.php is missing from the target"
    for path in "$home_real" "$target" "$target/cron/video-worker.php"; do
      owned_by_me "$path" || refuse "$path is not owned by the deploying account"
    done
    line="* * * * * [ ! -e $state/video-worker.hold ] && $php_bin $target/cron/video-worker.php --log=$state/video-worker.log >> $state/video-worker.log 2>&1 $tag"
    read_crontab
    backup_crontab
    echo "CRON_TAGGED_BEFORE=$(count_tagged "$current")"
    untagged "$current" > "$next"
    printf '%s\n' "$line" >> "$next"
    crontab "$next"
    verify_or_restore 1
    echo "CRON_OTHER_ENTRIES_PRESERVED=$(grep -cvE "$tag_re" "$current" || true)"
    echo "CRON_ENTRY=installed"
    ;;
  remove)
    read_crontab
    tagged="$(count_tagged "$current")"
    echo "CRON_TAGGED_BEFORE=$tagged"
    if [ "$tagged" = "0" ]; then
      echo "CRON_ENTRY=absent"
      exit 0
    fi
    backup_crontab
    untagged "$current" > "$next"
    crontab "$next"
    verify_or_restore 0
    echo "CRON_OTHER_ENTRIES_PRESERVED=$(grep -cvE "$tag_re" "$current" || true)"
    echo "CRON_ENTRY=removed"
    ;;
  wait)
    limit="${2:-}"
    [[ "$limit" =~ ^[0-9]{1,4}$ ]] || refuse "wait needs a number of seconds"
    deadline=$((SECONDS + limit))
    while :; do
      running="$(ps -u "$(id -u)" -o args= 2> /dev/null | awk '/cron\/video-worker\.php/ { n++ } END { print n + 0 }')"
      if [ "$running" = "0" ]; then
        echo "VIDEO_WORKER_RUNNING=0"
        exit 0
      fi
      if [ "$SECONDS" -ge "$deadline" ]; then
        echo "VIDEO_WORKER_RUNNING=$running"
        exit 1
      fi
      sleep 5
    done
    ;;
  *)
    refuse "usage: video-cron.sh install <target> | remove | wait <seconds>"
    ;;
esac
