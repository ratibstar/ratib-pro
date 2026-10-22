#!/usr/bin/env bash
# Protects the ai.rateb.sa application files during deploy and rollback. The workflow pipes
# it over SSH; it is never uploaded to the web root.
#   bash deploy-guard.sh prepare <target> <run> [<recover-id>]
#       Check the uploaded bundle in ~/.rateb-ai/deploy-<run>, compare every target file with
#       the manifest, then back up the files that will be replaced and verify the backup.
#   bash deploy-guard.sh apply <target> <run> <backup-id> [<recover-id>]
#       Set the worker hold, then swap the staged files in, in manifest order, re-checking
#       each target against the backup right before replacing it.
#   bash deploy-guard.sh release <run> <backup-id> [<recover-id>]
#       Remove the hold after a successful deploy, only if this deploy set or took it over.
#   bash deploy-guard.sh restore <target> <backup-id> [--allow-mismatch]
#       Put a backup back: restore replaced files and delete files the deploy added.
#   bash deploy-guard.sh list <backup-id>
#       Print the files a backup covers.
# Backups live in ~/.rateb-ai/backups/<id> (meta, backup.tsv, files.tar.gz, archive.sha256).
# Nothing in the target changes unless every check before it passed.
# A hold that is already in place (left by a deploy or rollback that stopped midway) is never
# overwritten or removed here: a deploy refuses to start unless <recover-id> names the backup
# the hold records, and a deploy that takes a hold over keeps it if it fails. Only a
# successful deploy (release) or a successful rollback removes it.
set -euo pipefail
umask 077

state="$HOME/.rateb-ai"
backups="$state/backups"
hold="$state/video-worker.hold"
rel_re='^[A-Za-z0-9._-]+(/[A-Za-z0-9._-]+)*$'
sha_re='^[0-9a-f]{64}$'
id_re='^[0-9]{8}T[0-9]{6}Z-[0-9]{1,20}$'
allowed_re='^(any|[0-9a-f]{64}):[a-z]+(,(any|[0-9a-f]{64}):[a-z]+)*$'

die() {
  echo "GUARD=error: $1"
  exit 1
}

for tool in sha256sum tar gzip stat mktemp awk find; do
  command -v "$tool" > /dev/null 2>&1 || die "$tool is not available"
done

sha_of() { sha256sum -- "$1" | awk '{print $1}'; }
mode_of() { stat -c %a -- "$1"; }

valid_rel() {
  [[ "$1" =~ $rel_re ]] || return 1
  case "/$1/" in */../* | */./*) return 1 ;; esac
}

# The backup id a hold records (backup= from a deploy, restore= from a rollback), or nothing.
hold_id() {
  [ -f "$hold" ] || return 0
  sed -n -E 's/^(backup|restore)=([0-9]{8}T[0-9]{6}Z-[0-9]{1,20})$/\2/p' "$hold" | head -n 1 || true
}

held_elsewhere() {
  local held
  held="$(hold_id)"
  echo "HOLD=kept"
  echo "HOLD_ID=${held:-unknown}"
  die "a hold left by an earlier deploy or rollback is in place; it stays until a rollback succeeds or a deploy run with recover_backup_id=${held:-<the backup id it records>} succeeds; nothing changed"
}

# check_hold <recover-id>: without a hold, no recover id may be given; with one, the recover id
# must name the backup the hold records.
check_hold() {
  if [ -e "$hold" ] || [ -L "$hold" ]; then
    if [ -n "$1" ] && [ -f "$hold" ] && [ "$(hold_id)" = "$1" ]; then
      echo "HOLD=adopted"
      echo "HOLD_ID=$1"
      return 0
    fi
    held_elsewhere
  fi
  [ -z "$1" ] || die "recover_backup_id is set but no hold is in place; nothing changed"
}

check_target() {
  local home_real
  home_real="$(cd "$HOME" && pwd -P)"
  case "$1" in
    "$home_real/domains/ai.rateb.sa" | "$home_real/domains/ai.rateb.sa/public_html" | "$home_real/domains/ai.rateb.sa/private_html") ;;
    *) die "the target is not ai.rateb.sa inside this account's home directory" ;;
  esac
  [ -d "$1" ] && [ "$(cd "$1" && pwd -P)" = "$1" ] || die "the target is not a real directory"
  [ "$(stat -c %u -- "$1")" = "$(id -u)" ] || die "the target is not owned by the deploying account"
}

# Every existing folder between the target and the file is a real folder, and the file
# itself is not a link.
safe_path() {
  local dir="$1" rest="$2" part
  while [[ "$rest" == */* ]]; do
    part="${rest%%/*}"
    rest="${rest#*/}"
    dir="$dir/$part"
    [ -L "$dir" ] && return 1
    if [ -e "$dir" ] && [ ! -d "$dir" ]; then return 1; fi
  done
  [ ! -L "$dir/$rest" ]
}

# state_matches <path> <present|absent> <sha>
state_matches() {
  if [ "$2" = present ]; then
    [ -f "$1" ] && [ ! -L "$1" ] && [ "$(sha_of "$1")" = "$3" ]
  else
    [ ! -e "$1" ] && [ ! -L "$1" ]
  fi
}

# allowed_label <sha> <sha:label,...>: print the reason the first matching entry gives.
allowed_label() {
  local item
  local -a items
  IFS=',' read -r -a items <<< "$2"
  for item in "${items[@]}"; do
    if [ "${item%%:*}" = "$1" ] || [ "${item%%:*}" = any ]; then
      echo "${item#*:}"
      return 0
    fi
  done
  return 1
}

load_manifest() {
  local rel kind digest allowed absent extra
  local -A seen=()
  M_REL=()
  M_KIND=()
  M_SHA=()
  M_ALLOWED=()
  M_NEW=()
  [ -f "$1" ] || die "the bundle has no manifest"
  while IFS=$'\t' read -r rel kind digest allowed absent extra; do
    [ -z "${extra:-}" ] || die "malformed manifest line"
    valid_rel "$rel" || die "unsafe path in the manifest"
    [ -z "${seen[$rel]:-}" ] || die "the manifest lists $rel twice"
    seen[$rel]=1
    [[ "$kind" =~ ^(app|generated)$ ]] || die "unknown kind for $rel"
    [[ "$digest" =~ $sha_re ]] || die "bad sha256 for $rel"
    [[ "$allowed" =~ $allowed_re ]] || die "bad allowed list for $rel"
    [[ "$absent" =~ ^[01]$ ]] || die "bad absent flag for $rel"
    if [ "$kind" = app ] && [[ "$allowed" == *any:* ]]; then die "only generated files may accept any content"; fi
    M_REL+=("$rel")
    M_KIND+=("$kind")
    M_SHA+=("$digest")
    M_ALLOWED+=("$allowed")
    M_NEW+=("$absent")
  done < "$1"
  [ "${#M_REL[@]}" -gt 0 ] || die "the manifest is empty"
}

load_backup() {
  local dir="$backups/$1" rel st digest mode after
  B_REL=()
  declare -gA B_ST=() B_SHA=() B_MODE=() B_AFTER=()
  while IFS=$'\t' read -r rel st digest mode after; do
    valid_rel "$rel" || die "unsafe path in backup $1"
    B_REL+=("$rel")
    B_ST[$rel]="$st"
    B_SHA[$rel]="$digest"
    B_MODE[$rel]="$mode"
    B_AFTER[$rel]="$after"
  done < "$dir/backup.tsv"
}

# verify_backup <id> <target>: the archive matches its checksum and holds exactly the
# recorded files with the recorded content and mode.
verify_backup() {
  local id="$1" target="$2" dir chk rel st digest mode after count=0 found f
  [[ "$id" =~ $id_re ]] || die "invalid backup id"
  dir="$backups/$id"
  for f in meta backup.tsv files.tar.gz archive.sha256; do
    [ -f "$dir/$f" ] || die "backup $id is incomplete"
  done
  grep -qxF "target=$target" "$dir/meta" || die "backup $id was taken from a different target"
  [ "$(sha_of "$dir/files.tar.gz")" = "$(tr -d '[:space:]' < "$dir/archive.sha256")" ] || die "backup $id does not match its checksum"
  chk="$(mktemp -d "$dir/verify.XXXXXX")"
  if ! tar -xzpf "$dir/files.tar.gz" -C "$chk" --no-same-owner 2> /dev/null; then
    rm -rf "$chk"
    die "backup $id cannot be unpacked"
  fi
  while IFS=$'\t' read -r rel st digest mode after; do
    if ! valid_rel "$rel"; then rm -rf "$chk"; die "unsafe path in backup $id"; fi
    if [ "$st" = present ]; then
      if [ ! -f "$chk/$rel" ] || [ -L "$chk/$rel" ] || [ "$(sha_of "$chk/$rel")" != "$digest" ] || [ "$(mode_of "$chk/$rel")" != "$mode" ]; then
        rm -rf "$chk"
        die "backup $id does not hold the recorded copy of $rel"
      fi
      count=$((count + 1))
    elif [ "$st" = absent ]; then
      if [ -e "$chk/$rel" ]; then rm -rf "$chk"; die "backup $id holds $rel, which it records as absent"; fi
    else
      rm -rf "$chk"
      die "backup $id has an unknown state for $rel"
    fi
  done < "$dir/backup.tsv"
  found="$(find "$chk" ! -type d | wc -l | tr -d ' ')"
  rm -rf "$chk"
  [ "$found" = "$count" ] || die "backup $id holds files it does not record"
  echo "BACKUP_VERIFIED=yes"
}

# write_backup <target> <id> <list>: list lines are "rel<TAB>sha the next step writes, or -".
write_backup() {
  local target="$1" id="$2" list="$3" dir rel after n=0
  dir="$backups/$id"
  mkdir -p "$backups"
  chmod 700 "$state" "$backups"
  mkdir -m 700 "$dir" 2> /dev/null || die "backup $id already exists"
  : > "$dir/backup.tsv"
  : > "$dir/present.list"
  while IFS=$'\t' read -r rel after; do
    if [ -f "$target/$rel" ] && [ ! -L "$target/$rel" ]; then
      printf '%s\tpresent\t%s\t%s\t%s\n' "$rel" "$(sha_of "$target/$rel")" "$(mode_of "$target/$rel")" "$after" >> "$dir/backup.tsv"
      printf '%s\n' "$rel" >> "$dir/present.list"
      n=$((n + 1))
    else
      printf '%s\tabsent\t-\t-\t%s\n' "$rel" "$after" >> "$dir/backup.tsv"
    fi
  done < "$list"
  printf 'target=%s\ncreated=%s\n' "$target" "$(date -u +%Y-%m-%dT%H:%M:%SZ)" > "$dir/meta"
  tar -C "$target" -czpf "$dir/files.tar.gz" --no-recursion -T "$dir/present.list" || die "the backup archive could not be written"
  sha_of "$dir/files.tar.gz" > "$dir/archive.sha256"
  chmod 600 "$dir"/*
  verify_backup "$id" "$target"
  echo "BACKUP_FILES=$n"
}

cmd_prepare() {
  local target="$1" run="$2" recover="$3" work stage expected entry i rel kind dst cur label failed=0 list id staged
  check_target "$target"
  [[ "$run" =~ ^[0-9]{1,20}$ ]] || die "invalid run id"
  [ -z "$recover" ] || [[ "$recover" =~ $id_re ]] || die "invalid recover backup id"
  check_hold "$recover"
  work="$state/deploy-$run"
  stage="$work/stage"
  [ -f "$work/bundle.tar.gz" ] && [ -f "$work/bundle.sha256" ] || die "the bundle was not uploaded"
  expected="$(tr -d '[:space:]' < "$work/bundle.sha256")"
  [[ "$expected" =~ $sha_re ]] && [ "$(sha_of "$work/bundle.tar.gz")" = "$expected" ] || die "the uploaded bundle does not match its checksum"
  tar -tzf "$work/bundle.tar.gz" > "$work/entries" 2> /dev/null || die "the bundle cannot be read"
  while IFS= read -r entry; do
    case "$entry" in
      manifest.tsv) ;;
      files/*) valid_rel "${entry#files/}" || die "the bundle has an unsafe path" ;;
      *) die "the bundle has an unexpected entry" ;;
    esac
  done < "$work/entries"
  rm -rf "$stage"
  mkdir -m 700 "$stage"
  tar -xzf "$work/bundle.tar.gz" -C "$stage" --no-same-owner --no-same-permissions || die "the bundle could not be unpacked"
  [ -z "$(find "$stage" ! -type f ! -type d -print -quit)" ] || die "the bundle contains links or special files"
  [ "$(stat -c %d -- "$stage")" = "$(stat -c %d -- "$target")" ] || die "the staging folder and the target are on different filesystems, so files cannot be swapped in atomically"
  load_manifest "$stage/manifest.tsv"
  staged="$(find "$stage/files" -type f 2> /dev/null | wc -l | tr -d ' ')"
  [ "$staged" = "${#M_REL[@]}" ] || die "the bundle and its manifest disagree"
  for i in "${!M_REL[@]}"; do
    rel="${M_REL[$i]}"
    [ -f "$stage/files/$rel" ] && [ "$(sha_of "$stage/files/$rel")" = "${M_SHA[$i]}" ] || die "the staged copy of $rel does not match the manifest"
  done

  for i in "${!M_REL[@]}"; do
    rel="${M_REL[$i]}"
    kind="${M_KIND[$i]}"
    dst="$target/$rel"
    if ! safe_path "$target" "$rel"; then
      echo "DRIFT=$rel FAILED a folder on its path, or the file, is a symbolic link"
      failed=1
    elif [ -e "$dst" ]; then
      if [ ! -f "$dst" ]; then
        echo "DRIFT=$rel FAILED it is not a regular file"
        failed=1
        continue
      fi
      cur="$(sha_of "$dst")"
      if label="$(allowed_label "$cur" "${M_ALLOWED[$i]}")"; then
        echo "DRIFT=$rel ok $label"
      elif [ "$kind" = generated ]; then
        echo "DRIFT=$rel FAILED this configuration file differs from what the deploy writes from secrets; rerun with accept_generated_config_change only if the secret was rotated on purpose"
        failed=1
      else
        echo "DRIFT=$rel FAILED unexpected content sha256=$cur; it matches neither the approved baseline nor this deploy"
        failed=1
      fi
    elif [ "${M_NEW[$i]}" = 1 ]; then
      echo "DRIFT=$rel ok new"
    else
      echo "DRIFT=$rel FAILED missing on the server although the approved baseline has it"
      failed=1
    fi
  done
  if [ "$failed" -ne 0 ]; then
    echo "DRIFT_OK=no"
    exit 1
  fi
  echo "DRIFT_OK=yes"

  list="$work/backup-list"
  : > "$list"
  for i in "${!M_REL[@]}"; do
    printf '%s\t%s\n' "${M_REL[$i]}" "${M_SHA[$i]}" >> "$list"
  done
  id="$(date -u +%Y%m%dT%H%M%SZ)-$run"
  write_backup "$target" "$id" "$list"
  echo "BACKUP_ID=$id"
}

cmd_apply() {
  local target="$1" run="$2" id="$3" recover="$4" stage i rel dst src parent mode mine
  check_target "$target"
  [[ "$run" =~ ^[0-9]{1,20}$ ]] || die "invalid run id"
  [[ "$id" =~ $id_re ]] || die "invalid backup id"
  [ -z "$recover" ] || [[ "$recover" =~ $id_re ]] || die "invalid recover backup id"
  stage="$state/deploy-$run/stage"
  load_manifest "$stage/manifest.tsv"
  verify_backup "$id" "$target"
  load_backup "$id"
  for i in "${!M_REL[@]}"; do
    rel="${M_REL[$i]}"
    [ -n "${B_ST[$rel]:-}" ] && [ "${B_AFTER[$rel]}" = "${M_SHA[$i]}" ] || die "backup $id does not cover this bundle"
    [ -f "$stage/files/$rel" ] || die "the staged copy of $rel is missing"
  done

  APPLIED=0
  OWN_HOLD=0
  mine="$(printf 'backup=%s\nrun=%s' "$id" "$run")"
  # Only a hold this run created, still unchanged, is removed when nothing was replaced.
  stop() {
    echo "GUARD=error: $1"
    echo "FILES_CHANGED=$APPLIED"
    if [ "$APPLIED" -eq 0 ] && [ "$OWN_HOLD" = 1 ] && [ -f "$hold" ] && [ "$(cat "$hold")" = "$mine" ]; then
      rm -f "$hold"
      echo "HOLD=cleared"
    else
      echo "HOLD=kept"
    fi
    exit 1
  }
  mkdir -p "$state"
  if [ -n "$recover" ]; then
    if ! { [ -f "$hold" ] && [ "$(hold_id)" = "$recover" ]; }; then
      echo "FILES_CHANGED=0"
      [ -e "$hold" ] || die "recover_backup_id is set but no hold is in place; nothing changed"
      held_elsewhere
    fi
    echo "HOLD=adopted"
  else
    # noclobber: the hold is created only if none exists, so an earlier one is never replaced.
    if [ -e "$hold" ] || [ -L "$hold" ] || ! (set -C && printf '%s\n' "$mine" > "$hold") 2> /dev/null; then
      echo "FILES_CHANGED=0"
      held_elsewhere
    fi
    OWN_HOLD=1
    echo "HOLD=set"
  fi
  for rel in "${M_REL[@]}"; do
    state_matches "$target/$rel" "${B_ST[$rel]}" "${B_SHA[$rel]}" || stop "$rel changed after the backup was taken"
  done
  for i in "${!M_REL[@]}"; do
    rel="${M_REL[$i]}"
    dst="$target/$rel"
    src="$stage/files/$rel"
    if [ "${B_ST[$rel]}" = present ] && [ "${B_SHA[$rel]}" = "${M_SHA[$i]}" ]; then
      echo "UNCHANGED=$rel"
      continue
    fi
    state_matches "$dst" "${B_ST[$rel]}" "${B_SHA[$rel]}" || stop "$rel changed after the backup was taken"
    safe_path "$target" "$rel" || stop "a folder on the path to $rel is a symbolic link"
    parent="$(dirname "$dst")"
    if [ ! -d "$parent" ]; then
      (umask 022 && mkdir -p -- "$parent") || stop "could not create the folder for $rel"
    fi
    mode=644
    if [ "${B_ST[$rel]}" = present ]; then mode="${B_MODE[$rel]}"; fi
    chmod "$mode" -- "$src" || stop "could not set the mode for $rel"
    mv -fT -- "$src" "$dst" 2> /dev/null || stop "could not replace $rel"
    APPLIED=$((APPLIED + 1))
    [ "$(sha_of "$dst")" = "${M_SHA[$i]}" ] || stop "$rel does not match the bundle after it was replaced"
    echo "APPLIED=$rel"
  done
  echo "FILES_CHANGED=$APPLIED"
  echo "APPLY_OK=yes"
}

cmd_restore() {
  local target="$1" id="$2" allow="$3" dir rel dst problems=0 mismatched=0 list pre ext restored=0 removed=0
  check_target "$target"
  [[ "$id" =~ $id_re ]] || die "invalid backup id"
  [ -z "$allow" ] || [ "$allow" = --allow-mismatch ] || die "unknown restore option"
  dir="$backups/$id"
  verify_backup "$id" "$target"
  [ "$(stat -c %d -- "$dir")" = "$(stat -c %d -- "$target")" ] || die "the backup and the target are on different filesystems, so files cannot be swapped back atomically"
  load_backup "$id"
  for rel in "${B_REL[@]}"; do
    dst="$target/$rel"
    if ! safe_path "$target" "$rel"; then
      echo "ROLLBACK_FILE=$rel FAILED a folder on its path, or the file, is a symbolic link"
      problems=1
    elif state_matches "$dst" "${B_ST[$rel]}" "${B_SHA[$rel]}"; then
      echo "ROLLBACK_FILE=$rel ok already the backed-up state"
    elif [ "${B_AFTER[$rel]}" != - ] && state_matches "$dst" present "${B_AFTER[$rel]}"; then
      echo "ROLLBACK_FILE=$rel ok the deployed version"
    elif [ ! -e "$dst" ] && [ ! -L "$dst" ]; then
      echo "ROLLBACK_FILE=$rel ok missing now"
    elif [ ! -f "$dst" ]; then
      echo "ROLLBACK_FILE=$rel FAILED it is not a regular file"
      problems=1
    else
      echo "ROLLBACK_FILE=$rel MISMATCH it changed after the deploy"
      mismatched=1
    fi
  done
  [ "$problems" -eq 0 ] || die "the restore was refused; nothing changed"
  if [ "$mismatched" -ne 0 ] && [ -z "$allow" ]; then
    echo "ROLLBACK_OK=no"
    die "files changed after the deploy; review them, then rerun with rollback_allow_mismatch (they are backed up before being restored); nothing changed"
  fi

  list="$(mktemp "$state/restore-list.XXXXXX")"
  for rel in "${B_REL[@]}"; do
    printf '%s\t%s\n' "$rel" "${B_SHA[$rel]}" >> "$list"
  done
  pre="$(date -u +%Y%m%dT%H%M%SZ)-$$"
  write_backup "$target" "$pre" "$list"
  rm -f "$list"
  echo "PRE_RESTORE_BACKUP=$pre"
  if [ -e "$hold" ] || [ -L "$hold" ]; then
    echo "HOLD=kept"
  else
    (set -C && printf 'restore=%s\n' "$id" > "$hold") 2> /dev/null || die "could not set the worker hold; nothing was restored"
    echo "HOLD=set"
  fi

  ext="$(mktemp -d "$dir/restore.XXXXXX")"
  tar -xzpf "$dir/files.tar.gz" -C "$ext" --no-same-owner || die "backup $id could not be unpacked"
  for rel in "${B_REL[@]}"; do
    dst="$target/$rel"
    if [ "${B_ST[$rel]}" = present ]; then
      if state_matches "$dst" present "${B_SHA[$rel]}" && [ "$(mode_of "$dst")" = "${B_MODE[$rel]}" ]; then
        continue
      fi
      if [ ! -d "$(dirname "$dst")" ]; then
        (umask 022 && mkdir -p -- "$(dirname "$dst")") || die "could not create the folder for $rel"
      fi
      mv -fT -- "$ext/$rel" "$dst" 2> /dev/null || die "could not restore $rel"
      [ "$(sha_of "$dst")" = "${B_SHA[$rel]}" ] && [ "$(mode_of "$dst")" = "${B_MODE[$rel]}" ] || die "$rel does not match the backup after restoring"
      restored=$((restored + 1))
      echo "RESTORED_FILE=$rel"
    elif [ -e "$dst" ]; then
      rm -f -- "$dst"
      [ ! -e "$dst" ] || die "could not remove $rel"
      removed=$((removed + 1))
      echo "REMOVED_FILE=$rel"
    fi
  done
  rm -rf "$ext"
  echo "RESTORED=$restored"
  echo "REMOVED=$removed"
  echo "ROLLBACK_OK=yes"
}

cmd_release() {
  local run="$1" id="$2" recover="$3"
  [[ "$run" =~ ^[0-9]{1,20}$ ]] || die "invalid run id"
  [[ "$id" =~ $id_re ]] || die "invalid backup id"
  [ -z "$recover" ] || [[ "$recover" =~ $id_re ]] || die "invalid recover backup id"
  if [ ! -e "$hold" ] && [ ! -L "$hold" ]; then
    echo "HOLD=absent"
    return 0
  fi
  if [ -n "$recover" ]; then
    [ -f "$hold" ] && [ "$(hold_id)" = "$recover" ] || { echo "HOLD=kept"; die "the hold records another deploy or rollback; it stays"; }
  else
    [ -f "$hold" ] && [ "$(cat "$hold")" = "$(printf 'backup=%s\nrun=%s' "$id" "$run")" ] || { echo "HOLD=kept"; die "the hold was not set by this deploy; it stays"; }
  fi
  rm -f "$hold"
  echo "HOLD=released"
}

cmd_list() {
  [[ "$1" =~ $id_re ]] || die "invalid backup id"
  [ -f "$backups/$1/backup.tsv" ] || die "backup $1 does not exist"
  awk -F '\t' '{ print "BACKUP_FILE=" $1 }' "$backups/$1/backup.tsv"
}

case "${1:-}" in
  prepare)
    [ $# -eq 3 ] || [ $# -eq 4 ] || die "usage: prepare <target> <run> [<recover-id>]"
    cmd_prepare "$2" "$3" "${4:-}"
    ;;
  apply)
    [ $# -eq 4 ] || [ $# -eq 5 ] || die "usage: apply <target> <run> <backup-id> [<recover-id>]"
    cmd_apply "$2" "$3" "$4" "${5:-}"
    ;;
  release)
    [ $# -eq 3 ] || [ $# -eq 4 ] || die "usage: release <run> <backup-id> [<recover-id>]"
    cmd_release "$2" "$3" "${4:-}"
    ;;
  restore)
    [ $# -eq 3 ] || [ $# -eq 4 ] || die "usage: restore <target> <backup-id> [--allow-mismatch]"
    cmd_restore "$2" "$3" "${4:-}"
    ;;
  list)
    [ $# -eq 2 ] || die "usage: list <backup-id>"
    cmd_list "$2"
    ;;
  *)
    die "usage: deploy-guard.sh prepare|apply|release|restore|list"
    ;;
esac
