"""Exercise migration commands against a temporary SQLite database."""

import os
from pathlib import Path
import shutil
import sqlite3
import subprocess
from tempfile import TemporaryDirectory
import unittest

from fixtures import copy_application


PROJECT = Path(__file__).resolve().parents[1]
PHP = ['php']
if extension := os.environ.get('FUSIONPHP_TEST_PDO_SQLITE_EXTENSION'):
    PHP.extend(['-d', f'extension={extension}'])
HAS_SQLITE = subprocess.check_output(
    [*PHP, '-r', 'echo in_array("sqlite", PDO::getAvailableDrivers(), true) ? "yes" : "no";'],
    text=True,
) == 'yes'


class MigrationTests(unittest.TestCase):
    def setUp(self):
        temporary = TemporaryDirectory(prefix='fusionphp-migrations-')
        self.addCleanup(temporary.cleanup)
        self.root = Path(temporary.name)
        copy_application(PROJECT, self.root)
        for folder in ('fusionphp', 'plugins'):
            shutil.copytree(PROJECT / folder, self.root / folder)
        shutil.copy(PROJECT / 'fusion', self.root)
        (self.root / 'storage').mkdir()
        self.database = self.root / 'storage/test.sqlite'
        self.environment = {**os.environ, 'DB_DRIVER': 'sqlite', 'DB_PATH': str(self.database)}

    def cli(self, *arguments, status=0, overrides=None):
        result = subprocess.run(
            [*PHP, 'fusion', *arguments], cwd=self.root,
            env={**self.environment, **(overrides or {})}, capture_output=True, text=True,
        )
        self.assertEqual(result.returncode, status, result.stdout + result.stderr)
        return result.stdout + result.stderr

    def migration(self, filename, up, down, directory='migrations'):
        folder = self.root / directory
        folder.mkdir(parents=True, exist_ok=True)
        file = folder / f'{filename}.php'
        file.write_text(f"""<?php
return [
    'up' => static function ($db): void {{ {up} }},
    'down' => static function ($db): void {{ {down} }},
];
""", encoding='utf-8')
        return file

    def tables(self):
        with sqlite3.connect(self.database) as db:
            return {name for (name,) in db.execute("SELECT name FROM sqlite_schema WHERE type = 'table'")}

    def test_scaffold_and_driver_errors(self):
        self.assertIn('Created migrations/', self.cli('migrate:make', 'create_articles'))
        (file,) = (self.root / 'migrations').glob('*.php')
        self.assertTrue(file.name.endswith('_create_articles.php'))
        subprocess.run([*PHP, '-l', file], capture_output=True, check=True)
        self.assertIn('lowercase migration name', self.cli('migrate:make', '../bad', status=1))
        self.assertIn('supports SQLite and PostgreSQL',
                      self.cli('migrate', status=1, overrides={'DB_DRIVER': 'mysql'}))

    @unittest.skipUnless(HAS_SQLITE, 'PDO SQLite is required')
    def test_apply_and_rollback_plugin_and_app_migrations(self):
        self.migration('20260101000000_create_articles',
                       "$db->query('CREATE TABLE articles (id INTEGER)');",
                       "$db->query('DROP TABLE articles');")
        self.cli('plugin:make', 'analytics')
        self.migration('20260102000000_create_events',
                       "$db->query('CREATE TABLE events (id INTEGER)');",
                       "$db->query('DROP TABLE events');", 'plugins/analytics/migrations')
        self.assertNotIn('create_events', self.cli('migrate:status'))
        self.cli('plugin:install', 'analytics')
        output = self.cli('migrate')
        self.assertLess(output.index('app/'), output.index('plugin:analytics/'))
        self.assertTrue({'articles', 'events'} <= self.tables())
        self.assertEqual(self.cli('migrate:status').count('applied'), 2)
        self.assertIn('No pending migrations', self.cli('migrate'))
        self.assertIn('Rolled back plugin:analytics/', self.cli('migrate:rollback'))
        self.assertNotIn('events', self.tables())
        self.assertIn('Rolled back app/', self.cli('migrate:rollback'))
        self.assertNotIn('articles', self.tables())

    @unittest.skipUnless(HAS_SQLITE, 'PDO SQLite is required')
    def test_failed_batch_and_drift(self):
        file = self.migration('20260101000000_create_articles',
                              "$db->query('CREATE TABLE articles (id INTEGER)');",
                              "$db->query('DROP TABLE articles');")
        bad = self.migration('20260102000000_fail',
                             "throw new RuntimeException('intentional failure');", '')
        self.assertIn('intentional failure', self.cli('migrate', status=1))
        self.assertNotIn('articles', self.tables())
        self.assertEqual(self.cli('migrate:status').count('pending'), 2)
        bad.unlink()
        self.cli('migrate')
        original = file.read_text(encoding='utf-8')
        file.write_text(original + '// changed\n', encoding='utf-8')
        self.assertIn('changed', self.cli('migrate:status'))
        self.assertIn('Restore its original file', self.cli('migrate:rollback', status=1))
        file.unlink()
        self.assertIn('missing', self.cli('migrate:status'))
        self.assertIn('Restore its original file', self.cli('migrate', status=1))
        file.write_text(original, encoding='utf-8')
        self.assertIn('Rolled back app/', self.cli('migrate:rollback'))
