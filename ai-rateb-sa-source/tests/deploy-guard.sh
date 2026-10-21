#!/usr/bin/env bash
# Runs ops/deploy-guard.sh (prepare, apply, restore, list) against a sandboxed home with
# hand-made bundles, and ops/deploy-manifest.py against a throwaway clone of this repository.
# No database, network, or real crontab is used.
# Usage: bash tests/deploy-guard.sh (from ai-rateb-sa-source, in a git checkout).
set -u
src="$(cd "$(dirname "$0")/.." && pwd)"
repo="$(cd "$src/.." && pwd)"
guard="$src/ops/deploy-guard.sh"
root="$(mktemp -d)"
root="$(cd "$root" && pwd -P)"
me="$(id -un)"
pass=0; fail=0
ok() { if eval "$2"; then echo "PASS $1"; pass=$((pass+1)); else echo "FAIL $1"; fail=$((fail+1)); fi; }
sha() { sha256sum "$1" | cut -c1-64; }
sha_of_text() { printf '%s' "$1" | sha256sum | cut -c1-64; }
tree_hash() { (cd "$1" && find . -type f -print0 | sort -z | xargs -0 sha256sum) | sha256sum | cut -c1-64; }

cat > "$root/mkbundle.py" << 'PY'
import hashlib, io, os, sys, tarfile
work, staged = sys.argv[1], sys.argv[2]
extras = sys.argv[3:]
bundle = os.path.join(work, "bundle.tar.gz")
def clean(info):
    info.uid = info.gid = 0
    info.uname = info.gname = ""
    info.mode = 0o600
    return info
with tarfile.open(bundle, "w:gz") as tar:
    tar.add(os.path.join(staged, "manifest.tsv"), arcname="manifest.tsv", filter=clean)
    for base, _, names in os.walk(os.path.join(staged, "files")):
        for name in sorted(names):
            path = os.path.join(base, name)
            tar.add(path, arcname=os.path.relpath(path, staged).replace(os.sep, "/"), filter=clean)
    for extra in extras:
        kind, name = extra.split("=", 1)
        info = tarfile.TarInfo(name)
        if kind == "link":
            info.type = tarfile.SYMTYPE
            info.linkname = "/etc/passwd"
            tar.addfile(info)
        else:
            data = b"extra"
            info.size = len(data)
            tar.addfile(info, io.BytesIO(data))
digest = hashlib.sha256(open(bundle, "rb").read()).hexdigest()
open(os.path.join(work, "bundle.sha256"), "w").write(digest + "\n")
PY

# A fresh server: app/a.php (to be replaced, mode 640), app/lib/b.php (unchanged by the
# deploy), app/keep.php (not in the deploy). The bundle replaces a.php, keeps b.php, and adds
# app/new/c.php.
setup() {
  home="$root/$1/home/$me"
  target="$home/domains/ai.rateb.sa"
  mkdir -p "$target/app/lib"
  home="$(cd "$home" && pwd -P)"
  target="$home/domains/ai.rateb.sa"
  printf 'A1' > "$target/app/a.php"; chmod 640 "$target/app/a.php"
  printf 'B1' > "$target/app/lib/b.php"
  printf 'K' > "$target/app/keep.php"
  staged="$root/$1/staged"
  mkdir -p "$staged/files/app/lib" "$staged/files/app/new"
  printf 'A2' > "$staged/files/app/a.php"
  printf 'B1' > "$staged/files/app/lib/b.php"
  printf 'C1' > "$staged/files/app/new/c.php"
  A1="$(sha_of_text A1)"; A2="$(sha_of_text A2)"; B1="$(sha_of_text B1)"; C1="$(sha_of_text C1)"
  printf 'app/a.php\tapp\t%s\t%s:deployed,%s:baseline\t0\napp/lib/b.php\tapp\t%s\t%s:deployed\t0\napp/new/c.php\tapp\t%s\t%s:deployed\t1\n' \
    "$A2" "$A2" "$A1" "$B1" "$B1" "$C1" "$C1" > "$staged/manifest.tsv"
  work="$home/.rateb-ai/deploy-7"
  mkdir -p "$work"
  before="$(tree_hash "$target")"
}
bundle() { python3 "$root/mkbundle.py" "$work" "$staged" "$@"; }
g() { HOME="$home" bash "$guard" "$@"; }
backup_of() { sed -n 's/^BACKUP_ID=//p' "$1" | tail -n 1; }
no_backups() { [ ! -d "$home/.rateb-ai/backups" ] || [ -z "$(ls -A "$home/.rateb-ai/backups")" ]; }
refused_prepare() {
  g prepare "$target" 7 > "$root/out" 2>&1; local c=$?
  [ $c -ne 0 ] && grep -q "$1" "$root/out" && [ "$(tree_hash "$target")" = "$before" ] && no_backups
}

# Happy path.
setup ok
bundle
g prepare "$target" 7 > "$root/p.out" 2>&1; code=$?
id="$(backup_of "$root/p.out")"
ok "prepare: every file matches, and the backup is written and verified" '[ $code -eq 0 ] && grep -qx "DRIFT=app/a.php ok baseline" "$root/p.out" && grep -qx "DRIFT=app/lib/b.php ok deployed" "$root/p.out" && grep -qx "DRIFT=app/new/c.php ok new" "$root/p.out" && grep -qx "BACKUP_VERIFIED=yes" "$root/p.out" && grep -qx "BACKUP_FILES=2" "$root/p.out" && [ -n "$id" ]'
ok "prepare: nothing in the target changed" '[ "$(tree_hash "$target")" = "$before" ] && [ ! -e "$home/.rateb-ai/video-worker.hold" ]'
ok "prepare: the backup is private and records modes" '[ "$(stat -c %a "$home/.rateb-ai/backups/$id")" = 700 ] && grep -q "^app/a.php	present	$A1	640	$A2$" "$home/.rateb-ai/backups/$id/backup.tsv" && grep -q "^app/new/c.php	absent	-	-	$C1$" "$home/.rateb-ai/backups/$id/backup.tsv"'
g apply "$target" 7 "$id" > "$root/a.out" 2>&1; code=$?
ok "apply: replaces, keeps, and adds files in manifest order" '[ $code -eq 0 ] && grep -qx "APPLY_OK=yes" "$root/a.out" && grep -qx "FILES_CHANGED=2" "$root/a.out" && grep -qx "UNCHANGED=app/lib/b.php" "$root/a.out" && [ "$(cat "$target/app/a.php")" = A2 ] && [ "$(cat "$target/app/new/c.php")" = C1 ]'
ok "apply: keeps the replaced file's mode, gives new files 644, leaves other files alone" '[ "$(stat -c %a "$target/app/a.php")" = 640 ] && [ "$(stat -c %a "$target/app/new/c.php")" = 644 ] && [ "$(cat "$target/app/keep.php")" = K ]'
ok "apply: sets the hold (the workflow releases it after readiness)" 'grep -qx "HOLD=set" "$root/a.out" && grep -qx "backup=$id" "$home/.rateb-ai/video-worker.hold"'
g list "$id" > "$root/l.out" 2>&1
ok "list: prints every file the backup covers" '[ "$(grep -c "^BACKUP_FILE=" "$root/l.out")" = 3 ]'
g restore "$target" "$id" > "$root/r.out" 2>&1; code=$?
ok "restore: brings back the exact previous tree and modes" '[ $code -eq 0 ] && grep -qx "ROLLBACK_OK=yes" "$root/r.out" && [ "$(tree_hash "$target")" = "$before" ] && [ "$(stat -c %a "$target/app/a.php")" = 640 ] && [ ! -e "$target/app/new/c.php" ]'
ok "restore: writes a pre-restore backup first" 'pre="$(sed -n "s/^PRE_RESTORE_BACKUP=//p" "$root/r.out")"; [ -n "$pre" ] && tar -xzOf "$home/.rateb-ai/backups/$pre/files.tar.gz" app/new/c.php | grep -qx C1'
g restore "$target" "$id" > "$root/r2.out" 2>&1; code=$?
ok "restore: a second restore is a no-op" '[ $code -eq 0 ] && grep -qx "RESTORED=0" "$root/r2.out" && grep -qx "REMOVED=0" "$root/r2.out" && [ "$(tree_hash "$target")" = "$before" ]'

# Drift and unexpected server state.
setup drift
printf 'A-hotfix' > "$target/app/a.php"
before="$(tree_hash "$target")"
bundle
ok "drift: unexpected content stops prepare before the backup" 'refused_prepare "^DRIFT=app/a.php FAILED unexpected content sha256=$(sha_of_text A-hotfix)" && grep -qx "DRIFT_OK=no" "$root/out"'

setup untracked
mkdir -p "$target/app/new"
printf 'production only' > "$target/app/new/c.php"
before="$(tree_hash "$target")"
bundle
ok "drift: an untracked production-only file where a new file goes is not overwritten" 'refused_prepare "^DRIFT=app/new/c.php FAILED unexpected content"'

setup missing
rm "$target/app/lib/b.php"
before="$(tree_hash "$target")"
bundle
ok "drift: a file the baseline has but the server lacks is reported" 'refused_prepare "^DRIFT=app/lib/b.php FAILED missing on the server"'

setup linkdir
mkdir -p "$root/linkdir/elsewhere"
mv "$target/app/lib" "$root/linkdir/elsewhere/lib"
ln -s "$root/linkdir/elsewhere/lib" "$target/app/lib"
before="$(tree_hash "$target")"
bundle
ok "symlinked folder on a file's path is refused" 'refused_prepare "^DRIFT=app/lib/b.php FAILED a folder on its path, or the file, is a symbolic link"'

setup linkfile
printf 'B1' > "$root/linkfile/real-b.php"
rm "$target/app/lib/b.php"
ln -s "$root/linkfile/real-b.php" "$target/app/lib/b.php"
before="$(tree_hash "$target")"
bundle
ok "symlinked target file is refused" 'refused_prepare "^DRIFT=app/lib/b.php FAILED a folder on its path, or the file, is a symbolic link"'

setup notfile
rm "$target/app/a.php"; mkdir "$target/app/a.php"
before="$(tree_hash "$target")"
bundle
ok "a directory where a file goes is refused" 'refused_prepare "^DRIFT=app/a.php FAILED it is not a regular file"'

# Bundle integrity.
setup corrupt
bundle
printf 'x' >> "$work/bundle.tar.gz"
ok "bundle: a checksum mismatch is refused" 'refused_prepare "the uploaded bundle does not match its checksum"'

setup unsafe
bundle "file=files/app/../../escape.php"
ok "bundle: a path with .. is refused" 'refused_prepare "the bundle has an unsafe path" && [ ! -e "$home/domains/escape.php" ]'

setup unexpected
bundle "file=other/thing.php"
ok "bundle: an entry outside files/ is refused" 'refused_prepare "the bundle has an unexpected entry"'

setup extra
bundle "file=files/app/extra.php"
ok "bundle: a file the manifest does not list is refused" 'refused_prepare "the bundle and its manifest disagree"'

setup symlink
bundle "link=files/app/link.php"
ok "bundle: a symbolic link entry is refused" 'refused_prepare "the bundle contains links or special files"'

setup missingentry
rm "$staged/files/app/new/c.php"
bundle
ok "bundle: a manifest file missing from the bundle is refused" 'refused_prepare "the bundle and its manifest disagree"'

setup staledigest
printf 'A3' > "$staged/files/app/a.php"
bundle
ok "bundle: a staged file that does not match its manifest sha256 is refused" 'refused_prepare "the staged copy of app/a.php does not match the manifest"'

setup anyapp
sed -i "1s/:baseline/:baseline,any:accepted/" "$staged/manifest.tsv"
bundle
ok "manifest: any content is never allowed for an application file" 'refused_prepare "only generated files may accept any content"'

setup generated
printf "<?php return ['client_id' => 'server-value'];" > "$target/app/a.php"
before="$(tree_hash "$target")"
sed -i "1s/\tapp\t/\tgenerated\t/" "$staged/manifest.tsv"
bundle
ok "generated file: a different server copy is refused without printing a sha256" 'refused_prepare "^DRIFT=app/a.php FAILED this configuration file differs" && ! grep -q "^DRIFT=app/a.php.*sha256" "$root/out"'
sed -i "1s/:baseline/:baseline,any:accepted/" "$staged/manifest.tsv"
bundle
g prepare "$target" 7 > "$root/gen.out" 2>&1; code=$?
ok "generated file: accepted when the deploy explicitly allows it" '[ $code -eq 0 ] && grep -qx "DRIFT=app/a.php ok accepted" "$root/gen.out"'

setup outside
bundle
mkdir -p "$home/domains/rateb.sa/public_html"
g prepare "$home/domains/rateb.sa/public_html" 7 > "$root/out" 2>&1; code=$?
ok "target: anything but ai.rateb.sa in this home is refused" '[ $code -ne 0 ] && grep -q "not ai.rateb.sa" "$root/out" && no_backups'

# Apply safety.
setup tamper
bundle
g prepare "$target" 7 > "$root/p.out" 2>&1
id="$(backup_of "$root/p.out")"
printf 'x' >> "$home/.rateb-ai/backups/$id/files.tar.gz"
g apply "$target" 7 "$id" > "$root/out" 2>&1; code=$?
ok "apply: a tampered backup is refused before the hold or any change" '[ $code -ne 0 ] && grep -q "does not match its checksum" "$root/out" && [ "$(tree_hash "$target")" = "$before" ] && [ ! -e "$home/.rateb-ai/video-worker.hold" ]'

setup between
bundle
g prepare "$target" 7 > "$root/p.out" 2>&1
id="$(backup_of "$root/p.out")"
printf 'changed after the backup' > "$target/app/lib/b.php"
before="$(tree_hash "$target")"
g apply "$target" 7 "$id" > "$root/out" 2>&1; code=$?
ok "apply: a file changed after the backup stops it with zero files changed and the hold released" '[ $code -ne 0 ] && grep -q "app/lib/b.php changed after the backup was taken" "$root/out" && grep -qx "FILES_CHANGED=0" "$root/out" && grep -qx "HOLD=cleared" "$root/out" && [ ! -e "$home/.rateb-ai/video-worker.hold" ] && [ "$(tree_hash "$target")" = "$before" ]'

setup midway
bundle
g prepare "$target" 7 > "$root/p.out" 2>&1
id="$(backup_of "$root/p.out")"
mkdir -p "$target/app/new"
chmod 555 "$target/app/new"
g apply "$target" 7 "$id" > "$root/out" 2>&1; code=$?
chmod 755 "$target/app/new"
ok "apply: a failure after a file changed keeps the hold" '[ $code -ne 0 ] && grep -qx "APPLIED=app/a.php" "$root/out" && grep -qx "FILES_CHANGED=1" "$root/out" && grep -qx "HOLD=kept" "$root/out" && [ -e "$home/.rateb-ai/video-worker.hold" ]'
rmdir "$target/app/new"
g restore "$target" "$id" > "$root/r.out" 2>&1; code=$?
ok "restore after a partial apply: the previous tree comes back" '[ $code -eq 0 ] && [ "$(tree_hash "$target")" = "$before" ]'

setup wrongbackup
bundle
g prepare "$target" 7 > "$root/p.out" 2>&1
id="$(backup_of "$root/p.out")"
printf 'A9' > "$staged/files/app/a.php"
sed -i "1s/^app\/a.php\tapp\t$A2\t$A2:deployed/app\/a.php\tapp\t$(sha_of_text A9)\t$(sha_of_text A9):deployed/" "$staged/manifest.tsv"
bundle
HOME="$home" bash -c 'rm -rf "$HOME/.rateb-ai/deploy-7/stage"; mkdir -p "$HOME/.rateb-ai/deploy-7/stage"; tar -xzf "$HOME/.rateb-ai/deploy-7/bundle.tar.gz" -C "$HOME/.rateb-ai/deploy-7/stage"'
g apply "$target" 7 "$id" > "$root/out" 2>&1; code=$?
ok "apply: a backup taken for a different bundle is refused" '[ $code -ne 0 ] && grep -q "does not cover this bundle" "$root/out" && [ "$(tree_hash "$target")" = "$before" ]'
g apply "$target" 7 "../../etc" > "$root/out" 2>&1; code=$?
ok "apply: an invalid backup id is refused" '[ $code -ne 0 ] && grep -q "invalid backup id" "$root/out"'

# Restore safety.
setup mismatch
bundle
g prepare "$target" 7 > "$root/p.out" 2>&1
id="$(backup_of "$root/p.out")"
g apply "$target" 7 "$id" > /dev/null 2>&1
printf 'edited after deploy' > "$target/app/a.php"
after="$(tree_hash "$target")"
g restore "$target" "$id" > "$root/out" 2>&1; code=$?
ok "restore: a file changed after the deploy is refused and nothing changes" '[ $code -ne 0 ] && grep -q "^ROLLBACK_FILE=app/a.php MISMATCH" "$root/out" && grep -qx "ROLLBACK_OK=no" "$root/out" && [ "$(tree_hash "$target")" = "$after" ]'
g restore "$target" "$id" --allow-mismatch > "$root/out" 2>&1; code=$?
ok "restore --allow-mismatch: restores and keeps the edited copy in the pre-restore backup" '[ $code -eq 0 ] && [ "$(tree_hash "$target")" = "$before" ] && pre="$(sed -n "s/^PRE_RESTORE_BACKUP=//p" "$root/out")" && tar -xzOf "$home/.rateb-ai/backups/$pre/files.tar.gz" app/a.php | grep -qx "edited after deploy"'

setup nonregular
bundle
g prepare "$target" 7 > "$root/p.out" 2>&1
id="$(backup_of "$root/p.out")"
g apply "$target" 7 "$id" > /dev/null 2>&1
rm "$target/app/a.php"; mkdir "$target/app/a.php"
after="$(tree_hash "$target")"
g restore "$target" "$id" --allow-mismatch > "$root/out" 2>&1; code=$?
ok "restore: a non-regular file in the way is refused even with --allow-mismatch" '[ $code -ne 0 ] && grep -q "^ROLLBACK_FILE=app/a.php FAILED it is not a regular file" "$root/out" && [ -d "$target/app/a.php" ] && [ "$(tree_hash "$target")" = "$after" ]'

setup restoretamper
bundle
g prepare "$target" 7 > "$root/p.out" 2>&1
id="$(backup_of "$root/p.out")"
g apply "$target" 7 "$id" > /dev/null 2>&1
sed -i "s/\t640\t/\t600\t/" "$home/.rateb-ai/backups/$id/backup.tsv"
after="$(tree_hash "$target")"
g restore "$target" "$id" > "$root/out" 2>&1; code=$?
ok "restore: a backup whose record does not match its archive is refused" '[ $code -ne 0 ] && grep -q "does not hold the recorded copy of app/a.php" "$root/out" && [ "$(tree_hash "$target")" = "$after" ]'

# The bundle builder, in a throwaway clone so no object is written to this repository.
clone="$root/clone"
if git clone --quiet --no-hardlinks "$repo" "$clone" 2> /dev/null; then
  builder="$clone/ai-rateb-sa-source/ops/deploy-manifest.py"
  head="$(git -C "$clone" rev-parse HEAD)"
  secret_id="client-secret-test-4e2a.apps.googleusercontent.com"
  ai_key="sk-test-9b8c7d6e5f"
  RATEB_GOOGLE_CLIENT_ID="$secret_id" DEEPSEEK_API_KEY="$ai_key" python3 "$builder" --baseline "$head" --out "$root/b1" > "$root/b1.out" 2>&1; code=$?
  expected_google="$(printf "<?php\ndeclare(strict_types=1);\nreturn ['client_id' => '%s'];\n" "$secret_id")"
  expected_ai="$(printf "<?php\ndeclare(strict_types=1);\nreturn [\n    'deepseek_api_key' => '%s',\n];\n" "$ai_key")"
  ok "builder: builds the bundle from a valid baseline" '[ $code -eq 0 ] && [ -f "$root/b1/bundle.tar.gz" ] && [ "$(tr -d "[:space:]" < "$root/b1/bundle.sha256")" = "$(sha "$root/b1/bundle.tar.gz")" ] && grep -qx "MANIFEST_GENERATED=config/ai.local.php,config/google.php" "$root/b1.out"'
  ok "builder: never prints a secret" '! grep -qF -e "$secret_id" -e "$ai_key" "$root/b1.out"'
  ok "builder: writes the generated configuration exactly as the workflow did before" '[ "$(cat "$root/b1/files/config/google.php")" = "$expected_google" ] && [ "$(cat "$root/b1/files/config/ai.local.php")" = "$expected_ai" ]'
  ok "builder: the local config is placed right after its base file" 'cut -f1 "$root/b1/manifest.tsv" | grep -A1 -x "config/ai.php" | tail -n 1 | grep -qx "config/ai.local.php"'
  python3 "$builder" --baseline "$head" --out "$root/b2" --accept-generated-change > /dev/null 2>&1
  ok "builder: the accept flag allows any content only for generated files" '[ -z "$(awk -F "\t" "\$2 == \"app\" && \$4 ~ /any:/" "$root/b2/manifest.tsv")" ] && [ -z "$(awk -F "\t" "\$2 == \"generated\" && \$4 !~ /any:accepted/" "$root/b2/manifest.tsv")" ] && [ -z "$(awk -F "\t" "\$4 ~ /any:/" "$root/b1/manifest.tsv")" ]'
  ok "builder: deploy.env carries the bootstrap hashes and the media root" 'grep -q "^BOOTSTRAP_SHA256=[0-9a-f]\{64\}" "$root/b1/deploy.env" && grep -qx "MEDIA_ROOT=/home/admin/.campaign-media" "$root/b1/deploy.env"'
  python3 "$builder" --baseline "not-a-sha" --out "$root/b3" > "$root/b3.out" 2>&1; code=$?
  ok "builder: a malformed baseline is refused" '[ $code -ne 0 ] && grep -q "^MANIFEST=error: the baseline must be a full 40-character commit sha" "$root/b3.out" && [ ! -e "$root/b3" ]'
  python3 "$builder" --baseline "0123456789abcdef0123456789abcdef01234567" --out "$root/b4" > "$root/b4.out" 2>&1; code=$?
  ok "builder: a baseline missing from the checkout is refused" '[ $code -ne 0 ] && grep -q "not in this checkout" "$root/b4.out"'
  orphan="$(git -C "$clone" -c user.name=t -c user.email=t@t commit-tree "$head^{tree}" -m orphan)"
  python3 "$builder" --baseline "$orphan" --out "$root/b5" > "$root/b5.out" 2>&1; code=$?
  ok "builder: a baseline that is not an ancestor of the deployed commit is refused" '[ $code -ne 0 ] && grep -q "not an ancestor" "$root/b5.out"'
else
  echo "FAIL builder: could not clone the repository"
  fail=$((fail+1))
fi

chmod -R u+w "$root" 2> /dev/null
rm -rf "$root"
echo "SUMMARY pass=$pass fail=$fail"
[ $fail -eq 0 ]
