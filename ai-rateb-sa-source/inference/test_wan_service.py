"""Offline tests. No model load, no socket bind, no generation."""

import base64
import json
import os
import subprocess
import tempfile
import threading
import unittest
from pathlib import Path

import wan_service


TINY = b'xxxxftyp' + b'\x00' * 1024
IMAGE = 'data:image/png;base64,' + base64.b64encode(b'\x89PNG' + b'\x00' * 32).decode('ascii')


def fake_runner(kind, body):
    return TINY


class ContractTests(unittest.TestCase):
    def setUp(self):
        self.store = wan_service.JobStore()

    def test_missing_auth_and_health_shape(self):
        self.assertFalse(wan_service.token_ok('', 'secret'))
        self.assertFalse(wan_service.token_ok('Bearer wrong', 'secret'))
        self.assertTrue(wan_service.token_ok('Bearer secret', 'secret'))

    def test_rejects_remote_image_and_long_duration(self):
        self.assertEqual(wan_service.validate_job('image', {
            'prompt': 'motion',
            'seconds': 5,
            'ratio': '720:1280',
            'prompt_image': 'https://example.test/a.png',
        }), 'invalid')
        self.assertEqual(wan_service.validate_job('text', {
            'prompt': 'motion',
            'seconds': 40,
            'ratio': '720:1280',
        }), 'invalid')
        self.assertEqual(wan_service.validate_job('video', {'prompt': 'x', 'seconds': 5, 'ratio': '720:1280'}), 'unsupported')

    def test_text_job_lifecycle_matches_php_contract(self):
        code, created = self.store.submit('text', {
            'prompt': 'slow camera move, no text',
            'seconds': 5,
            'ratio': '720:1280',
        }, fake_runner)
        self.assertEqual(code, 200)
        task_id = created['task_id']
        self.assertRegex(task_id, r'^[A-Za-z0-9_-]{8,80}$')
        status_code, status = self.store.status(task_id, 'http://127.0.0.1:8091')
        self.assertEqual(status_code, 200)
        self.assertEqual(status['status'], 'completed')
        self.assertTrue(status['url'].startswith('http://127.0.0.1:8091/v1/files/'))
        file_code, body = self.store.file(task_id)
        self.assertEqual(file_code, 200)
        self.assertIn(b'ftyp', body[:32])
        self.assertGreaterEqual(len(body), 1024)

    def test_image_job_and_failure_without_weights(self):
        code, created = self.store.submit('image', {
            'prompt': 'product still',
            'prompt_image': IMAGE,
            'seconds': 5,
            'ratio': '1280:720',
        }, wan_service.official_runner)
        self.assertEqual(code, 200)
        _, status = self.store.status(created['task_id'], 'http://127.0.0.1:8091')
        self.assertEqual(status['status'], 'failed')
        self.assertEqual(status['url'], '')
        self.assertNotIn('token', json.dumps(status))

    def test_second_active_job_is_rejected(self):
        started = threading.Event()
        release = threading.Event()

        def hold(kind, body):
            started.set()
            self.assertTrue(release.wait(2))
            return TINY

        result = {}

        def first():
            result['first'] = self.store.submit('text', {'prompt': 'a', 'seconds': 5, 'ratio': '720:720'}, hold)

        worker = threading.Thread(target=first)
        worker.start()
        self.assertTrue(started.wait(2))
        second = self.store.submit('text', {'prompt': 'b', 'seconds': 5, 'ratio': '720:720'}, fake_runner)
        release.set()
        worker.join(2)
        self.assertEqual(result['first'][0], 200)
        self.assertEqual(second[0], 429)


class RunnerTests(unittest.TestCase):
    def setUp(self):
        self.old = {key: os.environ.get(key) for key in ('WAN_REPO', 'WAN_CKPT_DIR', 'WAN_OUTPUT_DIR', 'WAN_PYTHON')}
        self.root = Path(tempfile.mkdtemp())
        self.repo = self.root / 'Wan2.2'
        self.ckpt = self.root / 'Wan2.2-TI2V-5B'
        self.output = self.root / 'out'
        self.repo.mkdir()
        (self.repo / 'generate.py').write_text('# official entry\n', encoding='utf-8')
        self.ckpt.mkdir()
        self.output.mkdir()
        os.environ['WAN_REPO'] = str(self.repo)
        os.environ['WAN_CKPT_DIR'] = str(self.ckpt)
        os.environ['WAN_OUTPUT_DIR'] = str(self.output)
        os.environ['WAN_PYTHON'] = 'python'

    def tearDown(self):
        for key, value in self.old.items():
            if value is None:
                os.environ.pop(key, None)
            else:
                os.environ[key] = value

    def test_missing_repository_and_weights(self):
        os.environ['WAN_REPO'] = str(self.root / 'missing')
        with self.assertRaises(wan_service.InferenceUnavailable):
            wan_service.official_runner('text', {'prompt': 'a', 'ratio': '720:1280'}, gpu_check=lambda: None)
        os.environ['WAN_REPO'] = str(self.repo)
        os.environ['WAN_CKPT_DIR'] = str(self.root / 'no-weights')
        with self.assertRaises(wan_service.InferenceUnavailable):
            wan_service.official_runner('text', {'prompt': 'a', 'ratio': '720:1280'}, gpu_check=lambda: None)

    def test_missing_gpu(self):
        def no_gpu():
            raise wan_service.InferenceUnavailable('GPU runtime is not available')
        with self.assertRaises(wan_service.InferenceUnavailable):
            wan_service.official_runner('text', {'prompt': 'a', 'ratio': '1280:720'}, gpu_check=no_gpu)

    def test_timeout_failed_process_and_bad_output(self):
        class TimeoutProcess:
            def communicate(self, timeout=None):
                if timeout is None:
                    return b'', b''
                raise subprocess.TimeoutExpired(cmd='generate.py', timeout=timeout)
            def kill(self):
                return None
        with self.assertRaises(TimeoutError):
            wan_service.official_runner('text', {'prompt': 'a', 'ratio': '720:1280'}, popen=lambda *args, **kwargs: TimeoutProcess(), gpu_check=lambda: None)

        class Failed:
            returncode = 1
            def communicate(self, timeout=None):
                return b'', b'fail'
        with self.assertRaises(RuntimeError):
            wan_service.official_runner('text', {'prompt': 'a', 'ratio': '720:1280'}, popen=lambda *args, **kwargs: Failed(), gpu_check=lambda: None)

        class BadOutput:
            returncode = 0
            def communicate(self, timeout=None):
                save = _save_file(self.command)
                Path(save).write_bytes(b'not-a-video')
                return b'', b''
            def __init__(self, command):
                self.command = command
        with self.assertRaises(RuntimeError):
            wan_service.official_runner('text', {'prompt': 'a', 'ratio': '720:1280'}, popen=lambda command, **kwargs: BadOutput(command), gpu_check=lambda: None)

    def test_mocked_success_uses_documented_flags(self):
        seen = {}

        class Success:
            returncode = 0
            def __init__(self, command):
                seen['command'] = command
            def communicate(self, timeout=None):
                Path(_save_file(seen['command'])).write_bytes(TINY)
                return b'', b''

        video = wan_service.official_runner('image', {
            'prompt': 'product still',
            'ratio': '720:1280',
            'prompt_image': IMAGE,
        }, popen=lambda command, **kwargs: Success(command), gpu_check=lambda: None)
        self.assertIn(b'ftyp', video[:32])
        command = seen['command']
        self.assertIn('--task', command)
        self.assertIn('ti2v-5B', command)
        self.assertIn('704*1280', command)
        self.assertIn('--offload_model', command)
        self.assertIn('--convert_model_dtype', command)
        self.assertIn('--t5_cpu', command)
        self.assertIn('--frame_num', command)
        self.assertIn('121', command)
        self.assertIn('--image', command)
        self.assertTrue(str(Path(command[command.index('--save_file') + 1]).resolve()).startswith(str(self.output.resolve())))


def _save_file(command):
    return command[command.index('--save_file') + 1]


if __name__ == '__main__':
    unittest.main()
