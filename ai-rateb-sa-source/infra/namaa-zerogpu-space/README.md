---
title: RATEB NAMAA Saudi TTS
sdk: gradio
sdk_version: 5.29.0
app_file: app.py
hardware: zero-a10g
license: mit
short_description: Minimal Saudi NAMAA TTS inference for RATEB AI
---

# RATEB NAMAA Saudi TTS (ZeroGPU)

Minimal Gradio ZeroGPU inference for `NAMAA-Space/NAMAA-Saudi-TTS` (MIT).

- Server-to-server only: requests must include header `X-Rateb-Namaa-Secret`.
- Set Space secret `RATEB_NAMAA_SECRET` (Hugging Face Secrets). Never commit it.
- Does not store or log request text or audio.
