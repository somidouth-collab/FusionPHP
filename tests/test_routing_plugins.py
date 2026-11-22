"""Regression coverage for public routes and the plugin installer."""

import base64
import json
import re
import subprocess
import unittest
from urllib.error import HTTPError
from urllib.request import Request

from test_csrf import Client, server


class RoutingTests(unittest.TestCase):
    def test_web_api_and_method_errors_under_a_mount_path(self):
        with server(overrides={'APP_BASE_PATH': '/demo', 'API_PREFIX': '/json'}) as origin:
            client = Client(origin + '/demo')
            for path, marker in (
                ('/', 'Build an application'),
                ('/framework', 'Create a page'),
                ('/templates', 'Render values safely'),
                ('/api-guide', 'API response checklist'),
                ('/services', 'id="auth"'),
                ('/classes', 'Class reference'),
                ('/appearance', 'id="appearance-example"'),
                ('/fusionphpstyle', 'id="javascript"'),
                ('/plugins', 'Build a plugin'),
            ):
                with self.subTest(path=path):
                    status, html = client.send(path)
                    self.assertEqual(status, 200)
                    self.assertIn(marker, html)
                    self.assertIn('/demo/assets/css/fusionphpstyle.css', html)
            status, body = client.submit_json({'name': 'Ada'}, path='/json/example')
            self.assertEqual((status, json.loads(body)), (200, {'data': {'name': 'Ada'}}))
            with self.assertRaises(HTTPError) as result:
                client.opener.open(Request(origin + '/demo/index.php/json/example'), timeout=3)
            with result.exception as response:
                self.assertEqual(response.status, 405)
                self.assertEqual(response.headers['Allow'], 'POST')
            self.assertEqual(client.send('/json/missing')[0], 404)
            self.assertEqual(client.send('/missing')[0], 404)

    def test_web_and_api_modes_expose_only_their_routes(self):
        for mode, expected in [('web', (200, 404)), ('api', (404, 200))]:
            with self.subTest(mode=mode), server(overrides={'APP_MODE': mode}) as origin:
                client = Client(origin)
                self.assertEqual((client.send('/framework')[0], client.send('/api')[0]), expected)


class PluginTests(unittest.TestCase):
    def test_scaffold_install_and_refresh_the_route_cache(self):
        with server(setup=self.set_project) as origin:
            client = Client(origin)
            self.assertEqual(client.send('/plugin/status-page')[0], 404)
            self.assertIn('Created plugins/status-page', self.cli('plugin:make', 'status-page'))
            self.assertIn('already exists', self.cli('plugin:make', 'status-page', status=1))
            self.assertIn('lowercase plugin name', self.cli('plugin:make', '../invalid', status=1))
            self.assertIn('Installed status-page', self.cli('plugin:install', 'status-page'))
            status, page = client.send('/plugin/status-page')
            self.assertEqual(status, 200)
            self.assertIn('<h1>Status Page</h1>', page)
            self.assertIn('already installed', self.cli('plugin:install', 'status-page'))
            self.assertRegex(self.cli('plugin:list'), r'status-page\s+\[installed\]')

    def test_admin_installation_requires_credentials_and_rotates_password(self):
        with server(setup=self.set_project) as origin:
            client = Client(origin)
            self.assertEqual(client.send('/admin')[0], 404)
            password = self.password(self.cli('plugin:install', 'admin-panel'))
            self.assertEqual(client.send('/admin')[0], 401)
            self.assertEqual(client.send('/admin', headers=self.authorization('wrong'))[0], 401)
            status, page = client.send('/admin', headers=self.authorization(password))
            self.assertEqual(status, 200)
            self.assertIn('<h1>Admin Panel</h1>', page)
            self.assertNotIn('Password:', self.cli('plugin:install', 'admin-panel'))
            replacement = self.password(self.cli('plugin:reset-password', 'admin-panel'))
            self.assertEqual(client.send('/admin', headers=self.authorization(password))[0], 401)
            self.assertEqual(client.send('/admin', headers=self.authorization(replacement))[0], 200)

    def set_project(self, path):
        self.project = path

    def cli(self, *args, status=0):
        result = subprocess.run(
            ['php', 'fusion', *args], cwd=self.project, capture_output=True, text=True,
        )
        self.assertEqual(result.returncode, status, result.stderr)
        return result.stdout + result.stderr

    def password(self, output):
        match = re.search(r'^Password: ([0-9a-f]{40})$', output, re.MULTILINE)
        self.assertIsNotNone(match, 'The installer did not print a generated password.')
        return match[1]

    @staticmethod
    def authorization(password):
        credentials = base64.b64encode(f'admin:{password}'.encode()).decode()
        return {'Authorization': f'Basic {credentials}'}
