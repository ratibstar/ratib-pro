"""
Minimal NAMAA-Saudi-TTS Gradio ZeroGPU service for RATEB AI.
No RATEB source, no customer data, no request/audio logging.
"""
from __future__ import annotations

import os
import secrets
import tempfile
from functools import lru_cache

import gradio as gr
import numpy as np
import spaces
import torch
import torchaudio as ta
from huggingface_hub import snapshot_download
from safetensors.torch import load_file as load_safetensors

EXPECTED_SECRET = (os.environ.get("RATEB_NAMAA_SECRET") or "").strip()
MODEL_REPO = "NAMAA-Space/NAMAA-Saudi-TTS"


def _authorized(request: gr.Request | None) -> bool:
    if EXPECTED_SECRET == "":
        return False
    if request is None:
        return False
    got = (request.headers.get("x-rateb-namaa-secret") or "").strip()
    if got == "":
        return False
    return secrets.compare_digest(got, EXPECTED_SECRET)


@lru_cache(maxsize=1)
def _load_model():
    # Module-level CUDA placement required for ZeroGPU.
    device = "cuda"
    ckpt_dir = snapshot_download(repo_id=MODEL_REPO, repo_type="model", revision="main")
    from chatterbox import mtl_tts

    model = mtl_tts.ChatterboxMultilingualTTS.from_pretrained(device=device)
    t3_state = load_safetensors(f"{ckpt_dir}/t3_mtl23ls_v2.safetensors", device=device)
    model.t3.load_state_dict(t3_state)
    model.t3.to(device).eval()
    return model


def health() -> str:
    secret_ok = "yes" if EXPECTED_SECRET != "" else "no"
    return f"ok secret_configured={secret_ok} model={MODEL_REPO} runtime=zerogpu"


@spaces.GPU(duration=60)
def tts(text: str, request: gr.Request):
    if not _authorized(request):
        raise gr.Error("unauthorized")
    cleaned = (text or "").strip()
    if cleaned == "":
        raise gr.Error("empty_text")
    if len(cleaned) > 600:
        cleaned = cleaned[:600]
    model = _load_model()
    with torch.inference_mode():
        wav = model.generate(cleaned, language_id="ar")
    if isinstance(wav, torch.Tensor):
        audio = wav.detach().float().cpu().numpy()
    else:
        audio = np.asarray(wav, dtype=np.float32)
    if audio.ndim > 1:
        audio = np.squeeze(audio)
    sr = int(getattr(model, "sr", 24000) or 24000)
    # Return filepath for stable Gradio file download (server-to-server).
    fd, path = tempfile.mkstemp(suffix=".wav")
    os.close(fd)
    ta.save(path, torch.from_numpy(audio).unsqueeze(0), sr)
    return path


with gr.Blocks(title="RATEB NAMAA Saudi TTS") as demo:
    gr.Markdown("RATEB NAMAA Saudi TTS — authenticated inference only.")
    status = gr.Textbox(label="health", value=health(), interactive=False)
    refresh = gr.Button("refresh_health")
    refresh.click(fn=health, outputs=status)
    text_in = gr.Textbox(label="text", lines=3)
    audio_out = gr.Audio(label="audio", type="filepath")
    run = gr.Button("tts")
    run.click(fn=tts, inputs=text_in, outputs=audio_out, api_name="tts")

demo.queue(max_size=4).launch()
