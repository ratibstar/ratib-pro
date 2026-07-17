#!/usr/bin/env python3
"""
DirectAdmin / generic SSH deploy — same fast file list as cPanel Fileman deploy,
but rsync over SSH (usually faster: one connection, delta transfer).

PX-Deploy: after upload, orphan purge + tree-hash integrity are mandatory.
Git HEAD is the single source of truth for managed production trees.
"""
from __future__ import annotations

import importlib.util
import os
import shlex
import stat
import subprocess
import sys
import tempfile


def _load_module(name: str, filename: str):
    here = os.path.dirname(os.path.abspath(__file__))
    path = os.path.join(here, filename)
    spec = importlib.util.spec_from_file_location(name, path)
    if spec is None or spec.loader is None:
        raise RuntimeError(f"cannot load {filename}")
    mod = importlib.util.module_from_spec(spec)
    sys.modules[name] = mod
    spec.loader.exec_module(mod)
    return mod


def _load_deploy_core():
    return _load_module("deploy_core", "github-cpanel-fileman-deploy-core.py")


integrity = _load_module("deploy_integrity", "deploy_integrity.py")


def _write_ssh_key() -> str:
    key = (
        os.environ.get("DEPLOY_SSH_PRIVATE_KEY")
        or os.environ.get("SSH_PRIVATE_KEY")
        or ""
    ).strip()
    if not key:
        raise SystemExit("DEPLOY_SSH_PRIVATE_KEY (or SSH_PRIVATE_KEY) required")
    if "\\n" in key and "\n" not in key:
        key = key.replace("\\n", "\n")
    fd, path = tempfile.mkstemp(prefix="rateb-deploy-key-", suffix=".pem")
    os.close(fd)
    with open(path, "w", encoding="utf-8", newline="\n") as handle:
        handle.write(key)
        if not key.endswith("\n"):
            handle.write("\n")
    os.chmod(path, stat.S_IRUSR | stat.S_IWUSR)
    return path


def _ssh_opts(key_path: str) -> str:
    port = os.environ.get("DEPLOY_SSH_PORT") or os.environ.get("SSH_PORT") or "22"
    extra = os.environ.get("DEPLOY_SSH_OPTS", "").strip()
    base = (
        f"ssh -i {key_path} -p {port} "
        "-o BatchMode=yes -o IdentitiesOnly=yes "
        "-o StrictHostKeyChecking=accept-new"
    )
    return f"{base} {extra}".strip()


def _ssh_run(key_path: str, remote_cmd: str) -> subprocess.CompletedProcess[str]:
    host = os.environ.get("DEPLOY_SSH_HOST") or os.environ.get("SSH_HOST")
    user = os.environ.get("DEPLOY_SSH_USER") or os.environ.get("SSH_USER")
    port = os.environ.get("DEPLOY_SSH_PORT") or os.environ.get("SSH_PORT") or "22"
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


def _ensure_remote_dir(remote_base: str, key_path: str) -> None:
    quoted = remote_base.replace("'", "'\"'\"'")
    proc = _ssh_run(key_path, f"mkdir -p '{quoted}'")
    if proc.stdout:
        print(proc.stdout.rstrip(), flush=True)
    if proc.returncode != 0:
        probe = _ssh_run(
            key_path,
            "pwd; echo '---'; ls -la; echo '---'; "
            "ls -la domains 2>/dev/null || true; echo '---'; "
            "ls -la public_html 2>/dev/null || true",
        )
        print(f"::error::cannot create remote dir: {remote_base}", flush=True)
        if probe.stdout:
            print("remote home listing:", flush=True)
            print(probe.stdout.rstrip(), flush=True)
        if probe.stderr:
            print(probe.stderr.rstrip(), flush=True)
        if proc.stderr:
            print(proc.stderr.rstrip(), flush=True)
        raise SystemExit(
            "Fix DEPLOY_REMOTE_BASE in GitHub Environment rateb.sa "
            "(DirectAdmin File Manager shows the real public_html path)"
        )


def _remote_dest(remote_base: str) -> str:
    host = os.environ.get("DEPLOY_SSH_HOST") or os.environ.get("SSH_HOST")
    user = os.environ.get("DEPLOY_SSH_USER") or os.environ.get("SSH_USER")
    if not host or not user:
        raise SystemExit("DEPLOY_SSH_HOST and DEPLOY_SSH_USER required")
    base = remote_base.rstrip("/") + "/"
    return f"{user}@{host}:{base}"


def _rsync_files(core, files: list[str], remote_base: str, key_path: str) -> tuple[int, int]:
    existing = [f for f in files if os.path.isfile(f)]
    missing = [f for f in files if f not in existing]
    for rel in missing:
        print(f"SKIP missing {rel}", flush=True)

    if not existing:
        return 0, 0

    fd, list_path = tempfile.mkstemp(prefix="rateb-deploy-files-", suffix=".txt")
    os.close(fd)
    try:
        with open(list_path, "w", encoding="utf-8") as handle:
            for rel in existing:
                handle.write(rel.replace("\\", "/") + "\n")

        dest = _remote_dest(remote_base)
        switches = os.environ.get("DEPLOY_RSYNC_SWITCHES", "-avz").split()
        cmd = [
            "rsync",
            *switches,
            "--files-from",
            list_path,
            "-e",
            _ssh_opts(key_path),
            "./",
            dest,
        ]
        print(f"rsync → {dest} ({len(existing)} file(s))", flush=True)
        proc = subprocess.run(cmd, text=True, capture_output=True)
        if proc.stdout:
            print(proc.stdout.rstrip(), flush=True)
        if proc.stderr:
            print(proc.stderr.rstrip(), flush=True)
        if proc.returncode != 0:
            raise SystemExit(proc.returncode)
        return len(existing), 0
    finally:
        try:
            os.remove(list_path)
        except OSError:
            pass


def _run_integrity(core, remote_base: str, key_path: str) -> int:
    host = os.environ.get("DEPLOY_SSH_HOST") or os.environ.get("SSH_HOST") or ""
    user = os.environ.get("DEPLOY_SSH_USER") or os.environ.get("SSH_USER") or ""
    port = os.environ.get("DEPLOY_SSH_PORT") or os.environ.get("SSH_PORT") or "22"
    print("PX-Deploy integrity: building expected Git manifest", flush=True)
    expected = integrity.build_expected_manifest()
    force = integrity.explicit_purge_paths(core)
    print(
        f"PX-Deploy integrity: expected_files={len(expected)} "
        f"force_purge={len(force)} dest={remote_base}",
        flush=True,
    )

    report = integrity.run_ssh_integrity(
        key_path=key_path,
        remote_base=remote_base,
        host=host,
        user=user,
        port=port,
        expected=expected,
        force_delete=force,
    )

    # Heal once: upload missing/mismatched expected files, then re-verify.
    heal = sorted(set(report.missing_expected) | {
        row.get("path", "") for row in report.hash_mismatches if row.get("path")
    })
    heal = [p for p in heal if p and os.path.isfile(p)]
    if heal and (report.missing_expected or report.hash_mismatches):
        print(f"PX-Deploy integrity heal upload: {len(heal)} file(s)", flush=True)
        _rsync_files(core, heal, remote_base, key_path)
        report = integrity.run_ssh_integrity(
            key_path=key_path,
            remote_base=remote_base,
            host=host,
            user=user,
            port=port,
            expected=expected,
            force_delete=force,
        )

    integrity.write_report(report)
    if not report.ok:
        print("::error::PX-Deploy integrity failed — orphan/hash mismatch", flush=True)
        for err in report.errors[:20]:
            print(f"integrity_error: {err}", flush=True)
        return 1
    print(
        "PX-Deploy integrity PASS "
        f"tree_hash={report.local_tree_hash} "
        f"orphans_removed={len(report.orphans_found)}",
        flush=True,
    )
    return 0


def main() -> int:
    core = _load_deploy_core()
    root = os.path.dirname(os.path.abspath(__file__))
    os.chdir(os.path.join(root, ".."))

    remote_base = (
        os.environ.get("DEPLOY_REMOTE_BASE")
        or os.environ.get("CPANEL_REMOTE_BASE")
        or "/home/admin/domains/rateb.sa/public_html"
    )
    mode = os.environ.get("DEPLOY_MODE") or os.environ.get("CPANEL_DEPLOY_MODE", "fast")
    files, _workers = core.build_file_list(mode)
    total = len(files)

    print(
        f"rsync deploy mode={mode} files={total} dest={remote_base}",
        flush=True,
    )

    key_path = _write_ssh_key()
    try:
        _ensure_remote_dir(remote_base.rstrip("/"), key_path)
        # Upload first so expected Git files exist, then purge orphans + verify hashes.
        ok, fail = _rsync_files(core, files, remote_base, key_path)
        print(
            f"\n========== Summary: ok={ok} fail={fail} total={total} "
            f"({(ok * 100 // total) if total else 0}% success) ==========",
            flush=True,
        )
        if fail > 0:
            return 1

        must_check = [m for m in core.MUST_OK if m in files]
        if must_check:
            print(f"MUST_OK check skipped for rsync (uploaded {ok} paths in batch)", flush=True)

        return _run_integrity(core, remote_base.rstrip("/"), key_path)
    finally:
        try:
            os.remove(key_path)
        except OSError:
            pass


if __name__ == "__main__":
    sys.exit(main())
