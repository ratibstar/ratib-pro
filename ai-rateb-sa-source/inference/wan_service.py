"""Private Wan2.2-TI2V-5B job API. Does not load weights or run inference."""

from __future__ import annotations

import base64
import binascii
import hmac
import json
import os
import re
import shutil
import subprocess
import tempfile
import threading
import uuid
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path

MAX_JOBS = 1
MAX_PROMPT = 400
MAX_IMAGE_BYTES = 2 * 1024 * 1024
ALLOWED_RATIOS = {'720:1280', '1280:720', '720:720'}
ALLOWED_SECONDS = 5
TASK_ID = re.compile(r'^[A-Za-z0-9_-]{8,80}$')


class InferenceUnavailable(Exception):
    """Raised when weights are absent. Inference is intentionally not implemented."""


class JobStore:
    def __init__(self) -> None:
        self._jobs: dict[str, dict] = {}
        self._files: dict[str, bytes] = {}
        self._lock = threading.Lock()

    def submit(self, kind: str, body: dict, runner) -> tuple[int, dict]:
        error = validate_job(kind, body)
        if error:
            return 422, {'error': error}
        with self._lock:
            active = sum(1 for job in self._jobs.values() if job['status'] in ('queued', 'processing'))
            if active >= MAX_JOBS:
                return 429, {'error': 'busy'}
            task_id = uuid.uuid4().hex
            self._jobs[task_id] = {
                'status': 'processing',
                'kind': kind,
                'url': '',
                'error': '',
            }
        self._run(task_id, kind, body, runner)
        return 200, {'task_id': task_id}

    def _run(self, task_id: str, kind: str, body: dict, runner) -> None:
        try:
            video = runner(kind, body)
        except InferenceUnavailable:
            self._fail(task_id, 'unavailable')
            return
        except Exception:
            self._fail(task_id, 'failed')
            return
        if not isinstance(video, (bytes, bytearray)) or b'ftyp' not in bytes(video)[:32] or len(video) < 1024:
            self._fail(task_id, 'failed')
            return
        with self._lock:
            self._files[task_id] = bytes(video)
            job = self._jobs[task_id]
            job['status'] = 'completed'
            job['url'] = '/v1/files/' + task_id

    def _fail(self, task_id: str, code: str) -> None:
        with self._lock:
            job = self._jobs.get(task_id)
            if job is None:
                return
            job['status'] = 'failed'
            job['error'] = code
            job['url'] = ''

    def status(self, task_id: str, public_base: str) -> tuple[int, dict]:
        if TASK_ID.fullmatch(task_id) is None:
            return 404, {'status': 'failed', 'url': '', 'error': 'rejected'}
        with self._lock:
            job = self._jobs.get(task_id)
            if job is None:
                return 404, {'status': 'failed', 'url': '', 'error': 'rejected'}
            url = ''
            if job['status'] == 'completed':
                url = public_base.rstrip('/') + job['url']
            return 200, {'status': job['status'], 'url': url, 'error': job['error']}

    def file(self, task_id: str) -> tuple[int, bytes]:
        if TASK_ID.fullmatch(task_id) is None:
            return 404, b''
        with self._lock:
            body = self._files.get(task_id)
        if body is None:
            return 404, b''
        return 200, body


def validate_job(kind: str, body: dict) -> str:
    if kind not in ('text', 'image'):
        return 'unsupported'
    if not isinstance(body, dict):
        return 'invalid'
    prompt = body.get('prompt')
    if not isinstance(prompt, str) or not prompt.strip() or len(prompt) > MAX_PROMPT:
        return 'invalid'
    if body.get('seconds') != ALLOWED_SECONDS:
        return 'invalid'
    if body.get('ratio') not in ALLOWED_RATIOS:
        return 'invalid'
    if kind == 'image':
        image = body.get('prompt_image')
        if not isinstance(image, str) or not image.startswith('data:image/'):
            return 'invalid'
        if ';base64,' not in image or image.lower().startswith(('http://', 'https://', 'file:')):
            return 'invalid'
        encoded = image.split(';base64,', 1)[1]
        try:
            raw = base64.b64decode(encoded, validate=True)
        except (binascii.Error, ValueError):
            return 'invalid'
        if len(raw) < 16 or len(raw) > MAX_IMAGE_BYTES:
            return 'invalid'
    return ''


TI2V_SIZES = {'720:1280': '704*1280', '1280:720': '1280*704'}
INFERENCE_TIMEOUT = 1800


def official_runner(kind: str, body: dict, popen=None, gpu_check=None) -> bytes:
    """Run the official single-GPU TI2V command. Does not download weights."""
    repo = Path(os.environ.get('WAN_REPO', '')).expanduser()
    ckpt = Path(os.environ.get('WAN_CKPT_DIR', '')).expanduser()
    output = Path(os.environ.get('WAN_OUTPUT_DIR', '')).expanduser()
    python = os.environ.get('WAN_PYTHON', 'python')
    script = repo / 'generate.py'
    if not repo.is_dir() or not script.is_file():
        raise InferenceUnavailable('Wan2.2 repository is not available')
    if not ckpt.is_dir():
        raise InferenceUnavailable('Wan2.2-TI2V-5B weights are not installed')
    size = TI2V_SIZES.get(str(body.get('ratio')))
    if size is None:
        raise InferenceUnavailable('ratio is not a documented TI2V size')
    if gpu_check is None:
        gpu_check = default_gpu_check
    gpu_check()
    output.mkdir(parents=True, exist_ok=True)
    work = Path(tempfile.mkdtemp(prefix='wan-', dir=output))
    try:
        command = [
            python,
            str(script),
            '--task', 'ti2v-5B',
            '--size', size,
            '--ckpt_dir', str(ckpt),
            '--offload_model', 'True',
            '--convert_model_dtype',
            '--t5_cpu',
            '--frame_num', '121',
            '--prompt', str(body.get('prompt') or ''),
            '--save_file', str(work / 'out.mp4'),
        ]
        if kind == 'image':
            image_path = work / 'input.png'
            image_path.write_bytes(_image_bytes(str(body.get('prompt_image') or '')))
            command.extend(['--image', str(image_path)])
        _contained(work / 'out.mp4', output)
        process = (popen or subprocess.Popen)(
            command,
            cwd=str(repo),
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
        )
        try:
            process.communicate(timeout=INFERENCE_TIMEOUT)
        except subprocess.TimeoutExpired:
            process.kill()
            process.communicate()
            raise TimeoutError('inference timed out')
        if process.returncode != 0:
            raise RuntimeError('inference failed')
        video_path = _contained(work / 'out.mp4', output)
        video = video_path.read_bytes()
        if b'ftyp' not in video[:32] or len(video) < 1024:
            raise RuntimeError('invalid video output')
        return video
    finally:
        shutil.rmtree(work, ignore_errors=True)


def default_gpu_check() -> None:
    try:
        completed = subprocess.run(['nvidia-smi', '-L'], capture_output=True, timeout=5, check=False)
    except (OSError, subprocess.TimeoutExpired) as error:
        raise InferenceUnavailable('GPU runtime is not available') from error
    if completed.returncode != 0:
        raise InferenceUnavailable('GPU runtime is not available')


def _image_bytes(value: str) -> bytes:
    encoded = value.split(';base64,', 1)[1]
    return base64.b64decode(encoded, validate=True)


def _contained(path: Path, root: Path) -> Path:
    resolved = path.resolve()
    if root.resolve() not in resolved.parents and resolved != root.resolve():
        raise RuntimeError('output path escaped storage')
    return resolved


def token_ok(header: str, expected: str) -> bool:
    if not expected or not header.startswith('Bearer '):
        return False
    given = header[7:].strip()
    return hmac.compare_digest(given, expected)


class ServiceHandler(BaseHTTPRequestHandler):
    store: JobStore = JobStore()
    token: str = ''
    runner = staticmethod(official_runner)

    def log_message(self, fmt: str, *args) -> None:
        return

    def _public_base(self) -> str:
        host = self.headers.get('Host', '127.0.0.1')
        scheme = 'https' if self.headers.get('X-Forwarded-Proto') == 'https' else 'http'
        return scheme + '://' + host

    def _send(self, code: int, payload: dict | None = None, body: bytes | None = None, content_type: str = 'application/json') -> None:
        raw = body if body is not None else json.dumps(payload or {}, ensure_ascii=False).encode('utf-8')
        self.send_response(code)
        self.send_header('Content-Type', content_type)
        self.send_header('Content-Length', str(len(raw)))
        self.end_headers()
        self.wfile.write(raw)

    def _authorized(self) -> bool:
        return token_ok(self.headers.get('Authorization', ''), self.token)

    def do_GET(self) -> None:
        if not self._authorized():
            self._send(401, {'error': 'auth'})
            return
        path = self.path.split('?', 1)[0]
        if path == '/health':
            self._send(200, {'ok': True, 'model_loaded': False})
            return
        if path.startswith('/v1/tasks/'):
            code, payload = self.store.status(path[len('/v1/tasks/'):], self._public_base())
            self._send(code, payload)
            return
        if path.startswith('/v1/files/'):
            code, data = self.store.file(path[len('/v1/files/'):])
            if code != 200:
                self._send(404, {'error': 'rejected'})
                return
            self._send(200, body=data, content_type='video/mp4')
            return
        self._send(404, {'error': 'rejected'})

    def do_POST(self) -> None:
        if not self._authorized():
            self._send(401, {'error': 'auth'})
            return
        path = self.path.split('?', 1)[0]
        length = int(self.headers.get('Content-Length', '0') or 0)
        if length < 2 or length > 6 * 1024 * 1024:
            self._send(422, {'error': 'invalid'})
            return
        try:
            body = json.loads(self.rfile.read(length).decode('utf-8'))
        except (UnicodeError, json.JSONDecodeError):
            self._send(422, {'error': 'invalid'})
            return
        if path == '/v1/text_to_video':
            kind = 'text'
        elif path == '/v1/image_to_video':
            kind = 'image'
        else:
            self._send(404, {'error': 'unsupported'})
            return
        code, payload = self.store.submit(kind, body, self.runner)
        self._send(code, payload)


def serve(host: str, port: int, token: str) -> None:
    ServiceHandler.token = token
    ServiceHandler.store = JobStore()
    ServiceHandler.runner = official_runner
    server = ThreadingHTTPServer((host, port), ServiceHandler)
    server.serve_forever()


if __name__ == '__main__':
    bind = os.environ.get('RATEB_PRIVATE_VIDEO_BIND', '127.0.0.1')
    port = int(os.environ.get('RATEB_PRIVATE_VIDEO_PORT', '8091'))
    token = os.environ.get('RATEB_PRIVATE_VIDEO_TOKEN', '')
    if not token or bind not in ('127.0.0.1', 'localhost'):
        raise SystemExit('Refusing to listen without a token on a non-loopback address')
    serve(bind, port, token)
