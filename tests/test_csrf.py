"""Run with python3 -B -m unittest discover -s tests -v."""

from contextlib import contextmanager
from http.cookiejar import CookieJar
import json
import os
from pathlib import Path
import shutil
import socket
import subprocess
from tempfile import TemporaryDirectory
import time
import unittest
from urllib.error import HTTPError, URLError
from urllib.parse import urlencode
from urllib.request import HTTPCookieProcessor, Request, build_opener

from fixtures import copy_application


PROJECT = Path(__file__).resolve().parents[1]
PROBE = r"""<?php
namespace App\Csrfprobe;

use Fusion\Core\Controller;
use Fusion\Core\Csrf;
use Fusion\Core\Session;

final class CsrfprobeController extends Controller
{
    protected function views(): array
    {
        return [
            'probe' => [
                'aliases' => ['csrf-probe'],
                'methods' => ['GET', 'POST'],
                'handlers' => ['GET' => 'read', 'POST' => 'write'],
            ],
        ];
    }

    protected function api(): array
    {
        return ['csrf-probe' => ['POST' => 'write']];
    }

    public function read(): void
    {
        Csrf::protect($this->request);
        $this->json(['token' => Csrf::token()]);
    }

    public function write(): void
    {
        Csrf::protect($this->request);
        switch ($this->request->post('action')) {
            case 'login':
                Session::login(['id' => 1]);
                break;
            case 'logout':
                Session::logout();
                break;
        }
        $this->json(['token' => Csrf::token()]);
    }
}
"""


@contextmanager
def server(extra_modules=None, overrides=None, setup=None):
    with TemporaryDirectory(prefix='fusionphp-csrf-') as directory:
        root = Path(directory)
        copy_application(PROJECT, root)
        for folder in ('fusionphp', 'plugins', 'public'):
            shutil.copytree(PROJECT / folder, root / folder)
        shutil.copy(PROJECT / 'fusion', root)
        (root / 'storage' / 'sessions').mkdir(parents=True)
        module = root / 'app' / 'csrfprobe'
        module.mkdir()
        (module / 'CsrfprobeController.php').write_text(PROBE, encoding='utf-8')
        for module_name, controller in (extra_modules or {}).items():
            folder = root / 'app' / module_name
            folder.mkdir()
            (folder / f'{module_name.capitalize()}Controller.php').write_text(controller, encoding='utf-8')
        with socket.socket() as listener:
            listener.bind(('127.0.0.1', 0))
            port = listener.getsockname()[1]
        origin = f'http://127.0.0.1:{port}'
        env = {
            **os.environ, 'APP_MODE': 'both', 'APP_BASE_PATH': '', 'APP_URL': origin,
            'FUSIONPHP_DEBUG': 'false', 'API_SESSION': 'true', 'API_PREFIX': '/api',
            **(overrides or {}),
        }
        if setup is not None:
            setup(root)
        with (root / 'server.log').open('w+') as log:
            command = ['php', '-d', f'session.save_path={root / "storage/sessions"}',
                       '-S', f'127.0.0.1:{port}', '-t', 'public']
            process = subprocess.Popen(command, cwd=root, env=env, stdout=log, stderr=log)
            try:
                for _ in range(60):
                    try:
                        Client(origin + env['APP_BASE_PATH']).send('/')
                        break
                    except URLError:
                        if process.poll() is not None:
                            break
                        time.sleep(0.05)
                else:
                    raise RuntimeError('PHP server did not start.')
                if process.poll() is not None:
                    log.seek(0)
                    raise RuntimeError(log.read())
                yield origin
            finally:
                process.terminate()
                try:
                    process.wait(timeout=3)
                except subprocess.TimeoutExpired:
                    process.kill()
                    process.wait()


class Client:
    def __init__(self, origin):
        self.origin = origin
        self.opener = build_opener(HTTPCookieProcessor(CookieJar()))

    def send(self, path, data=None, headers=None):
        req = Request(self.origin + '/index.php' + path, data=data, headers=headers or {})
        try:
            response = self.opener.open(req, timeout=3)
        except HTTPError as error:
            response = error
        with response:
            return response.status, response.read().decode()

    def get_token(self):
        status, payload = self.send('/csrf-probe')
        if status != 200:
            raise RuntimeError(f'Unexpected token response: {status}')
        return json.loads(payload)['token']

    def submit_form(self, fields, path='/csrf-probe', headers=None):
        return self.send(path, urlencode(fields, doseq=True).encode(), headers)

    def submit_json(self, fields, headers=None, path='/api/csrf-probe'):
        return self.send(path, json.dumps(fields).encode(), {
            'Content-Type': 'application/json', **(headers or {}),
        })


class CsrfTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        running = server()
        cls.origin = running.__enter__()
        cls.addClassCleanup(running.__exit__, None, None, None)

    def setUp(self):
        self.client = Client(self.origin)
        self.token = self.client.get_token()

    def test_form_token_validation(self):
        self.assertEqual(self.client.get_token(), self.token)
        self.assertEqual(self.client.submit_form({'_csrf': self.token})[0], 200)
        for fields in ({}, {'_csrf': 'invalid'}, {'_csrf[]': [self.token]}):
            with self.subTest(fields=list(fields)):
                self.assertEqual(self.client.submit_form(fields)[0], 403)
        self.assertEqual(self.client.submit_form({}, f'/csrf-probe?_csrf={self.token}')[0], 403)

    def test_ajax_validation(self):
        self.assertEqual(self.client.submit_json({'_csrf': self.token})[0], 200)
        self.assertEqual(self.client.submit_json({}, {'X-CSRF-Token': self.token})[0], 200)
        self.assertEqual(self.client.submit_json({'_csrf': self.token}, {'X-CSRF-Token': 'invalid'})[0], 403)
        self.assertEqual(self.client.submit_json({'_csrf': [self.token]})[0], 403)
        self.assertEqual(self.client.submit_json({'name': 'Ada'}, path='/api/example')[0], 200)

    def test_token_requires_its_original_session(self):
        other = Client(self.origin)
        self.assertEqual(other.submit_form({'_csrf': self.token})[0], 403)
        self.assertNotEqual(other.get_token(), self.token)
        self.assertEqual(other.submit_form({'_csrf': self.token})[0], 403)

    def test_login_and_logout_rotate_tokens(self):
        current = self.token
        for action in ('login', 'logout'):
            status, payload = self.client.submit_form({'_csrf': current, 'action': action})
            self.assertEqual(status, 200)
            updated = json.loads(payload)['token']
            self.assertNotEqual(updated, current)
            self.assertEqual(self.client.submit_form({'_csrf': current})[0], 403)
            self.assertEqual(self.client.submit_form({'_csrf': updated})[0], 200)
            current = updated

    def test_documentation_renders(self):
        for route, marker in (
            ('/classes', 'Fusion\\Core\\Csrf'),
            ('/services', 'id="csrf"'),
            ('/fusionphpstyle', 'X-CSRF-Token'),
        ):
            with self.subTest(route=route):
                status, html = self.client.send(route)
                self.assertEqual(status, 200)
                self.assertIn(marker, html)
