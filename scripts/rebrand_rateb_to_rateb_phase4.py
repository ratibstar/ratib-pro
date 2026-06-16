#!/usr/bin/env python3
"""Phase 4: replace any remaining 'rateb' substring (all cases) with 'rateb'."""
from __future__ import annotations

import os
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SKIP_DIRS = {"archive", "Designed", "node_modules", ".git", "vendor", "rateb_mobile"}
ALLOW_EXT = {".php", ".js", ".css", ".htaccess", ".inc", ".sql", ".yml", ".sh", ".py", ".md", ".txt", ".json"}


def sub_rateb(match: re.Match[str]) -> str:
    s = match.group(0)
    if s.isupper():
        return "RATEB"
    if s[0].isupper():
        return "Rateb"
    return "rateb"


def main() -> None:
    pat = re.compile(r"rateb", re.IGNORECASE)
    pre = [
        ("admin_", "admin_"),
        ("admin_", "admin_"),
        ("admin", "admin"),
        ("admin", "admin"),
    ]
    changed = 0
    for dirpath, dirnames, filenames in os.walk(ROOT):
        dirnames[:] = [d for d in dirnames if d not in SKIP_DIRS]
        for fn in filenames:
            path = Path(dirpath) / fn
            if path.suffix.lower() not in ALLOW_EXT and fn not in {".htaccess", ".cpanel.yml"}:
                continue
            try:
                text = path.read_text(encoding="utf-8", errors="replace")
            except OSError:
                continue
            if not pat.search(text) and not any(x in text for x in ("admin", "admin")):
                continue
            new_text = text
            for old, new in pre:
                new_text = new_text.replace(old, new)
            new_text = pat.sub(sub_rateb, new_text)
            if new_text != text:
                path.write_text(new_text, encoding="utf-8", newline="\n")
                changed += 1
    print(f"Updated {changed} files")


if __name__ == "__main__":
    main()
