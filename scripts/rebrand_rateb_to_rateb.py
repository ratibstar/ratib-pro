#!/usr/bin/env python3
"""Replace RATEB/rateb branding tokens with RATEB/rateb in runtime code (not archive/docs)."""
from __future__ import annotations

import os
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SKIP_DIRS = {"archive", "Designed", "node_modules", ".git", "vendor", "rateb_mobile"}
ALLOW_EXT = {".php", ".js", ".css", ".htaccess", ".example", ".yml", ".sh", ".py", ".inc"}

REPLACEMENTS = [
    ("RATEB_", "RATEB_"),
    ("rateb_env_load_bridge_dotenv", "rateb_env_load_bridge_dotenv"),
    ("rateb_bootstrap_project_dotenv", "rateb_bootstrap_project_dotenv"),
    ("session_name('rateb_control')", "session_name('rateb_control')"),
    ("['rateb_control']", "['rateb_control']"),
    ("$ratebControlCookie", "$ratebControlCookie"),
    ("$ratebSkipSession", "$ratebSkipSession"),
    ("$ratebRoot", "$ratebRoot"),
    ("$ratebDoc", "$ratebDoc"),
]


def should_process(path: Path) -> bool:
    if path.name == ".htaccess":
        return True
    if path.suffix.lower() in ALLOW_EXT or path.name.endswith(".env.example"):
        return True
    return False


def main() -> None:
    changed: list[str] = []
    for dirpath, dirnames, filenames in os.walk(ROOT):
        dirnames[:] = [d for d in dirnames if d not in SKIP_DIRS]
        for fn in filenames:
            path = Path(dirpath) / fn
            if not should_process(path):
                continue
            try:
                text = path.read_text(encoding="utf-8", errors="replace")
            except OSError:
                continue
            orig = text
            for old, new in REPLACEMENTS:
                text = text.replace(old, new)
            if text != orig:
                path.write_text(text, encoding="utf-8", newline="\n")
                changed.append(str(path.relative_to(ROOT)))
    print(f"Updated {len(changed)} files")
    for p in sorted(changed):
        print(p)


if __name__ == "__main__":
    main()
