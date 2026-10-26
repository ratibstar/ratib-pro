#!/usr/bin/env python3
"""Build the ai.rateb.sa deploy bundle and its manifest on the GitHub runner.

    python3 ai-rateb-sa-source/ops/deploy-manifest.py --baseline SHA --out DIR [--accept-generated-change]

For every file in ops/deploy-files.txt the manifest records the sha256 this deploy writes
and the server contents it may replace, each with the reason it is allowed:
  deployed    the server already has this deploy's version (a rerun)
  baseline    the server has the version from the approved baseline commit
  repository  a generated configuration file still holds the repository copy
  accepted    a generated configuration file may differ (operator chose to rotate it)
A file that is absent on the server is allowed only when the baseline does not have it
either (a new file) or it is generated from a secret for the first time. ops/deploy-guard.sh
enforces this on the server before anything changes.

Configuration files the workflow writes from secrets are built here exactly as before, read
from the environment, written with mode 600, and never printed.

Output in DIR: files/<rel>, manifest.tsv, bundle.tar.gz, bundle.sha256, deploy.env
(BOOTSTRAP_SHA256, MEDIA_ROOT), and opcache.txt (PHP files to invalidate).
"""
import argparse
import hashlib
import os
import re
import subprocess
import sys
import tarfile

HERE = os.path.dirname(os.path.abspath(__file__))
SRC = os.path.dirname(HERE)
REPO = os.path.dirname(SRC)
PREFIX = os.path.basename(SRC)
REL_RE = re.compile(r"^[A-Za-z0-9._-]+(/[A-Za-z0-9._-]+)*$")
LOCAL_AFTER = {"config/github.php": "config/github.local.php", "config/ai.php": "config/ai.local.php"}


def fail(message):
    print("MANIFEST=error: " + message)
    sys.exit(1)


def sha(data):
    return hashlib.sha256(data).hexdigest()


def git(*args):
    return subprocess.run(["git", "-C", REPO, *args], capture_output=True)


def php_string(value):
    return value.replace("\\", "\\\\").replace("'", "\\'")


def generated_files():
    out = {}
    client_id = os.environ.get("RATEB_GOOGLE_CLIENT_ID", "")
    if client_id:
        out["config/google.php"] = ("<?php\ndeclare(strict_types=1);\nreturn ['client_id' => '" + php_string(client_id) + "'];\n").encode("utf-8")
    secret = os.environ.get("RATEB_GITHUB_CLIENT_SECRET", "")
    if secret:
        out["config/github.local.php"] = ("<?php\ndeclare(strict_types=1);\nreturn ['client_secret' => '" + php_string(secret) + "'];\n").encode("utf-8")
    keys = [
        ("deepseek_api_key", os.environ.get("DEEPSEEK_API_KEY", "")),
        ("sawtak_api_key", os.environ.get("SAWTAK_API_KEY", "")),
        ("horde_api_key", os.environ.get("HORDE_API_KEY", "")),
        ("moyasar_secret_key", os.environ.get("MOYASAR_SECRET_KEY", "")),
    ]
    present = [(name, value) for name, value in keys if value]
    if present:
        body = "<?php\ndeclare(strict_types=1);\nreturn [\n"
        for name, value in present:
            body += f"    '{name}' => '{php_string(value)}',\n"
        body += "];\n"
        out["config/ai.local.php"] = body.encode("utf-8")
    return out


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--baseline", required=True)
    parser.add_argument("--out", required=True)
    parser.add_argument("--accept-generated-change", action="store_true")
    args = parser.parse_args()

    baseline = args.baseline.strip()
    if not re.fullmatch(r"[0-9a-f]{40}", baseline):
        fail("the baseline must be a full 40-character commit sha")
    if git("cat-file", "-e", baseline + "^{commit}").returncode != 0:
        fail("the baseline commit is not in this checkout; check out the full history")
    if git("merge-base", "--is-ancestor", baseline, "HEAD").returncode != 0:
        fail("the baseline is not an ancestor of the commit being deployed")

    files = []
    with open(os.path.join(HERE, "deploy-files.txt"), encoding="utf-8") as handle:
        for line in handle:
            rel = line.strip()
            if not rel or rel.startswith("#"):
                continue
            if not REL_RE.fullmatch(rel) or any(part in (".", "..") for part in rel.split("/")):
                fail("unsafe path in deploy-files.txt: " + rel)
            if rel in files or rel in LOCAL_AFTER.values():
                fail("listed twice or reserved: " + rel)
            files.append(rel)

    generated = generated_files()
    out = os.path.abspath(args.out)
    if os.path.exists(out):
        fail("the output folder already exists")
    os.makedirs(os.path.join(out, "files"), mode=0o700)
    os.chmod(out, 0o700)

    def baseline_blob(rel):
        result = git("cat-file", "blob", f"{baseline}:{PREFIX}/{rel}")
        return result.stdout if result.returncode == 0 else None

    entries = []

    def add(rel, data, kind, allowed, absent_ok):
        seen, unique = set(), []
        for digest, label in allowed:
            if digest not in seen:
                seen.add(digest)
                unique.append((digest, label))
        if kind == "generated" and args.accept_generated_change:
            unique.append(("any", "accepted"))
        path = os.path.join(out, "files", *rel.split("/"))
        os.makedirs(os.path.dirname(path), mode=0o700, exist_ok=True)
        with open(path, "wb") as handle:
            handle.write(data)
        os.chmod(path, 0o600)
        entries.append((rel, kind, sha(data), unique, absent_ok))

    for rel in files:
        path = os.path.join(SRC, *rel.split("/"))
        if not os.path.isfile(path):
            fail("missing in the repository: " + rel)
        with open(path, "rb") as handle:
            repository = handle.read()
        base = baseline_blob(rel)
        if rel in generated:
            data = generated[rel]
            allowed = [(sha(data), "deployed")]
            if base is not None:
                allowed.append((sha(base), "baseline"))
            allowed.append((sha(repository), "repository"))
            add(rel, data, "generated", allowed, base is None)
        else:
            allowed = [(sha(repository), "deployed")]
            if base is not None:
                allowed.append((sha(base), "baseline"))
            add(rel, repository, "app", allowed, base is None)
        local = LOCAL_AFTER.get(rel)
        if local and local in generated:
            data = generated[local]
            local_base = baseline_blob(local)
            allowed = [(sha(data), "deployed")]
            if local_base is not None:
                allowed.append((sha(local_base), "baseline"))
            add(local, data, "generated", allowed, local_base is None)

    with open(os.path.join(out, "manifest.tsv"), "w", encoding="utf-8", newline="\n") as handle:
        for rel, kind, digest, allowed, absent_ok in entries:
            handle.write("\t".join([rel, kind, digest, ",".join(f"{d}:{label}" for d, label in allowed), "1" if absent_ok else "0"]) + "\n")
    os.chmod(os.path.join(out, "manifest.tsv"), 0o600)

    library = open(os.path.join(SRC, "app", "media", "library.php"), encoding="utf-8").read()
    media = re.search(r"define\('MEDIA_ROOT',\s*'(/[A-Za-z0-9._/-]+)'\)", library)
    if not media:
        fail("MEDIA_ROOT was not found in app/media/library.php")
    boot_base = baseline_blob("app/bootstrap.php")
    boot_hashes = [sha(boot_base)] if boot_base is not None else []
    boot_hashes.append(next(digest for rel, _, digest, _, _ in entries if rel == "app/bootstrap.php"))
    with open(os.path.join(out, "deploy.env"), "w", encoding="utf-8", newline="\n") as handle:
        handle.write("BOOTSTRAP_SHA256=" + ",".join(dict.fromkeys(boot_hashes)) + "\n")
        handle.write("MEDIA_ROOT=" + media.group(1) + "\n")
    with open(os.path.join(out, "opcache.txt"), "w", encoding="utf-8", newline="\n") as handle:
        for rel, *_ in entries:
            if rel.endswith(".php"):
                handle.write(rel + "\n")

    def owned_by_nobody(info):
        info.uid = info.gid = 0
        info.uname = info.gname = ""
        info.mode = 0o600
        info.mtime = 0
        return info

    bundle = os.path.join(out, "bundle.tar.gz")
    with tarfile.open(bundle, "w:gz", format=tarfile.GNU_FORMAT) as tar:
        tar.add(os.path.join(out, "manifest.tsv"), arcname="manifest.tsv", filter=owned_by_nobody)
        for rel, *_ in entries:
            tar.add(os.path.join(out, "files", *rel.split("/")), arcname="files/" + rel, filter=owned_by_nobody)
    with open(bundle, "rb") as handle:
        bundle_sha = sha(handle.read())
    with open(os.path.join(out, "bundle.sha256"), "w", encoding="utf-8", newline="\n") as handle:
        handle.write(bundle_sha + "\n")

    for rel, kind, _, allowed, absent_ok in entries:
        reasons = [label for _, label in allowed] + (["new"] if absent_ok else [])
        print(f"MANIFEST_FILE={rel} {kind} replaces={'|'.join(reasons)}")
    print("MANIFEST_BASELINE=" + baseline)
    print("MANIFEST_FILES=" + str(len(entries)))
    print("MANIFEST_GENERATED=" + (",".join(rel for rel, kind, *_ in entries if kind == "generated") or "none"))
    print("BUNDLE_SHA256=" + bundle_sha)


if __name__ == "__main__":
    main()
