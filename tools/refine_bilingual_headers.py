from pathlib import Path

ROOT = Path(r"c:\Users\انا\Desktop\ratibprogram")
EXCLUDE_DIRS = {"Designed", "vendor", "archive", "logs", ".git", "node_modules"}
EXTS = {".php", ".js", ".css"}

GENERIC_EN = {
    ".php": "EN: This PHP file handles server-side logic for ",
    ".js": "EN: This JavaScript file implements client-side behavior for ",
    ".css": "EN: This stylesheet defines UI presentation rules for ",
}

GENERIC_AR = {
    ".php": "AR: هذا الملف PHP يدير منطق الخادم للملف ",
    ".js": "AR: هذا الملف JavaScript ينفذ سلوك الواجهة للملف ",
    ".css": "AR: هذا الملف CSS يعرّف تنسيقات واجهة المستخدم للملف ",
}


def should_skip(path: Path) -> bool:
    return any(part in EXCLUDE_DIRS for part in path.parts)


def module_label(rel: str) -> tuple[str, str]:
    parts = rel.split("/")
    if parts[0] == "api":
        return (
            "API endpoint/business logic",
            "منطق واجهات API والعمليات الخلفية",
        )
    if parts[0] == "pages":
        return (
            "user-facing page rendering and page-level server flow",
            "عرض صفحات المستخدم وتدفق الخادم الخاص بالصفحة",
        )
    if parts[0] == "includes":
        return (
            "shared bootstrap/helpers/layout partial behavior",
            "سلوك الملفات المشتركة للإعدادات والمساعدات وأجزاء التخطيط",
        )
    if parts[0] == "control-panel":
        return (
            "control-panel module behavior and admin-country operations",
            "سلوك وحدة لوحة التحكم وعمليات إدارة الدول",
        )
    if parts[0] == "admin":
        return (
            "system administration/observability module behavior",
            "سلوك وحدة إدارة النظام والمراقبة",
        )
    if parts[0] == "core":
        return (
            "core framework/runtime behavior",
            "سلوك النواة والإطار الأساسي للتشغيل",
        )
    if parts[0] == "config":
        return (
            "configuration/runtime setup behavior",
            "سلوك إعدادات النظام وتهيئة التشغيل",
        )
    if parts[0] == "resources":
        return (
            "template/view rendering behavior",
            "سلوك قوالب العرض والواجهات",
        )
    if parts[0] == "public":
        return (
            "public web entry/assets behavior",
            "سلوك المدخل العام للويب وملفات الواجهة",
        )
    if parts[0] == "js":
        return (
            "frontend interaction behavior",
            "سلوك تفاعلات الواجهة الأمامية",
        )
    if parts[0] == "css":
        return (
            "visual styling and layout rules",
            "قواعد التنسيق البصري وتخطيط الواجهة",
        )
    return (
        "application behavior",
        "سلوك جزء من التطبيق",
    )


def build_lines(ext: str, rel: str) -> tuple[str, str]:
    en_mod, ar_mod = module_label(rel)
    if ext == ".php":
        return (
            f" * EN: Handles {en_mod} in `{rel}`.",
            f" * AR: يدير {ar_mod} في `{rel}`.",
        )
    if ext == ".js":
        return (
            f" * EN: Implements {en_mod} in `{rel}`.",
            f" * AR: ينفذ {ar_mod} في `{rel}`.",
        )
    return (
        f" * EN: Defines {en_mod} in `{rel}`.",
        f" * AR: يحدد {ar_mod} في `{rel}`.",
    )


def refine_file(path: Path) -> bool:
    ext = path.suffix.lower()
    rel = str(path.relative_to(ROOT)).replace("\\", "/")
    text = path.read_text(encoding="utf-8")
    lines = text.splitlines()
    changed = False

    for i, line in enumerate(lines[:30]):
        if GENERIC_EN[ext] in line:
            new_en, _ = build_lines(ext, rel)
            lines[i] = new_en
            changed = True
            break
    for i, line in enumerate(lines[:30]):
        if GENERIC_AR[ext] in line:
            _, new_ar = build_lines(ext, rel)
            lines[i] = new_ar
            changed = True
            break

    if not changed:
        return False

    trailing_nl = text.endswith("\n") or text.endswith("\r\n")
    new_text = "\n".join(lines) + ("\n" if trailing_nl else "")
    path.write_text(new_text, encoding="utf-8")
    return True


updated: list[str] = []
for p in ROOT.rglob("*"):
    if not p.is_file():
        continue
    if should_skip(p):
        continue
    if p.suffix.lower() not in EXTS:
        continue
    try:
        if refine_file(p):
            updated.append(str(p.relative_to(ROOT)).replace("\\", "/"))
    except Exception:
        continue

print(f"REFINED:{len(updated)}")
for item in updated:
    print(item)
