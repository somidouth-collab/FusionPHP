"""Integration coverage for sign-in and permissions."""

import json
import unittest

from test_csrf import Client, server


PROBE = r"""<?php
namespace App\Authprobe;

use Fusion\Core\Auth;
use Fusion\Core\Controller;
use Fusion\Core\Csrf;
use Fusion\Core\Session;

final class AuthprobeController extends Controller
{
    protected function api(): array
    {
        return [
            'auth-probe/session' => ['GET' => 'identity'],
            'auth-probe/login' => ['POST' => 'login'],
            'auth-probe/logout' => ['POST' => 'logout'],
            'auth-probe/private' => ['GET' => 'privatePage'],
        ];
    }

    public function identity(): void
    {
        $this->json(['user' => Session::user(), 'token' => Csrf::token()]);
    }

    public function login(): void
    {
        Csrf::protect($this->request);
        $name = $this->request->json('name');
        $password = $this->request->json('password');
        $account = in_array($name, ['editor', 'viewer'], true) ? [
            'id' => $name === 'editor' ? 1 : 2,
            'name' => $name,
            'password_hash' => password_hash('example-password', PASSWORD_BCRYPT, ['cost' => 4]),
            'permissions' => $name === 'editor' ? ['articles.write'] : ['articles.read'],
            'secret' => 'never put this in the session',
        ] : null;
        if (!is_string($password) || !Auth::attempt($password, $account)) {
            $this->abort(401, 'Invalid credentials.');
        }
        $this->identity();
    }

    public function privatePage(): void
    {
        Auth::requirePermission('articles.write');
        $this->json(['allowed' => true]);
    }

    public function logout(): void
    {
        Csrf::protect($this->request);
        Session::logout();
        $this->identity();
    }
}
"""


class AuthTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        running = server({'authprobe': PROBE})
        cls.origin = running.__enter__()
        cls.addClassCleanup(running.__exit__, None, None, None)

    def setUp(self):
        self.client = Client(self.origin)
        self.token = self.client.get_token()

    def login(self, name, password='example-password', token=None):
        return self.client.submit_json(
            {'name': name, 'password': password, '_csrf': self.token if token is None else token},
            path='/api/auth-probe/login',
        )

    def test_invalid_credentials_and_missing_csrf(self):
        self.assertEqual(self.client.send('/api/auth-probe/private')[0], 401)
        self.assertEqual(self.login('editor', token='wrong')[0], 403)
        for name, password in [('editor', 'wrong'), ('unknown', 'example-password')]:
            with self.subTest(name=name):
                status, body = self.login(name, password)
                self.assertEqual(status, 401)
                self.assertEqual(json.loads(body)['error'], 'Invalid credentials.')
        self.assertIsNone(json.loads(self.client.send('/api/auth-probe/session')[1])['user'])

    def test_sign_in_sanitizes_identity_and_enforces_permission(self):
        status, body = self.login('editor')
        self.assertEqual(status, 200)
        payload = json.loads(body)
        self.assertNotEqual(payload['token'], self.token)
        self.assertEqual(payload['user'], {'id': 1, 'name': 'editor', 'permissions': ['articles.write']})
        self.assertEqual(self.client.send('/api/auth-probe/private')[0], 200)
        self.assertEqual(Client(self.origin).send('/api/auth-probe/private')[0], 401)

    def test_permission_denial_and_sign_out(self):
        status, body = self.login('viewer')
        self.assertEqual(status, 200)
        token = json.loads(body)['token']
        self.assertEqual(self.client.send('/api/auth-probe/private')[0], 403)
        self.assertEqual(self.client.submit_json({'_csrf': self.token}, path='/api/auth-probe/logout')[0], 403)
        status, body = self.client.submit_json({'_csrf': token}, path='/api/auth-probe/logout')
        self.assertEqual(status, 200)
        self.assertIsNone(json.loads(body)['user'])
        self.assertEqual(self.client.send('/api/auth-probe/private')[0], 401)

    def test_documentation_renders(self):
        for route, marker in (('/services', 'id="auth"'), ('/classes', 'Fusion\\Core\\Auth')):
            with self.subTest(route=route):
                status, html = self.client.send(route)
                self.assertEqual(status, 200)
                self.assertIn(marker, html)
