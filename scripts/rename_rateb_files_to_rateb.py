#!/usr/bin/env python3
"""Rename files and directories whose names contain 'rateb' -> 'rateb'."""
from __future__ import annotations

import os
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SKIP_DIRS = {"archive", "Designed", "node_modules", ".git", "vendor", "rateb_mobile"}


def rename_path(path: Path) -> Path | None:
    name = path.name
    lower = name.lower()
    if "rateb" not in lower:
        return None
    new_name = ""
    i = 0
    while i < len(name):
        chunk = name[i : i + 5]
        if chunk.lower() == "rateb":
            new_name += "rateb" if chunk.islower() else "RATEB"
            i += 5
        else:
            new_name += name[i]
            i += 1
    if new_name == name:
        return None
    return path.with_name(new_name)


def main() -> None:
    # Deepest paths first so directory renames happen before children.
    all_paths: list[Path] = []
    for dirpath, dirnames, filenames in os.walk(ROOT, topdown=False):
        parts = Path(dirpath).parts
        if any(p in SKIP_DIRS for p in parts):
            continue
        for fn in filenames:
            all_paths.append(Path(dirpath) / fn)
        all_paths.append(Path(dirpath))

    renamed = 0
    for path in all_paths:
        if path == ROOT:
            continue
        new_path = rename_path(path)
        if new_path is None or path == new_path:
            continue
        if not path.exists():
            continue
        if new_path.exists():
            print(f"SKIP exists: {new_path}")
            continue
        path.rename(new_path)
        renamed += 1
        print(f"{path.relative_to(ROOT)} -> {new_path.relative_to(ROOT)}")
    print(f"Renamed {renamed} paths")


if __name__ == "__main__":
    main()
