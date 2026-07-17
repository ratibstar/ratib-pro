#!/usr/bin/env python3
"""
PX-Deploy — Permanent Deployment Integrity

Git HEAD is the single source of truth for managed production trees.
Additive rsync/Fileman uploads alone are insufficient: remote orphans must be
detected, deleted, verified gone, and the managed tree hash must match Git.
"""
from __future__ import annotations

import hashlib
import json
import os
import shlex
import subprocess
import tempfile
from dataclasses import asdict, dataclass, field
from datetime import datetime, timezone
from typing import Iterable


# Fully Git-owned trees. Remote files under these roots that are not in HEAD
# are orphans and must be deleted.
INTEGRITY_MANAGED_ROOTS = (
    "rateb-erp/public",
    "public",
    "js",
    "css",
    "pages",
    "includes",
    "api",
    "control-panel",
    "modules/infrastructure-marketplace",
    "rateb-platform-catalog/public",
    "ratib-contact-center/public",
    "config/env",
    "app/Accounting",
)

# Runtime / user-data / non-Git trees — never orphan-scanned for deletion.
INTEGRITY_EXCLUDE_PREFIXES = (
    "rateb-erp/storage/",
    "storage/",
    "uploads/",
    "Designed/",
    ".git/",
    ".github/",
    ".cursor/",
    "archive/",
    "node_modules/",
    "rateb-erp/tools/boot-bench/.chrome-user-data/",
    "rateb-erp/capacitor/",
)

SERVICE_WORKER_NAMES = frozenset({
    "sw.js",
    "pos-sw.js",
    "rateb-offline-sw.js",
    "service-worker.js",
})


@dataclass
class OrphanRecord:
    path: str
    kind: str
    bytes: int = 0
    deleted: bool = False
    verified_absent: bool = False


@dataclass
class IntegrityReport:
    phase: str = "PX-Deploy"
    generated_at: str = ""
    remote_base: str = ""
    git_sha: str = ""
    managed_roots: list[str] = field(default_factory=list)
    expected_file_count: int = 0
    remote_file_count: int = 0
    commit_deleted: list[str] = field(default_factory=list)
    explicit_purge: list[str] = field(default_factory=list)
    orphans_found: list[OrphanRecord] = field(default_factory=list)
    orphan_directories_removed: list[str] = field(default_factory=list)
    hash_mismatches: list[dict] = field(default_factory=list)
    missing_expected: list[str] = field(default_factory=list)
    local_tree_hash: str = ""
    remote_tree_hash: str = ""
    ok: bool = False
    errors: list[str] = field(default_factory=list)

    def to_dict(self) -> dict:
        return asdict(self)


def _norm(path: str) -> str:
    return path.replace("\\", "/").lstrip("./")


def _excluded(path: str) -> bool:
    p = _norm(path)
    for prefix in INTEGRITY_EXCLUDE_PREFIXES:
        if p == prefix.rstrip("/") or p.startswith(prefix):
            return True
    if "/node_modules/" in f"/{p}/":
        return True
    return False


def classify_path(path: str) -> str:
    name = os.path.basename(path).lower()
    if name in SERVICE_WORKER_NAMES or name.endswith("-sw.js"):
        return "service_worker"
    if name.endswith(".webmanifest") or name == "manifest.json":
        return "manifest"
    ext = os.path.splitext(name)[1].lower()
    mapping = {
        ".js": "js",
        ".mjs": "js",
        ".cjs": "js",
        ".css": "css",
        ".php": "php",
        ".wasm": "wasm",
        ".json": "json",
        ".html": "html",
        ".htm": "html",
        ".md": "doc",
        ".txt": "doc",
        ".svg": "asset",
        ".map": "map",
        ".mts": "types",
        ".ts": "types",
    }
    return mapping.get(ext, "other")


def git_sha() -> str:
    env = os.environ.get("GITHUB_SHA", "").strip()
    if env:
        return env
    try:
        return subprocess.check_output(
            ["git", "rev-parse", "HEAD"], text=True
        ).strip()
    except Exception:
        return ""


def git_ls_files_under(roots: Iterable[str]) -> list[str]:
    out: list[str] = []
    for root in roots:
        try:
            raw = subprocess.check_output(
                ["git", "ls-files", "-z", "--", root],
                text=False,
            )
        except subprocess.CalledProcessError:
            continue
        for item in raw.split(b"\0"):
            if not item:
                continue
            path = _norm(item.decode("utf-8", errors="replace"))
            if _excluded(path):
                continue
            if os.path.isfile(path):
                out.append(path)
    return sorted(set(out))


def sha256_file(path: str) -> str:
    digest = hashlib.sha256()
    with open(path, "rb") as handle:
        while True:
            chunk = handle.read(1024 * 1024)
            if not chunk:
                break
            digest.update(chunk)
    return digest.hexdigest()


def git_head_sha256(path: str) -> str:
    """Hash of the Git HEAD blob (repository truth), not the possibly CRLF working tree."""
    data = subprocess.check_output(["git", "show", f"HEAD:{path}"])
    return hashlib.sha256(data).hexdigest()


def local_manifest(paths: Iterable[str]) -> dict[str, str]:
    manifest: dict[str, str] = {}
    for path in paths:
        norm = _norm(path)
        try:
            manifest[norm] = git_head_sha256(norm)
        except subprocess.CalledProcessError:
            if os.path.isfile(norm):
                manifest[norm] = sha256_file(norm)
    return manifest


def tree_hash(manifest: dict[str, str]) -> str:
    digest = hashlib.sha256()
    for path in sorted(manifest):
        line = f"{path}\0{manifest[path]}\n".encode("utf-8")
        digest.update(line)
    return digest.hexdigest()


def git_deleted_paths_for_sha(sha: str) -> list[str]:
    if not sha:
        return []
    try:
        parents = subprocess.check_output(
            ["git", "rev-list", "--parents", "-n", "1", sha],
            text=True,
        ).strip().split()
    except subprocess.CalledProcessError:
        return []
    if len(parents) < 2:
        return []
    parent = parents[1]
    try:
        out = subprocess.check_output(
            [
                "git",
                "diff-tree",
                "-r",
                "--diff-filter=D",
                "--name-only",
                parent,
                sha,
            ],
            text=True,
        )
    except subprocess.CalledProcessError:
        return []
    deleted = []
    for line in out.splitlines():
        path = _norm(line.strip())
        if path and not _excluded(path):
            deleted.append(path)
    return sorted(set(deleted))


def explicit_purge_paths(core_module) -> list[str]:
    paths: list[str] = []
    for name in (
        "SECURITY_REMOTE_DELETE_FILES",
        "EXTRACTION_ROLLBACK_REMOTE_DELETE_FILES",
    ):
        values = getattr(core_module, name, None) or []
        for rel in values:
            path = _norm(str(rel))
            if path:
                paths.append(path)
    return sorted(set(paths))


def write_report(report: IntegrityReport, dest: str | None = None) -> str:
    if not dest:
        dest = os.environ.get(
            "DEPLOY_ORPHAN_REPORT",
            os.path.join(os.getcwd(), "deploy-orphan-report.json"),
        )
    report.generated_at = datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")
    abs_dest = os.path.abspath(dest)
    parent = os.path.dirname(abs_dest)
    if parent:
        os.makedirs(parent, exist_ok=True)
    with open(abs_dest, "w", encoding="utf-8") as handle:
        json.dump(report.to_dict(), handle, indent=2, sort_keys=True)
        handle.write("\n")
    print(f"orphan_report={abs_dest}", flush=True)
    print(json.dumps({
        "ok": report.ok,
        "orphans": len(report.orphans_found),
        "hash_mismatches": len(report.hash_mismatches),
        "missing_expected": len(report.missing_expected),
        "local_tree_hash": report.local_tree_hash,
        "remote_tree_hash": report.remote_tree_hash,
        "errors": report.errors[:10],
    }, indent=2), flush=True)
    return abs_dest


def _ssh_run(
    key_path: str,
    remote_cmd: str,
    host: str,
    user: str,
    port: str,
) -> subprocess.CompletedProcess[str]:
    cmd = [
        "ssh",
        "-i",
        key_path,
        "-p",
        port,
        "-o",
        "BatchMode=yes",
        "-o",
        "IdentitiesOnly=yes",
        "-o",
        "StrictHostKeyChecking=accept-new",
        f"{user}@{host}",
        remote_cmd,
    ]
    return subprocess.run(cmd, text=True, capture_output=True)


def build_expected_manifest() -> dict[str, str]:
    paths = git_ls_files_under(INTEGRITY_MANAGED_ROOTS)
    return local_manifest(paths)


def ssh_credentials_from_env() -> tuple[str, str, str] | None:
    host = (os.environ.get("DEPLOY_SSH_HOST") or os.environ.get("SSH_HOST") or "").strip()
    user = (os.environ.get("DEPLOY_SSH_USER") or os.environ.get("SSH_USER") or "").strip()
    port = (os.environ.get("DEPLOY_SSH_PORT") or os.environ.get("SSH_PORT") or "22").strip()
    key = (
        os.environ.get("DEPLOY_SSH_PRIVATE_KEY")
        or os.environ.get("SSH_PRIVATE_KEY")
        or ""
    ).strip()
    if not host or not user or not key:
        return None
    return host, user, port


def run_ssh_integrity(
    *,
    key_path: str,
    remote_base: str,
    host: str,
    user: str,
    port: str,
    expected: dict[str, str],
    force_delete: list[str],
) -> IntegrityReport:
    """
    Upload expected manifest, delete orphans + force_delete, verify, hash-check.
    """
    report = IntegrityReport(
        remote_base=remote_base.rstrip("/"),
        git_sha=git_sha(),
        managed_roots=list(INTEGRITY_MANAGED_ROOTS),
        expected_file_count=len(expected),
        commit_deleted=git_deleted_paths_for_sha(git_sha()),
        explicit_purge=list(force_delete),
        local_tree_hash=tree_hash(expected),
    )

    base = remote_base.rstrip("/")
    fd_man, man_path = tempfile.mkstemp(prefix="rateb-integrity-manifest-", suffix=".txt")
    os.close(fd_man)
    fd_del, del_path = tempfile.mkstemp(prefix="rateb-integrity-delete-", suffix=".txt")
    os.close(fd_del)
    remote_man = f"/tmp/rateb-integrity-manifest-{os.getpid()}.txt"
    remote_del = f"/tmp/rateb-integrity-delete-{os.getpid()}.txt"
    remote_out = f"/tmp/rateb-integrity-out-{os.getpid()}.json"
    try:
        with open(man_path, "w", encoding="utf-8", newline="\n") as handle:
            for path in sorted(expected):
                handle.write(f"{path}\t{expected[path]}\n")
        with open(del_path, "w", encoding="utf-8", newline="\n") as handle:
            for path in sorted(set(force_delete) | set(report.commit_deleted)):
                handle.write(path + "\n")

        for local, remote in ((man_path, remote_man), (del_path, remote_del)):
            scp_cmd = [
                "scp",
                "-i", key_path,
                "-P", port,
                "-o", "BatchMode=yes",
                "-o", "IdentitiesOnly=yes",
                "-o", "StrictHostKeyChecking=accept-new",
                local,
                f"{user}@{host}:{remote}",
            ]
            proc = subprocess.run(scp_cmd, text=True, capture_output=True)
            if proc.returncode != 0:
                report.errors.append(
                    "scp_failed:" + ((proc.stderr or proc.stdout or "").strip())
                )
                report.ok = False
                return report

        remote_script = f"""
set -euo pipefail
python3 - <<'PY'
import hashlib, json, os
base = {json.dumps(base)}
man_path = {json.dumps(remote_man)}
del_path = {json.dumps(remote_del)}
out_path = {json.dumps(remote_out)}
roots = {json.dumps(list(INTEGRITY_MANAGED_ROOTS))}
excludes = {json.dumps(list(INTEGRITY_EXCLUDE_PREFIXES))}

def norm(p):
    return p.replace('\\\\', '/').lstrip('./')

def excluded(p):
    p = norm(p)
    for prefix in excludes:
        if p == prefix.rstrip('/') or p.startswith(prefix):
            return True
    if '/node_modules/' in ('/' + p + '/'):
        return True
    return False

expected = {{}}
with open(man_path, 'r', encoding='utf-8') as fh:
    for line in fh:
        line = line.strip()
        if not line or '\\t' not in line:
            continue
        path, digest = line.split('\\t', 1)
        expected[norm(path)] = digest.strip()

force = []
with open(del_path, 'r', encoding='utf-8') as fh:
    for line in fh:
        path = norm(line.strip())
        if path:
            force.append(path)

remote_files = []
for root in roots:
    abs_root = os.path.join(base, root)
    if not os.path.isdir(abs_root):
        continue
    for dirpath, dirnames, filenames in os.walk(abs_root):
        dirnames[:] = [d for d in dirnames if d not in ('node_modules', '.git')]
        for name in filenames:
            abs_path = os.path.join(dirpath, name)
            rel = norm(os.path.relpath(abs_path, base))
            if excluded(rel):
                continue
            remote_files.append(rel)

expected_set = set(expected)
orphan_paths = sorted(set(remote_files) - expected_set)
delete_targets = sorted(set(orphan_paths) | set(force))

orphans = []
for rel in delete_targets:
    abs_path = os.path.join(base, rel)
    existed = os.path.lexists(abs_path)
    size = os.path.getsize(abs_path) if existed and os.path.isfile(abs_path) else 0
    if existed and os.path.isfile(abs_path):
        try:
            os.remove(abs_path)
        except Exception as exc:
            orphans.append({{
                'path': rel,
                'kind': 'delete_error',
                'bytes': size,
                'deleted': False,
                'verified_absent': False,
                'error': str(exc),
            }})
            continue
    verified = not os.path.lexists(abs_path)
    if rel in orphan_paths or existed:
        orphans.append({{
            'path': rel,
            'kind': 'orphan' if rel in orphan_paths else 'force_purge',
            'bytes': size,
            'deleted': bool(existed and verified),
            'verified_absent': verified,
        }})

removed_dirs = []
for root in roots:
    abs_root = os.path.join(base, root)
    if not os.path.isdir(abs_root):
        continue
    for dirpath, dirnames, filenames in os.walk(abs_root, topdown=False):
        rel = norm(os.path.relpath(dirpath, base))
        if excluded(rel) or rel == norm(root):
            continue
        try:
            if not os.listdir(dirpath):
                os.rmdir(dirpath)
                removed_dirs.append(rel)
        except OSError:
            pass

remote_manifest = {{}}
missing = []
mismatches = []
for rel, want in expected.items():
    abs_path = os.path.join(base, rel)
    if not os.path.isfile(abs_path):
        missing.append(rel)
        continue
    h = hashlib.sha256()
    with open(abs_path, 'rb') as fh:
        while True:
            chunk = fh.read(1024 * 1024)
            if not chunk:
                break
            h.update(chunk)
    got = h.hexdigest()
    remote_manifest[rel] = got
    if got != want:
        mismatches.append({{'path': rel, 'local': want, 'remote': got}})

still = []
for root in roots:
    abs_root = os.path.join(base, root)
    if not os.path.isdir(abs_root):
        continue
    for dirpath, dirnames, filenames in os.walk(abs_root):
        dirnames[:] = [d for d in dirnames if d not in ('node_modules', '.git')]
        for name in filenames:
            abs_path = os.path.join(dirpath, name)
            rel = norm(os.path.relpath(abs_path, base))
            if excluded(rel):
                continue
            if rel not in expected_set:
                still.append(rel)

def tree_hash(manifest):
    d = hashlib.sha256()
    for path in sorted(manifest):
        d.update((path + '\\0' + manifest[path] + '\\n').encode('utf-8'))
    return d.hexdigest()

payload = {{
    'remote_file_count_before': len(remote_files),
    'orphans': orphans,
    'orphan_directories_removed': removed_dirs,
    'still_orphans': still,
    'missing_expected': missing,
    'hash_mismatches': mismatches,
    'remote_tree_hash': tree_hash(remote_manifest),
    'remote_manifest_count': len(remote_manifest),
}}
with open(out_path, 'w', encoding='utf-8') as fh:
    json.dump(payload, fh)
print(out_path)
PY
"""
        proc = _ssh_run(key_path, remote_script, host, user, port)
        if proc.returncode != 0:
            report.errors.append(
                "remote_integrity_failed:"
                + ((proc.stderr or "") + "\n" + (proc.stdout or "")).strip()
            )
            report.ok = False
            return report

        local_out = tempfile.mktemp(prefix="rateb-integrity-out-", suffix=".json")
        scp_out = [
            "scp",
            "-i", key_path,
            "-P", port,
            "-o", "BatchMode=yes",
            "-o", "IdentitiesOnly=yes",
            "-o", "StrictHostKeyChecking=accept-new",
            f"{user}@{host}:{remote_out}",
            local_out,
        ]
        proc = subprocess.run(scp_out, text=True, capture_output=True)
        if proc.returncode != 0:
            report.errors.append("scp_report_failed:" + (proc.stderr or proc.stdout or ""))
            report.ok = False
            return report
        with open(local_out, "r", encoding="utf-8") as handle:
            payload = json.load(handle)
        try:
            os.remove(local_out)
        except OSError:
            pass

        report.remote_file_count = int(payload.get("remote_file_count_before") or 0)
        report.orphan_directories_removed = list(
            payload.get("orphan_directories_removed") or []
        )
        report.missing_expected = list(payload.get("missing_expected") or [])
        report.hash_mismatches = list(payload.get("hash_mismatches") or [])
        report.remote_tree_hash = str(payload.get("remote_tree_hash") or "")
        for row in payload.get("orphans") or []:
            kind = row.get("kind") or "orphan"
            if kind not in ("delete_error", "force_purge", "orphan"):
                kind = classify_path(row.get("path", ""))
            elif kind in ("orphan", "force_purge"):
                kind = classify_path(row.get("path", ""))
            report.orphans_found.append(OrphanRecord(
                path=row.get("path", ""),
                kind=kind if row.get("kind") != "delete_error" else "delete_error",
                bytes=int(row.get("bytes") or 0),
                deleted=bool(row.get("deleted")),
                verified_absent=bool(row.get("verified_absent")),
            ))
        still = list(payload.get("still_orphans") or [])
        if still:
            report.errors.append("orphans_remain:" + ",".join(still[:40]))
            for path in still:
                report.orphans_found.append(OrphanRecord(
                    path=path,
                    kind=classify_path(path),
                    deleted=False,
                    verified_absent=False,
                ))

        if report.missing_expected:
            report.errors.append(
                "missing_expected:" + ",".join(report.missing_expected[:40])
            )
        if report.hash_mismatches:
            report.errors.append(
                "hash_mismatch_count=" + str(len(report.hash_mismatches))
            )
        if report.local_tree_hash != report.remote_tree_hash:
            report.errors.append(
                "tree_hash_mismatch:local="
                + report.local_tree_hash
                + ":remote="
                + report.remote_tree_hash
            )

        delete_errors = [o for o in report.orphans_found if o.kind == "delete_error"]
        report.ok = (
            not still
            and not report.missing_expected
            and not report.hash_mismatches
            and report.local_tree_hash == report.remote_tree_hash
            and not delete_errors
        )
        return report
    finally:
        for path in (man_path, del_path):
            try:
                os.remove(path)
            except OSError:
                pass
        _ssh_run(
            key_path,
            "rm -f -- "
            + " ".join(shlex.quote(p) for p in (remote_man, remote_del, remote_out)),
            host,
            user,
            port,
        )
