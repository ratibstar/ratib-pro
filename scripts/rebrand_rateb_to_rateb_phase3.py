#!/usr/bin/env python3
"""Phase 3: rateb_ function/identifier prefix -> rateb_."""
from __future__ import annotations

import os
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SKIP_DIRS = {"archive", "Designed", "node_modules", ".git", "vendor", "rateb_mobile"}
ALLOW_EXT = {".php", ".js", ".css", ".htaccess", ".inc", ".sql", ".yml", ".sh", ".py"}


def main() -> None:
    changed: list[str] = []
    for dirpath, dirnames, filenames in os.walk(ROOT):
        dirnames[:] = [d for d in dirnames if d not in SKIP_DIRS]
        for fn in filenames:
            path = Path(dirpath) / fn
            if path.suffix.lower() not in ALLOW_EXT and fn != ".htaccess":
                continue
            try:
                text = path.read_text(encoding="utf-8", errors="replace")
            except OSError:
                continue
            if "rateb_" not in text and "RATEB_" not in text:
                continue
            orig = text
            text = text.replace("rateb_", "rateb_")
            text = text.replace("RATEB_", "RATEB_")
            if text != orig:
                path.write_text(text, encoding="utf-8", newline="\n")
                changed.append(str(path.relative_to(ROOT)))
    print(f"Updated {len(changed)} files")


if __name__ == "__main__":
    main()
