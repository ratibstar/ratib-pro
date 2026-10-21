#!/usr/bin/env bash
# Runs the real deploy and worker-off steps of .github/workflows/ai-rateb-sa.yml with fake
# ssh, scp, and curl commands that act on a sandboxed "server" home on this machine. The
# preflight, uploads, checksums, readiness check (against a disposable MySQL database and the
# local ffmpeg), and cron script all run for real; only the network transport is faked, and
# crontab is a fake that writes to a file, so the machine's own crontab is never touched.
#
# RUN (from ai-rateb-sa-source):
#   RATEB_TEST_DB_HOST=127.0.0.1 RATEB_TEST_DB_PORT=3306 RATEB_TEST_DB_USER=... \
#   RATEB_TEST_DB_PASS=... RATEB_DEPLOY_TEST_DB_NAME=rateb_ai_deploy_test bash tests/deploy-sim.sh
# Needs ffmpeg, PHP with pdo_mysql/gd/posix, python3 with yaml, and a writable
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

sim="$(mktemp -d)"
sim="$(cd "$sim" && pwd -P)"
me="$(id -un)"
mkdir -p "$sim/bin" "$sim/remote-bin" "$sim/broken-bin" "$sim/runner"
pass=0; fail=0
ok() { if eval "$2"; then echo "PASS $1"; pass=$((pass+1)); else echo "FAIL $1"; fail=$((fail+1)); fi; }

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

cat > "$sim/bin/ssh" << 'EOF'
#!/usr/bin/env bash
while [ $# -gt 0 ]; do
  case "$1" in -i|-p|-o) shift 2 ;; -*) shift ;; *) break ;; esac
done
shift
cmd="$*"
cmd="${cmd//\/home\/admin/$SIM_HOME}"
cmd="${cmd//\/usr\/local\/bin\/php/$SIM_PHP}"
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
  printf '\n// changed in transit\n' >> "$dest"
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
cat > "$sim/broken-bin/ffmpeg" << 'EOF'
#!/bin/sh
case " $* " in
  *" -encoders "*) printf ' A....D aac   AAC\n'; exit 0 ;;
esac
exec "$REAL_FFMPEG" "$@"
EOF
chmod +x "$sim/bin/"* "$sim/remote-bin/"* "$sim/broken-bin/"*
export REAL_FFMPEG="$(command -v ffmpeg)"

python3 - "$workflow" "$sim" << 'PY'
import sys, yaml
jobs = yaml.safe_load(open(sys.argv[1], encoding="utf-8"))["jobs"]
for job, name in (("deploy", "deploy.sh"), ("worker-off", "worker-off.sh")):
    run = [s for s in jobs[job]["steps"] if "run" in s][0]["run"]
    open(sys.argv[2] + "/" + name, "w", encoding="utf-8", newline="\n").write(run)
PY

DEFAULT_REMOTE_PATH="$sim/remote-bin:$PATH"
new_server() {
  SIM_HOME="$sim/$1/home/$me"
  mkdir -p "$SIM_HOME/domains/ai.rateb.sa" "$SIM_HOME/private"
  SIM_HOME="$(cd "$SIM_HOME" && pwd -P)"
  target="$SIM_HOME/domains/ai.rateb.sa"
  (cd "$src" && tar --exclude=./tests --exclude=./ops -cf - .) | (cd "$target" && tar -xf -)
  rm -rf "$target/cron" "$target/app/video/worker.php" "$target/app/video/runtime.php" "$target/app/db.php"
  printf '<?php\n// server copy from before the worker\n' > "$target/app/video/engine.php"
  cat > "$target/config/database.php" << PHPCFG
<?php
return ['host' => '${RATEB_TEST_DB_HOST};port=${RATEB_TEST_DB_PORT}', 'database' => '${RATEB_DEPLOY_TEST_DB_NAME}', 'username' => '${RATEB_TEST_DB_USER}', 'password' => getenv('RATEB_AI_DB_PASSWORD') ?: '', 'charset' => 'utf8mb4'];
PHPCFG
  printf "<?php\nputenv('RATEB_AI_DB_PASSWORD=%s');\nputenv('RUNWAY_API_KEY=sim-provider-key');\n" "$RATEB_TEST_DB_PASS" > "$SIM_HOME/private/env.php"
  chmod 600 "$SIM_HOME/private/env.php"
  printf 'auto_prepend_file = "%s/private/env.php"\n' "$SIM_HOME" > "$target/.user.ini"
  FAKE_CRON="$SIM_HOME/.fake-crontab"
  SIM_REMOTE_PATH="$DEFAULT_REMOTE_PATH"
  export SIM_HOME FAKE_CRON SIM_REMOTE_PATH
  unset SIM_CORRUPT SIM_FAIL_CHECK SIM_WORKER_HTTP FAKE_CRON_FAIL
  : > "$sim/log"
}
tree_hash() { (cd "$1" && find . -type f -print0 | sort -z | xargs -0 sha256sum) | sha256sum | awk '{print $1}'; }
run_job() {
  rm -f "$sim/runner/"*
  (
    cd "$repo" && env GITHUB_SHA=simulated GITHUB_RUN_ID=4242 RUNNER_TEMP="$sim/runner" \
      DEPLOY_SSH_HOST=sim.invalid DEPLOY_SSH_USER=admin DEPLOY_SSH_PORT=22 \
      DEPLOY_SSH_PRIVATE_KEY='-----BEGIN SIM KEY-----\nnot-a-real-key\n-----END SIM KEY-----' \
      PATH="$sim/bin:$PATH" SIM_LOG="$sim/log" SIM_PHP="$(command -v php)" \
      bash --noprofile --norc -eo pipefail "$sim/$1"
  ) > "$2" 2>&1
}
key_removed() { local f; f="$(cat "$sim/runner/$1" 2> /dev/null)"; [ -n "$f" ] && [ ! -e "$f" ]; }
q="'"
in_order() {
  python3 - "$@" << 'PY'
import sys
log = open(sys.argv[1], encoding="utf-8").read()
at = [log.find(s) for s in sys.argv[2:]]
sys.exit(0 if -1 not in at and at == sorted(at) else 1)
PY
}
untouched_after_abort() {
  [ "$(tree_hash "$target")" = "$before" ] \
    && ! grep -q "^SCP $target/" "$sim/log" \
    && ! grep -q 'touch "$HOME/.rateb-ai/video-worker.pause"' "$sim/log" \
    && [ ! -e "$SIM_HOME/.rateb-ai/video-worker.pause" ] \
    && [ ! -e "$FAKE_CRON" ] \
    && [ ! -e "$SIM_HOME/.rateb-ai/preflight-4242" ] \
    && key_removed ai-deploy-key-path
}

# A. Preflight: the server's app/bootstrap.php is not a known version.
new_server a
printf '<?php\n// edited on the server\n' > "$target/app/bootstrap.php"
before="$(tree_hash "$target")"
run_job deploy.sh "$sim/a.out"; code=$?
ok "preflight abort (bootstrap): the deploy fails" '[ $code -ne 0 ] && grep -q "^PREFLIGHT_BOOTSTRAP=FAILED unexpected sha256=" "$sim/a.out" && grep -q "Preflight failed; no application file was changed" "$sim/a.out"'
ok "preflight abort (bootstrap): nothing in the web root, the pause file, or the crontab changed; temp files and key removed" 'untouched_after_abort'

# B. Preflight: the prepend file is group-writable.
new_server b
chmod 660 "$SIM_HOME/private/env.php"
before="$(tree_hash "$target")"
run_job deploy.sh "$sim/b.out"; code=$?
ok "preflight abort (prepend mode): the deploy fails and names the mode" '[ $code -ne 0 ] && grep -q "^PREFLIGHT_PREPEND=FAILED the prepend file is group- or world-writable mode=0660" "$sim/b.out"'
ok "preflight abort (prepend mode): nothing changed" 'untouched_after_abort'

# C. Preflight: ffmpeg cannot encode H.264.
new_server c
export SIM_REMOTE_PATH="$sim/broken-bin:$DEFAULT_REMOTE_PATH"
before="$(tree_hash "$target")"
run_job deploy.sh "$sim/c.out"; code=$?
ok "preflight abort (ffmpeg): the deploy fails and names the missing encoder" '[ $code -ne 0 ] && grep -q "^PREFLIGHT_FFMPEG_RENDER=FAILED missing libx264" "$sim/c.out"'
ok "preflight abort (ffmpeg): nothing changed" 'untouched_after_abort'

# D. Preflight: the database rejects the configured password.
new_server d
sed -i "s/RATEB_AI_DB_PASSWORD=[A-Za-z0-9]*/RATEB_AI_DB_PASSWORD=wrongpassword/" "$SIM_HOME/private/env.php"
before="$(tree_hash "$target")"
run_job deploy.sh "$sim/d.out"; code=$?
ok "preflight abort (database): the deploy fails with only the error class and code" '[ $code -ne 0 ] && grep -q "^PREFLIGHT_DB=FAILED PDOException code=" "$sim/d.out" && ! grep -q "wrongpassword" "$sim/d.out"'
ok "preflight abort (database): nothing changed" 'untouched_after_abort'

# E. A file changes in transit: the trap clears the pause file.
new_server e
export SIM_CORRUPT=app/video/engine.php
run_job deploy.sh "$sim/e.out"; code=$?
ok "checksum mismatch: the deploy stops at that file" '[ $code -ne 0 ] && grep -q "Checksum mismatch for app/video/engine.php" "$sim/e.out" && ! grep -q "^SCP $target/app/video/worker.php" "$sim/log"'
ok "checksum mismatch: the pause file was set, then cleared by the trap; key removed" 'grep -q "^VIDEO_WORKER_PAUSE=set" "$sim/e.out" && grep -q "^VIDEO_WORKER_PAUSE=cleared" "$sim/e.out" && [ ! -e "$SIM_HOME/.rateb-ai/video-worker.pause" ] && key_removed ai-deploy-key-path'
ok "checksum mismatch: the crontab was not touched" '[ ! -e "$FAKE_CRON" ]'

# F. The HTTP guard: the worker answers over the web.
new_server f
export SIM_WORKER_HTTP=200
run_job deploy.sh "$sim/f.out"; code=$?
ok "worker reachable over HTTP: the deploy fails before readiness and cron" '[ $code -ne 0 ] && grep -q "answered over HTTP" "$sim/f.out" && ! grep -q "video-worker.php. --check" "$sim/log" && [ ! -e "$FAKE_CRON" ] && [ ! -e "$SIM_HOME/.rateb-ai/video-worker.pause" ]'

# G. Readiness fails after upload: no cron change, pause cleared.
new_server g
export SIM_FAIL_CHECK=1
printf '0 4 * * * /usr/bin/true\n' > "$FAKE_CRON"
run_job deploy.sh "$sim/g.out"; code=$?
ok "readiness failure: the deploy fails, the crontab is unchanged, and the pause file is cleared" '[ $code -ne 0 ] && grep -q "readiness check failed; the cron entry was not installed" "$sim/g.out" && [ "$(cat "$FAKE_CRON")" = "0 4 * * * /usr/bin/true" ] && [ ! -e "$SIM_HOME/.rateb-ai/video-worker.pause" ]'

# H. A full deploy with the real preflight, readiness check, and cron install.
new_server h
others="$(printf 'MAILTO=""\n0 3 * * * /usr/local/bin/php /home/admin/domains/rateb.sa/public_html/cron/daily.php')"
printf '%s\n' "$others" > "$FAKE_CRON"
run_job deploy.sh "$sim/h.out"; code=$?
ok "deploy: succeeds" '[ $code -eq 0 ]'
ok "deploy: preflight passed every check" 'grep -qx "PREFLIGHT_OK=yes" "$sim/h.out" && grep -qx "PREFLIGHT_NAMED_LOCKS=ok" "$sim/h.out" && grep -qx "PREFLIGHT_FFMPEG_RENDER=ok" "$sim/h.out" && grep -q "^PREFLIGHT_PREPEND=ok mode=0600" "$sim/h.out"'
ok "deploy: the worker readiness check rendered a video and verified two locks" 'grep -qx "WORKER_READY=yes" "$sim/h.out" && grep -qx "WORKER_FFMPEG_RENDER=ok" "$sim/h.out" && grep -qx "WORKER_NAMED_LOCKS=ok" "$sim/h.out" && grep -qx "WORKER_ENV=loaded" "$sim/h.out"'
same=1
for rel in app/video/runtime.php app/video/engine.php app/video/worker.php app/db.php app/bootstrap.php cron/video-worker.php cron/.htaccess app/ai/video-status.php; do
  cmp -s "$src/$rel" "$target/$rel" || { same=0; echo "  differs: $rel"; }
done
ok "deploy: the uploaded worker files match the repository" '[ $same -eq 1 ]'
ok "deploy: exactly one cron entry for this target, other entries unchanged" '[ "$(grep -cE "# rateb-ai-video-worker$" "$FAKE_CRON")" = 1 ] && grep -q "^\* \* \* \* \* /usr/local/bin/php $target/cron/video-worker.php --log=$SIM_HOME/.rateb-ai/video-worker.log" "$FAKE_CRON" && [ "$(grep -vE "# rateb-ai-video-worker$" "$FAKE_CRON")" = "$others" ]'
ok "deploy: pause cleared, preflight folder, opcache script, and key removed" '[ ! -e "$SIM_HOME/.rateb-ai/video-worker.pause" ] && [ ! -e "$SIM_HOME/.rateb-ai/preflight-4242" ] && [ ! -e "$target/opcache-4242.php" ] && key_removed ai-deploy-key-path'
ok "deploy: the order was preflight, pause, upload, readiness, cron" 'in_order "$sim/log" "preflight.php${q} --target=" "touch \"\$HOME/.rateb-ai/video-worker.pause\"" "SCP $target/app/plans.php" "video-worker.php${q} --check" "bash -s -- install"'
run_job deploy.sh "$sim/h2.out"; code=$?
ok "deploy: a second deploy keeps exactly one cron entry" '[ $code -eq 0 ] && [ "$(grep -cE "# rateb-ai-video-worker$" "$FAKE_CRON")" = 1 ] && [ "$(grep -vE "# rateb-ai-video-worker$" "$FAKE_CRON")" = "$others" ]'

# I. worker-off waits for a running pass, removes only the tagged entry, and clears the pause.
: > "$sim/log"
bash -c 'sleep 4; :' "$target/cron/video-worker.php" &
fake_worker=$!
started=$SECONDS
run_job worker-off.sh "$sim/i.out"; code=$?
elapsed=$((SECONDS - started))
wait "$fake_worker" 2> /dev/null
ok "worker-off: succeeds and reports the worker stopped" '[ $code -eq 0 ] && grep -qx "VIDEO_WORKER=stopped" "$sim/i.out" && grep -qx "CRON_ENTRY=removed" "$sim/i.out"'
ok "worker-off: waited for the running pass to exit" '[ $elapsed -ge 4 ]'
ok "worker-off: only the tagged entry was removed" '[ "$(cat "$FAKE_CRON")" = "$others" ]'
ok "worker-off: the pause file was set, then cleared" 'grep -qx "VIDEO_WORKER_PAUSE=set" "$sim/i.out" && grep -qx "VIDEO_WORKER_PAUSE=cleared" "$sim/i.out" && [ ! -e "$SIM_HOME/.rateb-ai/video-worker.pause" ]'
ok "worker-off: nothing was uploaded" '! grep -q "^SCP " "$sim/log"'
ok "worker-off: the key was removed" 'key_removed ai-off-key-path'
run_job worker-off.sh "$sim/i2.out"; code=$?
ok "worker-off: a second run is a no-op" '[ $code -eq 0 ] && grep -qx "CRON_ENTRY=absent" "$sim/i2.out" && [ "$(cat "$FAKE_CRON")" = "$others" ]'

# J. worker-off when the crontab cannot be read: nothing changes, the pause stays (it expires).
run_job deploy.sh "$sim/j0.out"
export FAKE_CRON_FAIL=1
run_job worker-off.sh "$sim/j.out"; code=$?
unset FAKE_CRON_FAIL
ok "worker-off with an unreadable crontab: fails without changing the crontab and leaves the pause set" '[ $code -ne 0 ] && grep -q "could not read the current crontab" "$sim/j.out" && [ "$(grep -cE "# rateb-ai-video-worker$" "$FAKE_CRON")" = 1 ] && [ -e "$SIM_HOME/.rateb-ai/video-worker.pause" ]'

ok "no output or log contains the database password" '! grep -rqF "$RATEB_TEST_DB_PASS" "$sim"/*.out "$sim/log"'

php "$sim/seed.php" --drop > /dev/null 2>&1 || true
rm -rf "$sim"
echo "SUMMARY pass=$pass fail=$fail"
if [ $fail -eq 0 ]; then echo "RESULT=PASS"; exit 0; fi
echo "RESULT=FAIL"
exit 1
