#!/usr/bin/env python3
"""Deploy infra/namaa-zerogpu-space to a free Hugging Face ZeroGPU Space.

Requires env:
  HF_TOKEN              Hugging Face write token (never printed)
  RATEB_NAMAA_SECRET    Shared secret for Space + RATEB

Optional:
  HF_SPACE_REPO         default: <username>/rateb-namaa-saudi-tts
"""
from __future__ import annotations

import os
import secrets
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SPACE_DIR = ROOT / "infra" / "namaa-zerogpu-space"


def main() -> int:
    token = (os.environ.get("HF_TOKEN") or os.environ.get("HUGGINGFACE_TOKEN") or "").strip()
    if not token:
        print("BLOCKER=HF_TOKEN_MISSING")
        print("ACTION=Add HF_TOKEN for an eligible free HF account to server secrets, then re-run.")
        return 2

    shared = (os.environ.get("RATEB_NAMAA_SECRET") or "").strip()
    if not shared:
        shared = secrets.token_urlsafe(32)
        print("GENERATED_RATEB_NAMAA_SECRET=yes")
    else:
        print("GENERATED_RATEB_NAMAA_SECRET=no")

    try:
        from huggingface_hub import HfApi, whoami
    except Exception as exc:  # noqa: BLE001
        print("BLOCKER=huggingface_hub_missing")
        print("error=", type(exc).__name__)
        return 2

    api = HfApi(token=token)
    try:
        me = whoami(token=token)
    except Exception as exc:  # noqa: BLE001
        print("BLOCKER=HF_TOKEN_INVALID")
        print("error=", type(exc).__name__)
        return 2

    username = str(me.get("name") or me.get("fullname") or "").strip()
    if not username:
        print("BLOCKER=HF_USERNAME_UNKNOWN")
        return 2
    print("HF_USER=", username)

    # Eligibility hints (best-effort; do not fail solely on missing fields).
    email = str(me.get("email") or "")
    print("HF_EMAIL_PRESENT=", "yes" if email else "unknown")

    repo_id = (os.environ.get("HF_SPACE_REPO") or f"{username}/rateb-namaa-saudi-tts").strip()
    print("SPACE_REPO=", repo_id)

    if not SPACE_DIR.is_dir():
        print("BLOCKER=SPACE_DIR_MISSING")
        return 2

    try:
        api.create_repo(
            repo_id=repo_id,
            repo_type="space",
            space_sdk="gradio",
            private=False,
            exist_ok=True,
            token=token,
        )
    except Exception as exc:  # noqa: BLE001
        msg = str(exc).lower()
        if "payment" in msg or "billing" in msg or "subscribe" in msg or "pro" in msg and "required" in msg:
            print("BLOCKER=PAID_PLAN_REQUIRED")
            return 3
        print("BLOCKER=CREATE_SPACE_FAILED")
        print("error=", type(exc).__name__)
        return 2

    # Ensure ZeroGPU hardware when API supports it.
    try:
        api.request_space_hardware(repo_id=repo_id, hardware="zero-a10g", token=token)
        print("HARDWARE=zero-a10g")
    except Exception as exc:  # noqa: BLE001
        print("HARDWARE_SET=", type(exc).__name__)

    try:
        api.add_space_secret(repo_id=repo_id, key="RATEB_NAMAA_SECRET", value=shared, token=token)
        print("SPACE_SECRET=set")
    except Exception as exc:  # noqa: BLE001
        print("BLOCKER=SPACE_SECRET_FAILED")
        print("error=", type(exc).__name__)
        return 2

    try:
        api.upload_folder(
            repo_id=repo_id,
            repo_type="space",
            folder_path=str(SPACE_DIR),
            token=token,
            commit_message="Deploy minimal NAMAA ZeroGPU TTS for RATEB AI",
        )
        print("UPLOAD=ok")
    except Exception as exc:  # noqa: BLE001
        print("BLOCKER=UPLOAD_FAILED")
        print("error=", type(exc).__name__)
        return 2

    space_url = f"https://huggingface.co/spaces/{repo_id}"
    endpoint = f"https://{repo_id.replace('/', '-')}.hf.space"
    print("SPACE_URL=", space_url)
    print("NAMAA_INFERENCE_URL=", endpoint)
    print("NAMAA_INFERENCE_SECRET_PRESENT=yes")
    print("NEXT=Persist NAMAA_INFERENCE_URL and NAMAA_INFERENCE_SECRET on ai.rateb.sa secrets, then test Campaign 90.")
    # Write local non-secret marker for operators.
    marker = ROOT / "storage" / "namaa-zerogpu-deploy.txt"
    marker.parent.mkdir(parents=True, exist_ok=True)
    marker.write_text(
        f"space={repo_id}\nurl={space_url}\nendpoint={endpoint}\n",
        encoding="utf-8",
    )
    return 0


if __name__ == "__main__":
    sys.exit(main())
