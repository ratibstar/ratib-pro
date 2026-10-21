#!/usr/bin/env bash
# Runs the real deploy, worker-off, and rollback steps of .github/workflows/ai-rateb-sa.yml
# with fake ssh, scp, and curl commands that act on a sandboxed "server" home on this machine.
# The server starts as the approved baseline commit (the workflow's default baseline input).
# The bundle builder, preflight, drift check, backup, file swap, rollback, readiness check
# (against a disposable MySQL database and the local ffmpeg), and cron script all run for real;
# only the network transport is faked, and crontab is a fake that writes to a file, so the
# machine's own crontab is never touched.
#
# RUN (from ai-rateb-sa-source, in a checkout with full history):
#   RATEB_TEST_DB_HOST=127.0.0.1 RATEB_TEST_DB_PORT=3306 RATEB_TEST_DB_USER=... \
#   RATEB_TEST_DB_PASS=... RATEB_DEPLOY_TEST_DB_NAME=rateb_ai_deploy_test bash tests/deploy-sim.sh
# Needs ffmpeg, PHP with pdo_mysql/gd/posix, python3 with yaml, git, and a writable
# /home/admin/.campaign-media (the application's fixed media root).
# Exit codes: 0 all cases passed, 1 a case failed, 2 refused (nothing proven).
set -u
src="$(cd "$(dirname "$0")/.." && pwd)"
repo="$(cd "$src/.." && pwd)"
workflow="$repo/.github/workflows/ai-rateb-sa.yml"
refuse() { echo "REFUSED: $1"; exit 2; }
for v in RATEB_TEST_DB_HOST RATEB_TEST_DB_PORT RATEB_TEST_DB_USER RATEB_TEST_DB_PASS RATEB_DEPLOY_TEST_DB_NAME; do
  [ -n "${!v:-}" ] || refuse "$v is not set"
done
[[ "$RATEB_DEPLOY_TEST_DB_NAME" =~ ^[A-Za-z0-9_]+_test$ ]] || refuse "the deploy test database name must end in _test"
case "$RATEB_TEST_DB_HOST" in 127.0.0.1|localhost|::1) ;; *) refuse "the test database host is not local" ;; esac
[[ "$RATEB_TEST_DB_PASS" =~ ^[A-Za-z0-9]+$ ]] || refuse "use an alphanumeric disposable test password"
[ -d /home/admin/.campaign-media ] && [ -w /home/admin/.campaign-media ] || refuse "/home/admin/.campaign-media must exist and be writable"
command -v ffmpeg > /dev/null || refuse "ffmpeg is required"
python3 -c 'import yaml' 2> /dev/null || refuse "python3 yaml is required"
BASELINE="${RATEB_DEPLOY_BASELINE:-$(python3 -c 'import sys, yaml; d = yaml.safe_load(open(sys.argv[1], encoding="utf-8")); print((d.get("on") or d.get(True))["workflow_dispatch"]["inputs"]["baseline"]["default"])' "$workflow")}"
git -C "$repo" cat-file -e "${BASELINE}^{commit}" 2> /dev/null || refuse "the baseline commit $BASELINE is not in this checkout (fetch the full history)"

sim="$(mktemp -d)"
sim="$(cd "$sim" && pwd -P)"
me="$(id -un)"
mkdir -p "$sim/bin" "$sim/remote-bin" "$sim/runner"
pass=0; fail=0
ok() { if eval "$2"; then echo "PASS $1"; pass=$((pass+1)); else echo "FAIL $1"; fail=$((fail+1)); fi; }
SIM_CLIENT_ID="sim-client-5d1c0e.apps.googleusercontent.com"
PROD_CLIENT_ID="prod-client-7f3a9b.apps.googleusercontent.com"

cat > "$sim/seed.php" << 'PHP'
<?php
$pdo = new PDO('mysql:host=' . getenv('RATEB_TEST_DB_HOST') . ';port=' . getenv('RATEB_TEST_DB_PORT') . ';dbname=' . getenv('RATEB_DEPLOY_TEST_DB_NAME') . ';charset=utf8mb4', getenv('RATEB_TEST_DB_USER'), getenv('RATEB_TEST_DB_PASS'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
if ($tables !== [] && !in_array('rateb_deploy_sim_marker', $tables, true)) {
    fwrite(STDERR, "REFUSED: the deploy test database has tables this test did not create\n");
    exit(2);
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach ($tables as $table) {
    $pdo->exec('DROP TABLE `' . str_replace('`', '', (string) $table) . '`');
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
if (($argv[1] ?? '') === '--drop') {
    exit(0);
}
$pdo->exec('CREATE TABLE rateb_deploy_sim_marker (id INT) ENGINE=InnoDB');
$pdo->exec("CREATE TABLE users (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, name VARCHAR(120) NOT NULL DEFAULT '', email VARCHAR(190) NOT NULL, password_hash VARCHAR(255) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY email (email)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$pdo->exec("CREATE TABLE campaigns (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, user_id BIGINT UNSIGNED NOT NULL, title VARCHAR(255) NOT NULL DEFAULT '', product_name VARCHAR(255) NOT NULL DEFAULT '', description TEXT NULL, price VARCHAR(64) NULL, target_customer VARCHAR(255) NULL, status VARCHAR(32) NOT NULL DEFAULT 'draft', created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY user_id (user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$pdo->exec("CREATE TABLE campaign_media (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, campaign_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL, kind VARCHAR(16) NOT NULL, source VARCHAR(16) NOT NULL, original_name VARCHAR(255) NOT NULL, stored_name VARCHAR(64) NOT NULL, mime VARCHAR(64) NOT NULL, bytes INT UNSIGNED NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id), KEY campaign_user (campaign_id, user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
PHP
php "$sim/seed.php" || refuse "could not prepare the deploy test database"

# The fake ssh maps the production paths into the sandbox and gives the bare cron PATH the
# fake crontab (preflight only checks that crontab exists).
cat > "$sim/bin/ssh" << 'EOF'
#!/usr/bin/env bash
while [ $# -gt 0 ]; do
  case "$1" in -i|-p|-o) shift 2 ;; -*) shift ;; *) break ;; esac
done
shift
cmd="$*"
cmd="${cmd//\/home\/admin/$SIM_HOME}"
cmd="${cmd//\/usr\/local\/bin\/php/$SIM_PHP}"
cmd="${cmd//PATH=\/usr\/bin:\/bin/PATH=$SIM_CRON_PATH}"
printf 'SSH %s\n' "$cmd" >> "$SIM_LOG"
if [ -n "${SIM_FAIL_CHECK:-}" ] && [[ "$cmd" == *"cron/video-worker.php' --check"* ]]; then
  echo "WORKER_READY=no"
  exit 1
fi
cd "$SIM_HOME" || exit 255
HOME="$SIM_HOME" PATH="$SIM_REMOTE_PATH" exec bash -c "$cmd"
EOF
cat > "$sim/bin/scp" << 'EOF'
#!/usr/bin/env bash
while [ $# -gt 0 ]; do
  case "$1" in -i|-P|-o) shift 2 ;; -*) shift ;; *) break ;; esac
done
src="$1"
dest="${2#*:}"
dest="${dest//\/home\/admin/$SIM_HOME}"
case "$dest" in /*) ;; *) dest="$SIM_HOME/$dest" ;; esac
printf 'SCP %s\n' "$dest" >> "$SIM_LOG"
cp "$src" "$dest" || exit 1
if [ -n "${SIM_CORRUPT:-}" ] && [[ "$dest" == *"/$SIM_CORRUPT" ]]; then
  printf 'changed in transit' >> "$dest"
fi
EOF
cat > "$sim/bin/curl" << 'EOF'
#!/usr/bin/env bash
out=/dev/null
url=""
while [ $# -gt 0 ]; do
  case "$1" in -o) out="$2"; shift 2 ;; -w|--max-time) shift 2 ;; -*) shift ;; *) url="$1"; shift ;; esac
done
printf 'CURL %s\n' "$url" >> "$SIM_LOG"
case "$url" in
  */cron/video-worker.php) printf 'denied' > "$out"; printf '%s' "${SIM_WORKER_HTTP:-403}" ;;
  */opcache-*.php) printf 'ok' > "$out"; printf '200' ;;
  *) printf '404' ;;
esac
EOF
cat > "$sim/remote-bin/crontab" << 'EOF'
#!/usr/bin/env bash
if [ "${1:-}" = "-l" ]; then
  if [ -n "${FAKE_CRON_FAIL:-}" ]; then echo "crontab: permission denied" >&2; exit 1; fi
  if [ ! -f "$FAKE_CRON" ]; then echo "no crontab for sim" >&2; exit 1; fi
  cat "$FAKE_CRON"; exit 0
fi
cp "$1" "$FAKE_CRON"
EOF
chmod +x "$sim/bin/"* "$sim/remote-bin/"*
real_ffmpeg="$(command -v ffmpeg)"

python3 - "$workflow" "$sim" << 'PY'
import sys, yaml
jobs = yaml.safe_load(open(sys.argv[1], encoding="utf-8"))["jobs"]
for job, name in (("deploy", "deploy.sh"), ("worker-off", "worker-off.sh"), ("rollback", "rollback.sh")):
    run = [s for s in jobs[job]["steps"] if "run" in s][0]["run"]
    open(sys.argv[2] + "/" + name, "w", encoding="utf-8", newline="\n").write(run)
PY

git -C "$repo" archive -o "$sim/baseline.tar" "$BASELINE" ai-rateb-sa-source || refuse "could not export the baseline commit"
DEFAULT_REMOTE_PATH="$sim/remote-bin:$PATH"
new_server() {
  SIM_HOME="$sim/$1/home/$me"
  mkdir -p "$SIM_HOME/domains/ai.rateb.sa" "$SIM_HOME/private" "$SIM_HOME/.campaign-media"
  SIM_HOME="$(cd "$SIM_HOME" && pwd -P)"
  target="$SIM_HOME/domains/ai.rateb.sa"
  tar -xf "$sim/baseline.tar" -C "$target" --strip-components=1 --exclude=ai-rateb-sa-source/tests --exclude=ai-rateb-sa-source/ops
  cat > "$target/config/database.php" << PHPCFG
<?php
return ['host' => '${RATEB_TEST_DB_HOST};port=${RATEB_TEST_DB_PORT}', 'database' => '${RATEB_DEPLOY_TEST_DB_NAME}', 'username' => '${RATEB_TEST_DB_USER}', 'password' => getenv('RATEB_AI_DB_PASSWORD') ?: '', 'charset' => 'utf8mb4'];
PHPCFG
  printf "<?php\nputenv('RATEB_AI_DB_PASSWORD=%s');\nputenv('RUNWAY_API_KEY=sim-provider-key');\n" "$RATEB_TEST_DB_PASS" > "$SIM_HOME/private/env.php"
  chmod 600 "$SIM_HOME/private/env.php"
  printf 'auto_prepend_file = "%s/private/env.php"\n' "$SIM_HOME" > "$target/.user.ini"
  FAKE_CRON="$SIM_HOME/.fake-crontab"
  SIM_REMOTE_PATH="$DEFAULT_REMOTE_PATH"
  SIM_CRON_PATH="$sim/remote-bin:/usr/bin:/bin"
  export SIM_HOME FAKE_CRON SIM_REMOTE_PATH SIM_CRON_PATH
  unset SIM_CORRUPT SIM_FAIL_CHECK SIM_WORKER_HTTP FAKE_CRON_FAIL
  SIM_ACCEPT=false
  SIM_BACKUP_ID=""
  SIM_ALLOW=false
  : > "$sim/log"
}
tree_hash() { (cd "$1" && find . -type f -print0 | sort -z | xargs -0 sha256sum) | sha256sum | awk '{print $1}'; }
run_id=4241
run_job() {
  run_id=$((run_id + 1))
  rm -f "$sim/runner/"* 2> /dev/null
  (
    cd "$repo" && env GITHUB_SHA=simulated GITHUB_RUN_ID="$run_id" RUNNER_TEMP="$sim/runner" \
      DEPLOY_SSH_HOST=sim.invalid DEPLOY_SSH_USER=admin DEPLOY_SSH_PORT=22 \
      DEPLOY_SSH_PRIVATE_KEY='-----BEGIN SIM KEY-----\nnot-a-real-key\n-----END SIM KEY-----' \
      DEPLOY_BASELINE="$BASELINE" DEPLOY_ACCEPT_GENERATED="$SIM_ACCEPT" RATEB_GOOGLE_CLIENT_ID="$SIM_CLIENT_ID" \
      ROLLBACK_BACKUP_ID="$SIM_BACKUP_ID" ROLLBACK_ALLOW_MISMATCH="$SIM_ALLOW" \
      PATH="$sim/bin:$PATH" SIM_LOG="$sim/log" SIM_PHP="$(command -v php)" \
      bash --noprofile --norc -eo pipefail "$sim/$1"
  ) > "$2" 2>&1
}
key_removed() { local f; f="$(cat "$sim/runner/$1" 2> /dev/null)"; [ -n "$f" ] && [ ! -e "$f" ]; }
backup_id_of() { sed -n 's/^ROLLBACK_BACKUP_ID=//p' "$1" | tail -n 1; }
no_backups() { [ ! -d "$SIM_HOME/.rateb-ai/backups" ] || [ -z "$(ls -A "$SIM_HOME/.rateb-ai/backups")" ]; }
q="'"
in_order() {
  python3 - "$@" << 'PY'
import sys
log = open(sys.argv[1], encoding="utf-8").read()
at = [log.find(s) for s in sys.argv[2:]]
sys.exit(0 if -1 not in at and at == sorted(at) else 1)
PY
}
# Nothing in the web root, the pause or hold file, the backups, or the crontab changed, and
# the private work folder and the key were removed.
untouched_after_abort() {
  [ "$(tree_hash "$target")" = "$before" ] \
    && ! grep -q "^SCP $target/" "$sim/log" \
    && ! grep -q 'touch "$HOME/.rateb-ai/video-worker.pause"' "$sim/log" \
    && ! grep -q 'bash -s -- apply' "$sim/log" \
    && [ ! -e "$SIM_HOME/.rateb-ai/video-worker.pause" ] \
    && [ ! -e "$SIM_HOME/.rateb-ai/video-worker.hold" ] \
    && [ ! -e "$FAKE_CRON" ] \
    && [ ! -e "$SIM_HOME/.rateb-ai/deploy-$run_id" ] \
    && no_backups \
    && key_removed ai-deploy-key-path
}
held() { [ -e "$SIM_HOME/.rateb-ai/video-worker.hold" ] && [ -e "$SIM_HOME/.rateb-ai/video-worker.pause" ] && grep -qx "VIDEO_WORKER_HOLD=kept" "$1"; }
# The worker an earlier deploy installed, started by cron with this account's state folder.
worker_blocked() {
  local log="$SIM_HOME/.rateb-ai/video-worker.log"
  rm -f "$log" "$SIM_HOME/.rateb-ai/video-worker.pause"
  HOME="$SIM_HOME" php "$src/cron/video-worker.php" --log="$log" > /dev/null 2>&1 && [ ! -s "$log" ]
}

# A-E. Preflight failures stop the deploy before the drift check, the backup, or any change.
new_server a
printf '<?php\n// edited on the server\n' > "$target/app/bootstrap.php"
before="$(tree_hash "$target")"
run_job deploy.sh "$sim/a.out"; code=$?
ok "preflight abort (bootstrap): the deploy fails" '[ $code -ne 0 ] && grep -q "^PREFLIGHT_BOOTSTRAP=FAILED unexpected sha256=" "$sim/a.out" && grep -q "Preflight failed; no application file was changed" "$sim/a.out"'
ok "preflight abort (bootstrap): nothing changed; temp files and key removed" 'untouched_after_abort'

new_server b
chmod 660 "$SIM_HOME/private/env.php"
before="$(tree_hash "$target")"
run_job deploy.sh "$sim/b.out"; code=$?
ok "preflight abort (prepend mode): the deploy fails and names the mode" '[ $code -ne 0 ] && grep -q "^PREFLIGHT_PREPEND=FAILED the prepend file is group- or world-writable mode=0660" "$sim/b.out"'
ok "preflight abort (prepend mode): nothing changed" 'untouched_after_abort'

new_server c
mkdir -p "$SIM_HOME/bin"
cat > "$SIM_HOME/bin/ffmpeg" << EOF
#!/bin/sh
case " \$* " in
  *" -encoders "*) printf ' A....D aac   AAC\n'; exit 0 ;;
esac
exec "$real_ffmpeg" "\$@"
EOF
chmod +x "$SIM_HOME/bin/ffmpeg"
before="$(tree_hash "$target")"
run_job deploy.sh "$sim/c.out"; code=$?
ok "preflight abort (ffmpeg): resolves the account's ffmpeg by absolute path under cron's environment and names the missing encoder" '[ $code -ne 0 ] && grep -qx "PREFLIGHT_FFMPEG=$SIM_HOME/bin/ffmpeg" "$sim/c.out" && grep -q "^PREFLIGHT_FFMPEG_RENDER=FAILED missing libx264" "$sim/c.out"'
ok "preflight abort (ffmpeg): nothing changed" 'untouched_after_abort'

new_server d
sed -i "s/RATEB_AI_DB_PASSWORD=[A-Za-z0-9]*/RATEB_AI_DB_PASSWORD=wrongpassword/" "$SIM_HOME/private/env.php"
before="$(tree_hash "$target")"
run_job deploy.sh "$sim/d.out"; code=$?
ok "preflight abort (database): the deploy fails with only the error class and code" '[ $code -ne 0 ] && grep -q "^PREFLIGHT_DB=FAILED PDOException code=" "$sim/d.out" && ! grep -q "wrongpassword" "$sim/d.out"'
ok "preflight abort (database): nothing changed" 'untouched_after_abort'

new_server e
rm -rf "$SIM_HOME/.campaign-media"
before="$(tree_hash "$target")"
run_job deploy.sh "$sim/e.out"; code=$?
ok "preflight abort (media root missing): the deploy fails and names MEDIA_ROOT" '[ $code -ne 0 ] && grep -qx "PREFLIGHT_MEDIA_ROOT=FAILED the media root does not exist" "$sim/e.out"'
ok "preflight abort (media root missing): nothing changed" 'untouched_after_abort'

# F-I. The drift check stops the deploy before the backup or any change.
new_server f
printf '\n// hotfix made on the server\n' >> "$target/app/plans.php"
before="$(tree_hash "$target")"
run_job deploy.sh "$sim/f.out"; code=$?
ok "drift abort (edited file): names the file and its unexpected sha256" '[ $code -ne 0 ] && grep -q "^DRIFT=app/plans.php FAILED unexpected content sha256=$(sha256sum "$target/app/plans.php" | cut -c1-64)" "$sim/f.out" && grep -qx "DRIFT_OK=no" "$sim/f.out" && grep -q "no application file was changed" "$sim/f.out"'
ok "drift abort (edited file): other files were checked too" 'grep -qx "DRIFT=app/video/engine.php ok baseline" "$sim/f.out" && grep -qx "DRIFT=app/video/runtime.php ok new" "$sim/f.out"'
ok "drift abort (edited file): nothing changed and no backup was written" 'untouched_after_abort'

new_server g
printf '<?php\n// production-only database helper\n' > "$target/app/db.php"
before="$(tree_hash "$target")"
run_job deploy.sh "$sim/g.out"; code=$?
ok "drift abort (untracked production-only file): the deploy refuses to overwrite it" '[ $code -ne 0 ] && grep -q "^DRIFT=app/db.php FAILED unexpected content" "$sim/g.out"'
ok "drift abort (untracked production-only file): nothing changed" 'untouched_after_abort'

new_server h
printf "<?php\ndeclare(strict_types=1);\nreturn ['client_id' => '%s'];\n" "$PROD_CLIENT_ID" > "$target/config/google.php"
before="$(tree_hash "$target")"
run_job deploy.sh "$sim/h.out"; code=$?
ok "drift abort (generated config differs): refused without printing either value" '[ $code -ne 0 ] && grep -q "^DRIFT=config/google.php FAILED this configuration file differs" "$sim/h.out" && ! grep -q "^DRIFT=config/google.php.*sha256" "$sim/h.out"'
ok "drift abort (generated config differs): nothing changed" 'untouched_after_abort'
SIM_ACCEPT=true
run_job deploy.sh "$sim/h2.out"; code=$?
ok "generated config: accept_generated_config_change lets an intended rotation through and backs up the old copy" '[ $code -eq 0 ] && grep -qx "DRIFT=config/google.php ok accepted" "$sim/h2.out" && grep -qF "$SIM_CLIENT_ID" "$target/config/google.php" && tar -xzOf "$SIM_HOME/.rateb-ai/backups/$(backup_id_of "$sim/h2.out")/files.tar.gz" config/google.php | grep -qF "$PROD_CLIENT_ID"'

new_server i
export SIM_CORRUPT=bundle.tar.gz
before="$(tree_hash "$target")"
run_job deploy.sh "$sim/i.out"; code=$?
ok "bundle changed in transit: refused before the drift check" '[ $code -ne 0 ] && grep -q "^GUARD=error: the uploaded bundle does not match its checksum" "$sim/i.out"'
ok "bundle changed in transit: nothing changed" 'untouched_after_abort'

# J. Replacing files fails midway: the hold and pause stay, cron is untouched, and a rollback
# restores the baseline tree exactly.
new_server j
others="$(printf 'MAILTO=""\n0 3 * * * /usr/local/bin/php /home/admin/domains/rateb.sa/public_html/cron/daily.php')"
printf '%s\n' "$others" > "$FAKE_CRON"
before="$(tree_hash "$target")"
chmod 555 "$target/app/ai"
run_job deploy.sh "$sim/j.out"; code=$?
chmod 755 "$target/app/ai"
ok "partial failure: the deploy fails after some files changed" '[ $code -ne 0 ] && grep -qx "APPLIED=app/video/runtime.php" "$sim/j.out" && grep -q "^GUARD=error: could not replace app/ai/" "$sim/j.out" && grep -qx "HOLD=kept" "$sim/j.out"'
ok "partial failure: the hold and pause stay, and the crontab is unchanged" 'held "$sim/j.out" && [ "$(cat "$FAKE_CRON")" = "$others" ] && ! grep -q "bash -s -- install" "$sim/log"'
ok "partial failure: the error names the backup to roll back to" 'grep -q "run task=rollback with backup_id=$(backup_id_of "$sim/j.out")" "$sim/j.out"'
ok "partial failure: the worker cron entry is not installed and the worker file was not reached" '! grep -q "rateb-ai-video-worker" "$FAKE_CRON" && [ ! -e "$target/cron/video-worker.php" ]'
ok "partial failure: an already installed worker does not run while the hold is set, even with the pause gone" 'worker_blocked'
touch "$SIM_HOME/.rateb-ai/video-worker.pause"
SIM_BACKUP_ID="$(backup_id_of "$sim/j.out")"
run_job rollback.sh "$sim/j2.out"; code=$?
ok "partial failure: rollback restores the baseline tree exactly" '[ $code -eq 0 ] && grep -qx "ROLLBACK_OK=yes" "$sim/j2.out" && [ "$(tree_hash "$target")" = "$before" ]'
ok "partial failure: rollback releases the hold and pause only after the restore" '[ ! -e "$SIM_HOME/.rateb-ai/video-worker.hold" ] && [ ! -e "$SIM_HOME/.rateb-ai/video-worker.pause" ] && grep -qx "VIDEO_WORKER_HOLD=cleared" "$sim/j2.out" && in_order "$sim/log" "bash -s -- remove" "bash -s -- wait 540" "bash -s -- restore"'

# K. Replacing files fails at the first file: the hold is released, nothing changed.
new_server k
before="$(tree_hash "$target")"
chmod 555 "$target/app/video"
run_job deploy.sh "$sim/k.out"; code=$?
chmod 755 "$target/app/video"
ok "failure before any change: the deploy fails with zero files changed" '[ $code -ne 0 ] && grep -qx "FILES_CHANGED=0" "$sim/k.out" && grep -qx "HOLD=cleared" "$sim/k.out" && grep -q "stopped before any file changed" "$sim/k.out"'
ok "failure before any change: the tree, hold, pause, and crontab are untouched" '[ "$(tree_hash "$target")" = "$before" ] && [ ! -e "$SIM_HOME/.rateb-ai/video-worker.hold" ] && [ ! -e "$SIM_HOME/.rateb-ai/video-worker.pause" ] && [ ! -e "$FAKE_CRON" ]'

# L-M. Failures after every file was replaced keep the hold: no cron change.
new_server l
export SIM_WORKER_HTTP=200
run_job deploy.sh "$sim/l.out"; code=$?
ok "worker reachable over HTTP: fails before readiness and cron, and keeps the hold" '[ $code -ne 0 ] && grep -q "answered over HTTP" "$sim/l.out" && ! grep -q "video-worker.php. --check" "$sim/log" && [ ! -e "$FAKE_CRON" ] && held "$sim/l.out"'

new_server m
export SIM_FAIL_CHECK=1
printf '0 4 * * * /usr/bin/true\n' > "$FAKE_CRON"
run_job deploy.sh "$sim/m.out"; code=$?
ok "readiness failure: fails, the crontab is unchanged, and the hold stays" '[ $code -ne 0 ] && grep -q "readiness check failed; the cron entry was not installed" "$sim/m.out" && [ "$(cat "$FAKE_CRON")" = "0 4 * * * /usr/bin/true" ] && held "$sim/m.out"'

# N. A full deploy with the real preflight, drift check, backup, swap, readiness, and cron.
new_server n
printf '%s\n' "$others" > "$FAKE_CRON"
before="$(tree_hash "$target")"
run_job deploy.sh "$sim/n.out"; code=$?
first_backup="$(backup_id_of "$sim/n.out")"
ok "deploy: succeeds" '[ $code -eq 0 ] && grep -qx "APPLY_OK=yes" "$sim/n.out"'
ok "deploy: preflight passed every check under cron's environment" 'grep -qx "PREFLIGHT_OK=yes" "$sim/n.out" && grep -q "^PREFLIGHT_CRON_ENV=ok PATH=" "$sim/n.out" && grep -qx "PREFLIGHT_MEDIA_ROOT=ok" "$sim/n.out" && grep -q "^PREFLIGHT_FFMPEG=/" "$sim/n.out" && grep -qx "PREFLIGHT_NAMED_LOCKS=ok" "$sim/n.out" && grep -qx "PREFLIGHT_FFMPEG_RENDER=ok" "$sim/n.out" && grep -q "^PREFLIGHT_PREPEND=ok mode=0600" "$sim/n.out"'
ok "deploy: every file matched the baseline or was new, and the backup was verified" 'grep -qx "DRIFT_OK=yes" "$sim/n.out" && grep -qx "BACKUP_VERIFIED=yes" "$sim/n.out" && [ -n "$first_backup" ]'
ok "deploy: the backup is private and holds the replaced files" 'd="$SIM_HOME/.rateb-ai/backups/$first_backup"; [ "$(stat -c %a "$d")" = 700 ] && [ "$(stat -c %a "$d/files.tar.gz")" = 600 ] && tar -tzf "$d/files.tar.gz" > "$sim/list" && grep -qx "app/video/engine.php" "$sim/list" && ! grep -qx "app/video/runtime.php" "$sim/list"'
ok "deploy: the worker readiness check ran under cron's environment and passed" 'grep -qx "WORKER_READY=yes" "$sim/n.out" && grep -qx "WORKER_FFMPEG_RENDER=ok" "$sim/n.out" && grep -qx "WORKER_NAMED_LOCKS=ok" "$sim/n.out" && grep -qx "WORKER_ENV=loaded" "$sim/n.out" && grep -q "env -i .*video-worker.php${q} --check" "$sim/log"'
same=1
while IFS= read -r rel; do
  case "$rel" in ''|'#'*|config/google.php) continue ;; esac
  cmp -s "$src/$rel" "$target/$rel" || { same=0; echo "  differs: $rel"; }
done < "$src/ops/deploy-files.txt"
ok "deploy: every application file matches the repository" '[ $same -eq 1 ]'
recorded_mode() { awk -F '\t' -v rel="$2" '$1 == rel { print $4 }' "$SIM_HOME/.rateb-ai/backups/$1/backup.tsv"; }
ok "deploy: config/google.php was generated from the secret and kept its previous mode" 'grep -qF "$SIM_CLIENT_ID" "$target/config/google.php" && [ "$(stat -c %a "$target/config/google.php")" = "$(recorded_mode "$first_backup" config/google.php)" ]'
ok "deploy: new files are created with mode 644" '[ "$(stat -c %a "$target/app/video/runtime.php")" = 644 ] && [ "$(stat -c %a "$target/cron/video-worker.php")" = 644 ]'
grep '^APPLIED=' "$sim/n.out" | sed 's/^APPLIED=//' > "$sim/applied"
ok "deploy: files were swapped in the order of ops/deploy-files.txt" 'grep -vE "^(#|$)" "$src/ops/deploy-files.txt" | grep -xF -f "$sim/applied" | cmp -s - "$sim/applied"'
ok "deploy: exactly one cron entry, guarded by the hold, other entries unchanged" '[ "$(grep -cE "# rateb-ai-video-worker$" "$FAKE_CRON")" = 1 ] && grep -qF "* * * * * [ ! -e $SIM_HOME/.rateb-ai/video-worker.hold ] && /usr/local/bin/php $target/cron/video-worker.php --log=$SIM_HOME/.rateb-ai/video-worker.log" "$FAKE_CRON" && [ "$(grep -vE "# rateb-ai-video-worker$" "$FAKE_CRON")" = "$others" ]'
ok "deploy: hold and pause cleared; work folder, opcache script, and key removed" '[ ! -e "$SIM_HOME/.rateb-ai/video-worker.hold" ] && [ ! -e "$SIM_HOME/.rateb-ai/video-worker.pause" ] && [ ! -e "$SIM_HOME/.rateb-ai/deploy-$run_id" ] && [ ! -e "$target/opcache-$run_id.php" ] && key_removed ai-deploy-key-path'
ok "deploy: the order was preflight, drift check and backup, pause, swap, readiness, cron" 'in_order "$sim/log" "preflight.php${q} --cron-env" "bash -s -- prepare" "touch \"\$HOME/.rateb-ai/video-worker.pause\"" "bash -s -- apply" "video-worker.php${q} --check" "bash -s -- install"'
run_job deploy.sh "$sim/n2.out"; code=$?
ok "deploy: a second deploy changes no file and keeps exactly one cron entry" '[ $code -eq 0 ] && grep -qx "FILES_CHANGED=0" "$sim/n2.out" && ! grep -q "^APPLIED=" "$sim/n2.out" && grep -qx "DRIFT=app/video/engine.php ok deployed" "$sim/n2.out" && [ "$(grep -cE "# rateb-ai-video-worker$" "$FAKE_CRON")" = 1 ]'

# O. worker-off waits for a running pass, removes only the tagged entry, and clears the pause.
: > "$sim/log"
bash -c 'sleep 4; :' "$target/cron/video-worker.php" &
fake_worker=$!
started=$SECONDS
run_job worker-off.sh "$sim/o.out"; code=$?
elapsed=$((SECONDS - started))
wait "$fake_worker" 2> /dev/null
ok "worker-off: succeeds and reports the worker stopped and no hold" '[ $code -eq 0 ] && grep -qx "VIDEO_WORKER=stopped" "$sim/o.out" && grep -qx "CRON_ENTRY=removed" "$sim/o.out" && grep -qx "VIDEO_WORKER_HOLD=absent" "$sim/o.out"'
ok "worker-off: waited for the running pass to exit" '[ $elapsed -ge 4 ]'
ok "worker-off: only the tagged entry was removed" '[ "$(cat "$FAKE_CRON")" = "$others" ]'
ok "worker-off: the pause file was set, then cleared; nothing uploaded; key removed" 'grep -qx "VIDEO_WORKER_PAUSE=set" "$sim/o.out" && grep -qx "VIDEO_WORKER_PAUSE=cleared" "$sim/o.out" && [ ! -e "$SIM_HOME/.rateb-ai/video-worker.pause" ] && ! grep -q "^SCP " "$sim/log" && key_removed ai-off-key-path'
run_job worker-off.sh "$sim/o2.out"; code=$?
ok "worker-off: a second run is a no-op" '[ $code -eq 0 ] && grep -qx "CRON_ENTRY=absent" "$sim/o2.out" && [ "$(cat "$FAKE_CRON")" = "$others" ]'
run_job deploy.sh "$sim/o3.out"
export FAKE_CRON_FAIL=1
run_job worker-off.sh "$sim/o4.out"; code=$?
unset FAKE_CRON_FAIL
ok "worker-off with an unreadable crontab: fails without changing the crontab and leaves the pause set" '[ $code -ne 0 ] && grep -q "could not read the current crontab" "$sim/o4.out" && [ "$(grep -cE "# rateb-ai-video-worker$" "$FAKE_CRON")" = 1 ] && [ -e "$SIM_HOME/.rateb-ai/video-worker.pause" ]'

# P. Rollback to the first backup after later deploys: the baseline comes back, cron is removed.
SIM_BACKUP_ID="$first_backup"
: > "$sim/log"
run_job rollback.sh "$sim/p.out"; code=$?
ok "rollback: restores the baseline tree exactly" '[ $code -eq 0 ] && grep -qx "ROLLBACK_OK=yes" "$sim/p.out" && grep -qx "ROLLBACK=done" "$sim/p.out" && [ "$(tree_hash "$target")" = "$before" ]'
ok "rollback: deletes files the deploy added and restores the ones it replaced" 'grep -qx "REMOVED_FILE=app/video/runtime.php" "$sim/p.out" && grep -qx "RESTORED_FILE=app/video/engine.php" "$sim/p.out" && [ ! -e "$target/cron/video-worker.php" ]'
ok "rollback: the worker cron entry is removed, other entries kept, hold and pause cleared" '[ "$(cat "$FAKE_CRON")" = "$others" ] && [ ! -e "$SIM_HOME/.rateb-ai/video-worker.hold" ] && [ ! -e "$SIM_HOME/.rateb-ai/video-worker.pause" ] && grep -qx "VIDEO_WORKER=removed" "$sim/p.out"'
ok "rollback: a backup of the state it replaced was written first" 'pre="$(sed -n "s/^PRE_RESTORE_BACKUP=//p" "$sim/p.out")"; [ -n "$pre" ] && tar -tzf "$SIM_HOME/.rateb-ai/backups/$pre/files.tar.gz" > "$sim/list" && grep -qx "app/video/runtime.php" "$sim/list"'

# Q. Rollback when a file changed after the deploy: refused, then allowed explicitly.
new_server q
before="$(tree_hash "$target")"
run_job deploy.sh "$sim/q0.out"
SIM_BACKUP_ID="$(backup_id_of "$sim/q0.out")"
printf '\n// edited after the deploy\n' >> "$target/app/plans.php"
after_edit="$(tree_hash "$target")"
run_job rollback.sh "$sim/q.out"; code=$?
ok "rollback mismatch: refused, names the file, changes nothing, keeps the hold" '[ $code -ne 0 ] && grep -q "^ROLLBACK_FILE=app/plans.php MISMATCH" "$sim/q.out" && [ "$(tree_hash "$target")" = "$after_edit" ] && [ -e "$SIM_HOME/.rateb-ai/video-worker.hold" ] && grep -qx "VIDEO_WORKER_HOLD=kept" "$sim/q.out"'
SIM_ALLOW=true
run_job rollback.sh "$sim/q2.out"; code=$?
ok "rollback mismatch allowed: restores the baseline and keeps the edited copy in a backup" '[ $code -eq 0 ] && [ "$(tree_hash "$target")" = "$before" ] && pre="$(sed -n "s/^PRE_RESTORE_BACKUP=//p" "$sim/q2.out")" && tar -xzOf "$SIM_HOME/.rateb-ai/backups/$pre/files.tar.gz" app/plans.php | grep -q "edited after the deploy"'
SIM_BACKUP_ID="../../etc"
SIM_ALLOW=false
: > "$sim/log"
run_job rollback.sh "$sim/q3.out"; code=$?
ok "rollback: an invalid backup id is refused before connecting" '[ $code -ne 0 ] && grep -q "Set backup_id" "$sim/q3.out" && [ ! -s "$sim/log" ]'

ok "no output or log contains the database password or a client id" '! grep -rqF -e "$RATEB_TEST_DB_PASS" -e "$SIM_CLIENT_ID" -e "$PROD_CLIENT_ID" "$sim"/*.out "$sim/log"'

php "$sim/seed.php" --drop > /dev/null 2>&1 || true
chmod -R u+w "$sim" 2> /dev/null
rm -rf "$sim"
echo "SUMMARY pass=$pass fail=$fail"
if [ $fail -eq 0 ]; then echo "RESULT=PASS"; exit 0; fi
echo "RESULT=FAIL"
exit 1
