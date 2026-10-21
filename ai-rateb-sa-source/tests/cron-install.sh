#!/usr/bin/env bash
# Runs the crontab installer embedded in .github/workflows/ai-rateb-sa.yml against a fake
# crontab command. Usage: bash tests/cron-install.sh (from ai-rateb-sa-source).
set -u
workflow="$(cd "$(dirname "$0")/../.." && pwd)/.github/workflows/ai-rateb-sa.yml"
root="$(mktemp -d)"
python3 - "$workflow" "$root/cron-install.sh" << 'PY'
import re, sys, yaml
jobs = yaml.safe_load(open(sys.argv[1], encoding="utf-8"))["jobs"]
run = [s for s in jobs["deploy"]["steps"] if "run" in s][0]["run"]
script = re.search(r"<< 'SH'\n(.*?)\nSH\n", run, re.S)
if not script:
    raise SystemExit("installer not found in the deploy job")
open(sys.argv[2], "w", encoding="utf-8", newline="\n").write(script.group(1))
PY
mkdir -p "$root/bin" "$root/home" "$root/home/u/domains/ai.rateb.sa/cron"
touch "$root/home/u/domains/ai.rateb.sa/cron/video-worker.php"
cat > "$root/bin/crontab" << 'EOF'
#!/usr/bin/env bash
store="$FAKE_CRON"
if [ "${1:-}" = "-l" ]; then
  if [ -n "${FAKE_CRON_FAIL:-}" ]; then echo "crontab: permission denied" >&2; exit 1; fi
  if [ ! -f "$store" ]; then echo "no crontab for u" >&2; exit 1; fi
  cat "$store"; exit 0
fi
cp "$1" "$store"
EOF
chmod +x "$root/bin/crontab"
sed 's#/home/\*/domains#'"$root"'/home/*/domains#g' "$root/cron-install.sh" > "$root/install.sh"
target="$root/home/u/domains/ai.rateb.sa"
export PATH="$root/bin:$PATH" HOME="$root/home/u"
pass=0; fail=0
ok() { if eval "$2"; then echo "PASS $1"; pass=$((pass+1)); else echo "FAIL $1"; fail=$((fail+1)); fi; }

export FAKE_CRON="$root/cron1"
printf 'MAILTO=""\n0 3 * * * /usr/local/bin/php /home/u/domains/rateb.sa/public_html/cron/daily.php\n* * * * * old worker line # rateb-ai-video-worker\n*/5 * * * * /usr/bin/true\n' > "$FAKE_CRON"
bash "$root/install.sh" "$target" > "$root/out1" 2>&1; code=$?
ok "existing crontab: install succeeds" '[ $code -eq 0 ]'
ok "existing crontab: one tagged line" '[ "$(grep -c "# rateb-ai-video-worker" "$FAKE_CRON")" = 1 ]'
ok "existing crontab: tagged line points at the worker" 'grep -q "^\* \* \* \* \* /usr/local/bin/php $target/cron/video-worker.php --log=$HOME/.rateb-ai/video-worker.log >> $HOME/.rateb-ai/video-worker.log 2>&1 # rateb-ai-video-worker$" "$FAKE_CRON"'
ok "existing crontab: other entries kept in order" '[ "$(grep -v rateb-ai-video-worker "$FAKE_CRON")" = "$(printf "MAILTO=\"\"\n0 3 * * * /usr/local/bin/php /home/u/domains/rateb.sa/public_html/cron/daily.php\n*/5 * * * * /usr/bin/true")" ]'
ok "existing crontab: backup written" 'ls "$HOME/.rateb-ai"/crontab.backup.* > /dev/null 2>&1'
ok "existing crontab: reports preserved count" 'grep -qx "CRON_OTHER_ENTRIES_PRESERVED=3" "$root/out1"'
bash "$root/install.sh" "$target" > /dev/null 2>&1
ok "rerun is idempotent" '[ "$(grep -c "# rateb-ai-video-worker" "$FAKE_CRON")" = 1 ] && [ "$(wc -l < "$FAKE_CRON")" -eq 4 ]'

export FAKE_CRON="$root/cron2"
bash "$root/install.sh" "$target" > "$root/out2" 2>&1; code=$?
ok "no crontab yet: installs a single entry" '[ $code -eq 0 ] && [ "$(wc -l < "$FAKE_CRON")" -eq 1 ]'

export FAKE_CRON="$root/cron3" FAKE_CRON_FAIL=1
printf '0 1 * * * keep-me\n' > "$FAKE_CRON"
bash "$root/install.sh" "$target" > "$root/out3" 2>&1; code=$?
ok "unreadable crontab: aborts" '[ $code -ne 0 ] && grep -q "could not read" "$root/out3"'
ok "unreadable crontab: nothing changed" '[ "$(cat "$FAKE_CRON")" = "0 1 * * * keep-me" ]'
unset FAKE_CRON_FAIL

bash "$root/install.sh" "/home/u/domains/rateb.sa/public_html" > "$root/out4" 2>&1; code=$?
ok "a non ai.rateb.sa target is refused" '[ $code -ne 0 ] && grep -q "refused" "$root/out4"'

rm -rf "$root"
echo "SUMMARY pass=$pass fail=$fail"
[ $fail -eq 0 ]
