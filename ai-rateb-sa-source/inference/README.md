# Private Wan2.2 inference service

This process is separate from the PHP application. It speaks the HTTP contract already implemented by `PrivateVideoProvider`. It does not download weights and does not generate video.

## Contract the PHP adapter calls

Authentication on every request, including health: `Authorization: Bearer <RATEB_PRIVATE_VIDEO_TOKEN>`.

| Call | Body | Success |
| --- | --- | --- |
| `POST /v1/text_to_video` | `prompt`, `seconds`, `ratio` | `{"task_id":"<8-80 chars>"}` |
| `POST /v1/image_to_video` | `prompt`, `prompt_image` (`data:image/...;base64,...`), `seconds`, `ratio` | same |
| `GET /v1/tasks/{task_id}` | none | `status` is `processing`, `completed`, or `failed`. `url` is set only when completed, and must use this service's scheme, host, and port. |
| `GET /v1/files/{task_id}` | none | MP4 bytes. The first 32 bytes contain `ftyp`. Length is at least 1024. |
| `GET /health` | none | `{"ok":true,"model_loaded":false}` |

`seconds` must be `5`. `ratio` must be `720:1280`, `1280:720`, or `720:720`. Remote image URLs are rejected. `POST /v1/video_to_video` is not implemented. One job may be queued or processing at a time.

The completed file URL is built from the request `Host`. Point `RATEB_PRIVATE_VIDEO_URL` at that same origin or the PHP adapter will refuse the download.

## Model

Official sources, not yet executed here:

- Repository: https://github.com/Wan-Video/Wan2.2
- Weights: https://huggingface.co/Wan-AI/Wan2.2-TI2V-5B
- License: Apache 2.0
- Tasks: text-to-video and image-to-video
- Documented output: 720p at 24 fps, including a vertical size, on a GPU with at least 24 GB VRAM

`official_runner` builds the official single-GPU command and runs it only when `WAN_REPO`, `WAN_CKPT_DIR`, `WAN_OUTPUT_DIR`, and a GPU runtime are present. It does not download weights. If any of those are missing, the job fails as unavailable and no video is claimed.

The command, taken from the official README and `generate.py`, is:

```text
python generate.py --task ti2v-5B --size 704*1280 --ckpt_dir "$WAN_CKPT_DIR" --offload_model True --convert_model_dtype --t5_cpu --frame_num 121 --prompt "..." --save_file "$WAN_OUTPUT_DIR/job/out.mp4"
```

Image-to-video adds `--image` with a local file. `--size 1280*704` is the documented landscape size. `720:720` is rejected because `ti2v-5B` only allows `704*1280` and `1280*704`. `--frame_num 121` is the official TI2V default in `wan/configs/wan_ti2v_5B.py` (`sample_fps` 24, about 5 seconds). Do not run this command from the tests.

## Manual local setup

Do this only on a machine you already own. It does not rent a GPU or buy credits.

1. Install Python 3.11+ and the official Wan2.2 dependencies from that repository. Do not start this until a 24 GB GPU is already on the machine.
2. Download `Wan2.2-TI2V-5B` yourself from the Hugging Face link above.
3. Set `RATEB_PRIVATE_VIDEO_TOKEN` to a long random value. The server refuses to start on any address other than `127.0.0.1`.
4. Set `WAN_REPO` to the official checkout, `WAN_CKPT_DIR` to the downloaded `Wan2.2-TI2V-5B` directory, and `WAN_OUTPUT_DIR` to a private writable directory. Optional `WAN_PYTHON` selects the interpreter.
5. From this directory run `python wan_service.py`. It listens on `127.0.0.1:8091`.
6. The operator command to verify later, on that GPU machine only, is the `generate.py` command above. This repository does not execute it.
7. On the PHP host set `RATEB_VIDEO_PROVIDER=private`, `RATEB_PRIVATE_VIDEO_URL=http://127.0.0.1:8091`, and the same token. Keep that host able to reach only this bind address.

If the repository, weights, or GPU check is missing, a submitted job ends as unavailable. Real video generation has not been run.

## Offline tests

```text
cd ai-rateb-sa-source/inference
python test_wan_service.py
php ../tests/video-engine.php
```
