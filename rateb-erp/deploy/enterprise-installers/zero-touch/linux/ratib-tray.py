#!/usr/bin/env python3
"""Phase D.4 — RATEB system tray (Linux). Uses PyQt5/PySide2 if available; else notify-send poller."""
from __future__ import annotations

import json
import os
import shutil
import subprocess
import sys
import time
from pathlib import Path

ROOT = Path(os.environ.get("RATEB_BRANCH_ROOT", "/opt/ratib-branch"))
STATUS = ROOT / "storage" / "branch" / "status.json"
APP_ENV = ROOT / "storage" / "branch" / "appliance.env"
SCRIPT = Path(__file__).resolve().parent


def load_url() -> str:
    url = "https://rateb.sa/rateb-erp/public/admin/"
    if APP_ENV.is_file():
        for line in APP_ENV.read_text(encoding="utf-8", errors="ignore").splitlines():
            if line.startswith("RATEB_CLOUD_ADMIN_URL="):
                cand = line.split("=", 1)[1].strip()
                if cand:
                    url = cand
            elif line.startswith("RATEB_BRANCH_HTTP_URL=") and "rateb.sa" not in url:
                # Prefer cloud; local appliance URL is sync-only.
                pass
    if "rateb.sa" not in url:
        url = "https://rateb.sa/rateb-erp/public/admin/"
    return url


def read_status() -> dict:
    try:
        return json.loads(STATUS.read_text(encoding="utf-8"))
    except Exception:
        return {"display": "🔵 STARTING", "state": "starting", "open_url": load_url(), "pending_records": 0}


def php_bin() -> str:
    if APP_ENV.is_file():
        for line in APP_ENV.read_text(encoding="utf-8", errors="ignore").splitlines():
            if line.startswith("RATEB_PHP_BIN="):
                p = line.split("=", 1)[1].strip()
                if p and Path(p).exists():
                    return p
    return shutil.which("php") or "php"


def run_php(*args: str) -> None:
    cmd = [php_bin(), "-d", "extension=pdo_sqlite", "-d", "extension=sqlite3", *args]
    subprocess.Popen(cmd, cwd=str(ROOT), stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)


def open_erp() -> None:
    st = read_status()
    url = st.get("open_url") or load_url()
    subprocess.Popen(["xdg-open", url], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)


def backup_now() -> None:
    run_php(str(ROOT / "bin" / "hybrid-branch-backup.php"), "--label=manual")


def diagnostics() -> None:
    run_php(str(ROOT / "bin" / "hybrid-branch-diagnostics.php"))
    subprocess.Popen(["xdg-open", str(ROOT / "storage" / "branch")], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)


def export_support() -> None:
    run_php(str(ROOT / "bin" / "hybrid-zero-touch-export-support.php"))


def restart_services() -> None:
    subprocess.Popen(
        ["systemctl", "restart", "ratib-branch-web.service", "ratib-hybrid-sync.service"],
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL,
    )


def try_qt_tray() -> bool:
    QtWidgets = None
    for mod in ("PyQt5.QtWidgets", "PySide2.QtWidgets", "PyQt6.QtWidgets", "PySide6.QtWidgets"):
        try:
            import importlib

            QtWidgets = importlib.import_module(mod)
            break
        except Exception:
            continue
    if QtWidgets is None:
        return False

    app = QtWidgets.QApplication(sys.argv)
    tray = QtWidgets.QSystemTrayIcon()
    tray.setToolTip("RATEB ERP")
    menu = QtWidgets.QMenu()
    act_status = menu.addAction("Status")
    act_status.setEnabled(False)
    act_open = menu.addAction("Open RATEB ERP")
    act_open.triggered.connect(open_erp)
    act_bak = menu.addAction("Backup Now")
    act_bak.triggered.connect(backup_now)
    act_diag = menu.addAction("Diagnostics")
    act_diag.triggered.connect(diagnostics)
    act_exp = menu.addAction("Export Support Package")
    act_exp.triggered.connect(export_support)
    act_rst = menu.addAction("Restart Services")
    act_rst.triggered.connect(restart_services)
    menu.addSeparator()
    act_exit = menu.addAction("Exit")
    act_exit.triggered.connect(app.quit)
    tray.setContextMenu(menu)
    tray.show()

    def refresh() -> None:
        st = read_status()
        disp = st.get("display") or st.get("label") or "RATEB"
        act_status.setText(f"Status: {disp}")
        tray.setToolTip(f"RATEB ERP — {disp}")

    timer = QtWidgets.QTimer()
    timer.timeout.connect(refresh)
    timer.start(3000)
    refresh()
    app.exec_() if hasattr(app, "exec_") else app.exec()
    return True


def notify_loop() -> None:
    last = ""
    while True:
        st = read_status()
        disp = st.get("display") or "RATEB"
        if disp != last and shutil.which("notify-send"):
            subprocess.call(["notify-send", "-a", "RATEB ERP", "RATEB ERP", str(disp)])
            last = disp
        time.sleep(5)


def main() -> int:
    if try_qt_tray():
        return 0
    # Fallback: desktop notification poller (still zero-touch; no jargon)
    notify_loop()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
