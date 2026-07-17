#!/usr/bin/env python3
"""
PX-Deploy integrity CLI for emergency / cPanel git-hook / manual SSH deploys.

Uses SSH remote integrity when DEPLOY_SSH_* is set.
Otherwise runs local integrity against DEPLOY_REMOTE_BASE / CPANEL_REMOTE_BASE
(useful when the hook already executes on the production host).
"""
from __future__ import annotations

import importlib.util
import json
import os
import stat
import sys
import tempfile


def _load(name: str, filename: str):
    here = os.path.dirname(os.path.abspath(__file__))
    path = os.path.join(here, filename)
    spec = importlib.util.spec_from_file_location(name, path)
    if spec is None or spec.loader is None:
        raise RuntimeError(f"cannot load {filename}")
    mod = importlib.util.module_from_spec(spec)
    sys.modules[name] = mod
    spec.loader.exec_module(mod)
    return mod


def _write_key() -> str:
    key = (
        os.environ.get("DEPLOY_SSH_PRIVATE_KEY")
        or os.environ.get("SSH_PRIVATE_KEY")
        or ""
    ).strip()
    if not key:
        raise SystemExit("DEPLOY_SSH_PRIVATE_KEY required for remote integrity")
    if "\\n" in key and "\n" not in key:
        key = key.replace("\\n", "\n")
    fd, path = tempfile.mkstemp(prefix="rateb-px-deploy-", suffix=".pem")
    os.close(fd)
    with open(path, "w", encoding="utf-8", newline="\n") as handle:
        handle.write(key)
        if not key.endswith("\n"):
            handle.write("\n")
    os.chmod(path, stat.S_IRUSR | stat.S_IWUSR)
    return path


def _run_local(integrity, core, remote_base: str) -> int:
    """Integrity against a local DocumentRoot (hook running on the server)."""
    import hashlib

    expected = integrity.build_expected_manifest()
    force = set(integrity.explicit_purge_paths(core)) | set(
        integrity.git_deleted_paths_for_sha(integrity.git_sha())
    )
    base = remote_base.rstrip("/")
    report = integrity.IntegrityReport(
        remote_base=base,
        git_sha=integrity.git_sha(),
        managed_roots=list(integrity.INTEGRITY_MANAGED_ROOTS),
        expected_file_count=len(expected),
        commit_deleted=integrity.git_deleted_paths_for_sha(integrity.git_sha()),
        explicit_purge=sorted(force),
        local_tree_hash=integrity.tree_hash(expected),
    )

    def excluded(p: str) -> bool:
        return integrity._excluded(p)

    remote_files = []
    for root in integrity.INTEGRITY_MANAGED_ROOTS:
        abs_root = os.path.join(base, root)
        if not os.path.isdir(abs_root):
            continue
        for dirpath, dirnames, filenames in os.walk(abs_root):
            dirnames[:] = [d for d in dirnames if d not in ("node_modules", ".git")]
            for name in filenames:
                abs_path = os.path.join(dirpath, name)
                rel = integrity._norm(os.path.relpath(abs_path, base))
                if excluded(rel):
                    continue
                remote_files.append(rel)

    expected_set = set(expected)
    orphans = sorted(set(remote_files) - expected_set)
    delete_targets = sorted(set(orphans) | set(force))
    for rel in delete_targets:
        abs_path = os.path.join(base, rel)
        existed = os.path.lexists(abs_path)
        size = os.path.getsize(abs_path) if existed and os.path.isfile(abs_path) else 0
        if existed and os.path.isfile(abs_path):
            try:
                os.remove(abs_path)
            except OSError as exc:
                report.errors.append(f"delete_error:{rel}:{exc}")
                report.orphans_found.append(integrity.OrphanRecord(
                    path=rel, kind="delete_error", bytes=size,
                    deleted=False, verified_absent=False,
                ))
                continue
        verified = not os.path.lexists(abs_path)
        if rel in orphans or existed:
            report.orphans_found.append(integrity.OrphanRecord(
                path=rel,
                kind=integrity.classify_path(rel),
                bytes=size,
                deleted=bool(existed and verified),
                verified_absent=verified,
            ))

    removed_dirs = []
    for root in integrity.INTEGRITY_MANAGED_ROOTS:
        abs_root = os.path.join(base, root)
        if not os.path.isdir(abs_root):
            continue
        for dirpath, dirnames, filenames in os.walk(abs_root, topdown=False):
            rel = integrity._norm(os.path.relpath(dirpath, base))
            if excluded(rel) or rel == integrity._norm(root):
                continue
            try:
                if not os.listdir(dirpath):
                    os.rmdir(dirpath)
                    removed_dirs.append(rel)
            except OSError:
                pass
    report.orphan_directories_removed = removed_dirs

    remote_manifest = {}
    for rel, want in expected.items():
        abs_path = os.path.join(base, rel)
        if not os.path.isfile(abs_path):
            report.missing_expected.append(rel)
            continue
        h = hashlib.sha256()
        with open(abs_path, "rb") as handle:
            while True:
                chunk = handle.read(1024 * 1024)
                if not chunk:
                    break
                h.update(chunk)
        got = h.hexdigest()
        remote_manifest[rel] = got
        if got != want:
            report.hash_mismatches.append({"path": rel, "local": want, "remote": got})

    still = []
    for root in integrity.INTEGRITY_MANAGED_ROOTS:
        abs_root = os.path.join(base, root)
        if not os.path.isdir(abs_root):
            continue
        for dirpath, dirnames, filenames in os.walk(abs_root):
            dirnames[:] = [d for d in dirnames if d not in ("node_modules", ".git")]
            for name in filenames:
                abs_path = os.path.join(dirpath, name)
                rel = integrity._norm(os.path.relpath(abs_path, base))
                if excluded(rel):
                    continue
                if rel not in expected_set:
                    still.append(rel)
    if still:
        report.errors.append("orphans_remain:" + ",".join(still[:40]))
    report.remote_file_count = len(remote_files)
    report.remote_tree_hash = integrity.tree_hash(remote_manifest)
    if report.local_tree_hash != report.remote_tree_hash:
        report.errors.append("tree_hash_mismatch")
    report.ok = (
        not still
        and not report.missing_expected
        and not report.hash_mismatches
        and report.local_tree_hash == report.remote_tree_hash
        and not any(o.kind == "delete_error" for o in report.orphans_found)
    )
    integrity.write_report(report)
    print(json.dumps({"ok": report.ok, "mode": "local", "errors": report.errors[:10]}, indent=2))
    return 0 if report.ok else 1


def main() -> int:
    root = os.path.dirname(os.path.abspath(__file__))
    os.chdir(os.path.join(root, ".."))
    integrity = _load("deploy_integrity", "deploy_integrity.py")
    core = _load("deploy_core", "github-cpanel-fileman-deploy-core.py")
    remote_base = (
        os.environ.get("DEPLOY_REMOTE_BASE")
        or os.environ.get("CPANEL_REMOTE_BASE")
        or "/home/admin/domains/rateb.sa/public_html"
    )
    creds = integrity.ssh_credentials_from_env()
    if creds is None and os.path.isdir(remote_base):
        return _run_local(integrity, core, remote_base)
    if creds is None:
        print("::error::PX-Deploy integrity requires SSH secrets or a local DocumentRoot", flush=True)
        return 1
    host, user, port = creds
    key_path = _write_key()
    try:
        expected = integrity.build_expected_manifest()
        force = integrity.explicit_purge_paths(core)
        report = integrity.run_ssh_integrity(
            key_path=key_path,
            remote_base=remote_base.rstrip("/"),
            host=host,
            user=user,
            port=port,
            expected=expected,
            force_delete=force,
        )
        integrity.write_report(report)
        return 0 if report.ok else 1
    finally:
        try:
            os.remove(key_path)
        except OSError:
            pass


if __name__ == "__main__":
    sys.exit(main())
