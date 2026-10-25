#!/usr/bin/env bash
# Runs ops/video-cron.sh (install, remove, wait) against fake crontab, stat, and ps commands,
# and checks how .github/workflows/ai-rateb-sa.yml wires it.
# Usage: bash tests/cron-install.sh (from ai-rateb-sa-source).
set -u
src="$(cd "$(dirname "$0")/.." && pwd)"
script="$src/ops/video-cron.sh"
workflow="$(cd "$src/.." && pwd)/.github/workflows/ai-rateb-sa.yml"
root="$(mktemp -d)"
root="$(cd "$root" && pwd -P)"
me="$(id -un)"
pass=0; fail=0
ok() { if eval "$2"; then echo "PASS $1"; pass=$((pass+1)); else echo "FAIL $1"; fail=$((fail+1)); fi; }

mkdir -p "$root/bin"
real_stat="$(command -v stat)"
cat > "$root/bin/crontab" << 'EOF'
#!/usr/bin/env bash
store="$FAKE_CRON"
if [ "${1:-}" = "-l" ]; then
  if [ -n "${FAKE_CRON_FAIL:-}" ]; then echo "crontab: permission denied" >&2; exit 1; fi
  if [ ! -f "$store" ]; then echo "no crontab for u" >&2; exit 1; fi
  cat "$store"; exit 0
fi
echo write >> "$store.writes"
if [ -n "${FAKE_CRON_CORRUPT:-}" ] && [ -f "$FAKE_CRON_CORRUPT" ]; then
  rm -f "$FAKE_CRON_CORRUPT"
  tail -n +2 "$1" > "$store"
  exit 0
fi
cp "$1" "$store"
EOF
cat > "$root/bin/stat" << EOF
#!/usr/bin/env bash
if [ -n "\${FAKE_STAT_UID:-}" ] && [ "\$1" = "-c" ] && [ "\$2" = "%u" ] && [[ "\$3" == *"\${FAKE_STAT_PATH:-}" ]]; then echo "\$FAKE_STAT_UID"; exit 0; fi
exec "$real_stat" "\$@"
EOF
cat > "$root/bin/ps" << 'EOF'
#!/usr/bin/env bash
cat "$FAKE_PS" 2>/dev/null || true
EOF
chmod +x "$root/bin/crontab" "$root/bin/stat" "$root/bin/ps"

home="$root/home/$me"
target="$home/domains/ai.rateb.sa"
mkdir -p "$target/cron"
touch "$target/cron/video-worker.php"
export PATH="$root/bin:$PATH" HOME="$home"
run() { bash "$script" "$@"; }
tagline="* * * * * [ ! -e $home/.rateb-ai/video-worker.hold ] && /usr/local/bin/php $target/cron/video-worker.php --log=$home/.rateb-ai/video-worker.log >> $home/.rateb-ai/video-worker.log 2>&1 # rateb-ai-video-worker"
others="$(printf 'MAILTO=""\n0 3 * * * /usr/local/bin/php /home/u/domains/rateb.sa/public_html/cron/daily.php\n*/5 * * * * echo "# rateb-ai-video-worker is mentioned here" > /dev/null\n*/5 * * * * /usr/bin/true')"

# Install.
export FAKE_CRON="$root/cron1"
printf '%s\n* * * * * old worker line # rateb-ai-video-worker\n' "$others" > "$FAKE_CRON"
run install "$target" > "$root/out1" 2>&1; code=$?
ok "install: succeeds over an existing crontab" '[ $code -eq 0 ] && grep -qx "CRON_ENTRY=installed" "$root/out1"'
ok "install: exactly one tagged line" '[ "$(grep -cE "# rateb-ai-video-worker$" "$FAKE_CRON")" = 1 ]'
ok "install: the tagged line runs the worker for this target" 'grep -qxF "$tagline" "$FAKE_CRON"'
ok "install: other entries kept unchanged and in order, including a line that only mentions the tag" '[ "$(grep -vE "# rateb-ai-video-worker$" "$FAKE_CRON")" = "$others" ]'
ok "install: backup of the previous crontab written with mode 600" 'b="$(sed -n "s/^CRON_BACKUP=//p" "$root/out1")"; [ -f "$b" ] && [ "$(stat -c %a "$b")" = 600 ] && grep -q "old worker line" "$b"'
ok "install: reports the preserved count" 'grep -qx "CRON_OTHER_ENTRIES_PRESERVED=4" "$root/out1"'
run install "$target" > /dev/null 2>&1
ok "install: rerun is idempotent" '[ "$(grep -cE "# rateb-ai-video-worker$" "$FAKE_CRON")" = 1 ] && [ "$(wc -l < "$FAKE_CRON")" -eq 5 ]'

export FAKE_CRON="$root/cron2"
run install "$target" > "$root/out2" 2>&1; code=$?
ok "install: no crontab yet installs a single entry" '[ $code -eq 0 ] && [ "$(cat "$FAKE_CRON")" = "$tagline" ]'

export FAKE_CRON="$root/cron3" FAKE_CRON_FAIL=1
printf '0 1 * * * keep-me\n' > "$FAKE_CRON"
run install "$target" > "$root/out3" 2>&1; code=$?
ok "install: unreadable crontab aborts without writing" '[ $code -ne 0 ] && grep -q "could not read" "$root/out3" && [ "$(cat "$FAKE_CRON")" = "0 1 * * * keep-me" ] && [ ! -f "$FAKE_CRON.writes" ]'
unset FAKE_CRON_FAIL

export FAKE_CRON="$root/cron4" FAKE_CRON_CORRUPT="$root/corrupt-once"
printf '0 1 * * * keep-me\n0 2 * * * keep-me-too\n' > "$FAKE_CRON"
touch "$FAKE_CRON_CORRUPT"
run install "$target" > "$root/out4" 2>&1; code=$?
ok "install: a write that drops another entry is detected and the backup restored" '[ $code -ne 0 ] && grep -q "previous crontab was restored" "$root/out4" && [ "$(cat "$FAKE_CRON")" = "$(printf "0 1 * * * keep-me\n0 2 * * * keep-me-too")" ]'
unset FAKE_CRON_CORRUPT

# Install target and ownership checks.
export FAKE_CRON="$root/cron5"
refused() {
  printf '0 1 * * * keep-me\n' > "$FAKE_CRON"
  run install "$1" > "$root/out-refused" 2>&1; local c=$?
  [ $c -ne 0 ] && grep -q "^CRON=refused" "$root/out-refused" && [ "$(cat "$FAKE_CRON")" = "0 1 * * * keep-me" ]
}
ok "install: a rateb.sa target is refused" 'refused "$home/domains/rateb.sa/public_html"'
other="$root/home/someone-else/domains/ai.rateb.sa"
mkdir -p "$other/cron"; touch "$other/cron/video-worker.php"
ok "install: ai.rateb.sa in another account's home is refused" 'refused "$other"'
ok "install: a path with .. is refused" 'refused "$target/../ai.rateb.sa"'
ln -s "$other" "$home/domains/ai.rateb.sa/public_html"
ok "install: a symlinked public_html is refused" 'refused "$target/public_html"'
rm -rf "$home/domains/ai.rateb.sa/public_html"
ok "install: a relative target is refused" 'refused "domains/ai.rateb.sa"'
export FAKE_STAT_UID=4242 FAKE_STAT_PATH="/domains/ai.rateb.sa"
ok "install: a target owned by another account is refused" 'refused "$target" && grep -q "not owned by the deploying account" "$root/out-refused"'
export FAKE_STAT_PATH="/cron/video-worker.php"
ok "install: a worker file owned by another account is refused" 'refused "$target"'
unset FAKE_STAT_UID FAKE_STAT_PATH

# Remove (worker-off).
export FAKE_CRON="$root/cron6"
printf '%s\n%s\n' "$others" "$tagline" > "$FAKE_CRON"
run remove > "$root/out6" 2>&1; code=$?
ok "remove: deletes only the tagged entry" '[ $code -eq 0 ] && grep -qx "CRON_ENTRY=removed" "$root/out6" && [ "$(cat "$FAKE_CRON")" = "$others" ]'
ok "remove: keeps the line that only mentions the tag" 'grep -q "is mentioned here" "$FAKE_CRON"'
ok "remove: writes a backup that still has the entry" 'b="$(sed -n "s/^CRON_BACKUP=//p" "$root/out6")"; [ -f "$b" ] && grep -qxF "$tagline" "$b"'
writes="$(wc -l < "$FAKE_CRON.writes")"
run remove > "$root/out6b" 2>&1; code=$?
ok "remove: rerun reports absent and does not rewrite the crontab" '[ $code -eq 0 ] && grep -qx "CRON_ENTRY=absent" "$root/out6b" && [ "$(wc -l < "$FAKE_CRON.writes")" = "$writes" ]'

export FAKE_CRON="$root/cron7"
run remove > "$root/out7" 2>&1; code=$?
ok "remove: no crontab at all is a no-op" '[ $code -eq 0 ] && grep -qx "CRON_ENTRY=absent" "$root/out7" && [ ! -f "$FAKE_CRON" ]'

export FAKE_CRON="$root/cron8"
printf '%s\n' "$tagline" > "$FAKE_CRON"
run remove > "$root/out8" 2>&1; code=$?
ok "remove: a crontab with only the worker entry becomes empty" '[ $code -eq 0 ] && [ ! -s "$FAKE_CRON" ]'

export FAKE_CRON="$root/cron9" FAKE_CRON_FAIL=1
printf '%s\n%s\n' "$others" "$tagline" > "$FAKE_CRON"
run remove > "$root/out9" 2>&1; code=$?
ok "remove: unreadable crontab aborts without writing" '[ $code -ne 0 ] && grep -q "could not read" "$root/out9" && [ ! -f "$FAKE_CRON.writes" ]'
unset FAKE_CRON_FAIL

export FAKE_CRON="$root/cron10" FAKE_CRON_CORRUPT="$root/corrupt-once"
printf '%s\n%s\n' "$others" "$tagline" > "$FAKE_CRON"
touch "$FAKE_CRON_CORRUPT"
run remove > "$root/out10" 2>&1; code=$?
ok "remove: a write that drops another entry is detected and the backup restored" '[ $code -ne 0 ] && grep -q "previous crontab was restored" "$root/out10" && [ "$(cat "$FAKE_CRON")" = "$(printf "%s\n%s" "$others" "$tagline")" ]'
unset FAKE_CRON_CORRUPT

ok "backups: only the five newest are kept" '[ "$(ls -1 "$home/.rateb-ai"/crontab.backup.* | wc -l)" -le 5 ]'

# Wait for running passes.
export FAKE_PS="$root/ps"
printf 'bash\n/usr/local/bin/php /home/u/domains/rateb.sa/public_html/cron/daily.php\n' > "$FAKE_PS"
run wait 30 > "$root/out11" 2>&1; code=$?
ok "wait: returns at once when no worker pass is running" '[ $code -eq 0 ] && grep -qx "VIDEO_WORKER_RUNNING=0" "$root/out11"'
printf '/bin/sh -c /usr/local/bin/php %s/cron/video-worker.php --log=x\n/usr/local/bin/php %s/cron/video-worker.php --log=x\n' "$target" "$target" > "$FAKE_PS"
( sleep 3; : > "$FAKE_PS" ) &
started=$SECONDS
run wait 30 > "$root/out12" 2>&1; code=$?
wait
ok "wait: waits until the running pass exits" '[ $code -eq 0 ] && [ $((SECONDS - started)) -ge 3 ] && grep -qx "VIDEO_WORKER_RUNNING=0" "$root/out12"'
printf '/usr/local/bin/php %s/cron/video-worker.php --log=x\n' "$target" > "$FAKE_PS"
run wait 1 > "$root/out13" 2>&1; code=$?
ok "wait: gives up after the limit and reports the running pass" '[ $code -ne 0 ] && grep -qx "VIDEO_WORKER_RUNNING=1" "$root/out13"'
run wait abc > "$root/out14" 2>&1; code=$?
ok "wait: a non-numeric limit is refused" '[ $code -ne 0 ] && grep -q "^CRON=refused" "$root/out14"'
run bogus > "$root/out15" 2>&1; code=$?
ok "an unknown command is refused" '[ $code -ne 0 ] && grep -q "^CRON=refused" "$root/out15"'

# Workflow wiring.
python3 - "$workflow" "$src/ops/deploy-files.txt" > "$root/wiring" 2>&1 << 'PY'
import sys, yaml
doc = yaml.safe_load(open(sys.argv[1], encoding="utf-8"))
on = doc.get("on", doc.get(True))
inputs = on["workflow_dispatch"]["inputs"]
options = inputs["task"]["options"]
jobs = doc["jobs"]
def run_of(job):
    return "\n".join(s["run"] for s in jobs[job]["steps"] if "run" in s)
deploy, off, back = run_of("deploy"), run_of("worker-off"), run_of("rollback")
dsq = " ".join(deploy.split())
order = [l.strip() for l in open(sys.argv[2], encoding="utf-8") if l.strip() and not l.startswith("#")]
def pos(text, needle):
    i = text.find(needle)
    if i < 0:
        raise SystemExit("missing: " + needle)
    return i
def before(a, b):
    return order.index(a) < order.index(b)
checkout = [s for s in jobs["deploy"]["steps"] if s.get("uses", "").startswith("actions/checkout")][0]
checks = {
    "worker-off and rollback are task options": "worker-off" in options and "rollback" in options,
    "worker-off job only runs for that task": jobs["worker-off"]["if"] == "github.event.inputs.task == 'worker-off'",
    "rollback job only runs for that task": jobs["rollback"]["if"] == "github.event.inputs.task == 'rollback'",
    "deploy: the approved baseline defaults to a full commit sha": len(str(inputs["baseline"]["default"])) == 40,
    "deploy: checkout has the full history for the baseline": checkout.get("with", {}).get("fetch-depth") == 0,
    "deploy: trap is registered before anything remote changes": pos(deploy, "trap cleanup EXIT") < pos(deploy, "mkdir -p .rateb-ai"),
    "deploy: the bundle is built before anything remote changes": pos(deploy, "deploy-manifest.py") < pos(deploy, "mkdir -p .rateb-ai"),
    "deploy: preflight runs under cron's environment": pos(deploy, "${CRON_ENV} /usr/local/bin/php '${WORK}/preflight.php' --cron-env") > 0 and "--media-root=" in deploy,
    "deploy: order is preflight, drift check and backup, pause, apply, readiness, cron": pos(deploy, "preflight.php' --cron-env") < pos(deploy, "bash -s -- prepare") < pos(deploy, 'touch "$HOME/.rateb-ai/video-worker.pause"') < pos(deploy, "bash -s -- apply") < pos(deploy, "WORKER_READY=yes") < pos(deploy, "bash -s -- install '${TARGET}'\" < ai-rateb-sa-source/ops/video-cron.sh"),
    "deploy: a failed preflight or drift check exits before the pause file": pos(deploy, "Preflight failed; no application file was changed") < pos(deploy, "the backup could not be verified; no application file was changed") < pos(deploy, "PAUSED=1"),
    "deploy: the hold is marked before files are replaced and released only after cron install": pos(deploy, "HOLD=1\n") < pos(deploy, "bash -s -- apply") and pos(deploy, "bash -s -- install") < pos(deploy, "bash -s -- release '${RUN}' '${BACKUP_ID}' '${RECOVER}'"),
    "deploy: the workflow never removes the hold itself (only the guard's release does)": not any("video-worker.hold" in l and "rm " in l for l in deploy.splitlines()),
    "deploy: the pause is removed after a successful run only once the release succeeded": pos(dsq, "HOLD=(released|absent)") < pos(dsq, "HOLD=0 \"${SSH[@]}\" \"$REMOTE\" 'rm -f \"$HOME/.rateb-ai/video-worker.pause\"'"),
    "deploy: the trap clears the pause only when no hold may be in place and no pass was left running": pos(dsq, 'cleanup() {') < pos(dsq, 'if [ "$HOLD" = "1" ]; then') < pos(dsq, 'elif [ "$PAUSED" = "1" ] && [ "$PAUSE_KEEP" = "1" ]; then echo "VIDEO_WORKER_PAUSE=kept"') < pos(dsq, "elif [ \"$PAUSED\" = \"1\" ]; then \"${SSH[@]}\" \"$REMOTE\" 'rm -f \"$HOME/.rateb-ai/video-worker.pause\"'") < pos(dsq, "trap cleanup EXIT"),
    "deploy: the hold flag is dropped after a failed apply only when the guard cleared its own hold": "if grep -qx 'FILES_CHANGED=0' <<< \"$apply_out\" && grep -qx 'HOLD=cleared' <<< \"$apply_out\"; then HOLD=0" in dsq and [l.strip() for l in deploy.splitlines()].count("HOLD=0") == 3,
    "deploy: a hold reported by prepare stops the deploy before the pause file": pos(deploy, "grep -qx 'HOLD=kept' <<< \"$guard_out\"") < pos(deploy, "PAUSED=1"),
    "deploy: prepare, apply, and release get the recover id": "prepare '${TARGET}' '${RUN}' '${RECOVER}'" in deploy and "apply '${TARGET}' '${RUN}' '${BACKUP_ID}' '${RECOVER}'" in deploy and "recover_backup_id" in inputs,
    "deploy: waits for a running worker pass after the pause and before apply": pos(deploy, 'touch "$HOME/.rateb-ai/video-worker.pause"') < pos(deploy, 'bash -s -- wait 540" < ai-rateb-sa-source/ops/video-cron.sh') < pos(deploy, "HOLD=1\n") < pos(deploy, "bash -s -- apply"),
    "deploy: a pass still running after the wait stops the deploy and keeps the pause": pos(deploy, "bash -s -- wait 540") < pos(deploy, "PAUSE_KEEP=1\n") < pos(deploy, "bash -s -- apply"),
    "deploy: the job timeout leaves room for the 540 second wait": int(jobs["deploy"]["timeout-minutes"]) >= 20,
    "deploy: worker_mode is a choice that defaults to inactive, with active as the only opt-in": inputs.get("worker_mode", {}).get("type") == "choice" and inputs.get("worker_mode", {}).get("default") == "inactive" and inputs.get("worker_mode", {}).get("options") == ["inactive", "active"],
    "deploy: worker_mode reaches the job, an empty value means inactive, and an unknown value stops before connecting": any(s.get("env", {}).get("DEPLOY_WORKER_MODE") == "${{ github.event.inputs.worker_mode }}" for s in jobs["deploy"]["steps"]) and 'WORKER_MODE="${DEPLOY_WORKER_MODE:-inactive}"' in deploy and pos(deploy, "worker_mode must be inactive or active") < pos(deploy, 'prefix="ai-deploy-"') < pos(deploy, "mkdir -p .rateb-ai"),
    "deploy: active installs the worker entry and inactive removes only the tagged entry, each in one place": 'if [ "$WORKER_MODE" = "active" ]; then cron_out="$("${SSH[@]}" "$REMOTE" "bash -s -- install \'${TARGET}\'" < ai-rateb-sa-source/ops/video-cron.sh 2>&1)" else cron_out="$("${SSH[@]}" "$REMOTE" "bash -s -- remove" < ai-rateb-sa-source/ops/video-cron.sh 2>&1)" fi' in dsq and deploy.count("bash -s -- install") == 1 and deploy.count("bash -s -- remove") == 1,
    "deploy: in both modes the cron entry is set after readiness and checked before the release": pos(deploy, "WORKER_READY=yes") < pos(deploy, "bash -s -- install") < pos(deploy, "bash -s -- remove") < pos(deploy, "grep -qxE \"$cron_want\" <<< \"$cron_out\"") < pos(deploy, "bash -s -- release '${RUN}' '${BACKUP_ID}' '${RECOVER}'") and "cron_want='CRON_ENTRY=installed'" in deploy and "cron_want='CRON_ENTRY=(removed|absent)'" in deploy,
    "deploy: a failed cron step exits with the hold flag still set, and the run reports the mode": pos(dsq, "could not be set for worker_mode=${WORKER_MODE}; the hold and pause stay.\" exit 1") < pos(dsq, "HOLD=0 \"${SSH[@]}\" \"$REMOTE\" 'rm -f \"$HOME/.rateb-ai/video-worker.pause\"'") and 'echo "VIDEO_WORKER=${WORKER_MODE}"' in deploy,
    "rollback: a hold already in place is not overwritten": '[ -e \\"\\$HOME/.rateb-ai/video-worker.hold\\" ] ||' in back and 'printf "rollback\\n" >' not in back,
    "rollback: a symlinked hold is refused before the pause, and the hold is written with noclobber": pos(back, r'if [ -L \"\$HOME/.rateb-ai/video-worker.hold\" ]; then echo HOLD=symlink; exit 1; fi') < pos(back, r'touch \"\$HOME/.rateb-ai/video-worker.pause\"') < pos(back, r"(set -C && printf 'restore=%s\n'") < pos(back, "HELD=1\n"),
    "deploy: the readiness check runs under cron's environment": "${CRON_ENV} /usr/local/bin/php '${TARGET}/cron/video-worker.php' --check" in deploy,
    "deploy: files are no longer copied one by one into the web root": 'for rel in "${files[@]}"' not in deploy and ":${TARGET}/config/" not in deploy,
    "deploy: no crontab edit is embedded in the workflow": "crontab -l" not in deploy and "crontab \"" not in deploy and "crontab -l" not in back,
    "deploy order: runtime.php before engine.php": before("app/video/runtime.php", "app/video/engine.php"),
    "deploy order: media library before engine.php": before("app/media/library.php", "app/video/engine.php"),
    "deploy order: engine.php before the endpoints that load it": before("app/video/engine.php", "app/ai/video.php") and before("app/video/engine.php", "app/ai/video-status.php"),
    "deploy order: db.php and plans.php before bootstrap.php": before("app/db.php", "app/bootstrap.php") and before("app/plans.php", "app/bootstrap.php"),
    "deploy order: bootstrap.php before pages and endpoints": all(before("app/bootstrap.php", rel) for rel in order if rel.endswith(".php") and not rel.startswith(("app/bootstrap.php", "app/video/", "app/media/library", "app/brand/", "app/db", "app/plans", "app/schema", "app/nav", "config/"))),
    "deploy order: cron/.htaccess before the cron worker": before("cron/.htaccess", "cron/video-worker.php"),
    "worker-off: pause is set before the entry is removed": pos(off, 'touch "$HOME/.rateb-ai/video-worker.pause"') < pos(off, 'bash -s -- remove" < ai-rateb-sa-source/ops/video-cron.sh'),
    "worker-off: waits for a running pass before reporting stopped": pos(off, "bash -s -- wait 540") < pos(off, "VIDEO_WORKER=stopped"),
    "worker-off: uploads nothing and leaves a hold in place": "scp " not in off and "rm -f \"$HOME/.rateb-ai/video-worker.hold" not in off,
    "rollback: hold, cron removal, and wait come before the restore": pos(back, "video-worker.hold") < pos(back, "bash -s -- remove") < pos(back, "bash -s -- wait 540") < pos(back, "bash -s -- restore"),
    "rollback: the hold is released only after a verified restore": pos(back, "ROLLBACK_OK=yes") < pos(back, 'rm -f "$HOME/.rateb-ai/video-worker.hold"'),
}
for name, good in checks.items():
    print(("PASS " if good else "FAIL ") + name)
PY
code=$?
while IFS= read -r line; do
  echo "$line"
  case "$line" in PASS*) pass=$((pass+1)) ;; FAIL*|missing*|Traceback*) fail=$((fail+1)) ;; esac
done < "$root/wiring"
[ $code -eq 0 ] || { echo "FAIL workflow wiring check did not run"; fail=$((fail+1)); }

rm -rf "$root"
echo "SUMMARY pass=$pass fail=$fail"
[ $fail -eq 0 ]
