from pathlib import Path

ROOT = Path(r"c:\Users\انا\Desktop\ratibprogram")
EXCLUDE_DIRS = {"Designed", "vendor", "archive", "logs", ".git", "node_modules"}
EXTS = {".php", ".js", ".css"}


def should_skip(path: Path) -> bool:
    return any(part in EXCLUDE_DIRS for part in path.parts)


def detect_nl(text: str) -> str:
    return "\r\n" if "\r\n" in text else "\n"


def has_bilingual_header(text: str) -> bool:
    head = "\n".join(text.splitlines()[:60])
    return "EN:" in head and "AR:" in head


def add_php_header(text: str, rel: str) -> str:
    if not text.lstrip().startswith("<?php"):
        return text
    nl = detect_nl(text)
    lines = text.splitlines()
    first = lines[0]
    rest = lines[1:]
    header = [
        "/**",
        f" * EN: This PHP file handles server-side logic for {rel}.",
        f" * AR: هذا الملف PHP يدير منطق الخادم للملف {rel}.",
        " */",
    ]
    body = nl.join([first, *header, *rest])
    return body + (nl if text.endswith(("\n", "\r\n")) else "")


def add_js_header(text: str, rel: str) -> str:
    nl = detect_nl(text)
    header = [
        "/**",
        f" * EN: This JavaScript file implements client-side behavior for {rel}.",
        f" * AR: هذا الملف JavaScript ينفذ سلوك الواجهة للملف {rel}.",
        " */",
    ]
    return nl.join(header) + nl + text


def add_css_header(text: str, rel: str) -> str:
    nl = detect_nl(text)
    header = [
        "/*",
        f" * EN: This stylesheet defines UI presentation rules for {rel}.",
        f" * AR: هذا الملف CSS يعرّف تنسيقات واجهة المستخدم للملف {rel}.",
        " */",
    ]
    return nl.join(header) + nl + text


updated = []
skipped = []
for path in ROOT.rglob("*"):
    if not path.is_file():
        continue
    if path.suffix.lower() not in EXTS:
        continue
    if should_skip(path):
        continue
    try:
        text = path.read_text(encoding="utf-8")
    except Exception:
        skipped.append(str(path.relative_to(ROOT)))
        continue
    if has_bilingual_header(text):
        continue

    rel = str(path.relative_to(ROOT)).replace("\\", "/")
    if path.suffix.lower() == ".php":
        new_text = add_php_header(text, rel)
    elif path.suffix.lower() == ".js":
        new_text = add_js_header(text, rel)
    else:
        new_text = add_css_header(text, rel)

    if new_text != text:
        path.write_text(new_text, encoding="utf-8")
        updated.append(rel)

print(f"UPDATED:{len(updated)}")
for item in updated:
    print(item)
print(f"SKIPPED:{len(skipped)}")
for item in skipped[:50]:
    print("SKIP", item)
