# FusionPHP

FusionPHP is a standalone PHP framework for server-rendered applications and JSON APIs. Controllers, models, and views live together in application modules. The starter includes documentation, the FusionPHPStyle CSS toolkit, a first-party JavaScript and AJAX library, and a plugin CLI.

## Run locally

Use PHP 8.1 or later with sessions enabled. The documentation runs without a database. For database features, enable the PDO extension for your driver. The migration runner supports SQLite and PostgreSQL.

From the project directory, copy the default environment file on first setup and start PHP’s development server.

```sh
cp .env.example .env
php -S 127.0.0.1:8000 -t public
```

Open [the documentation](http://127.0.0.1:8000/) and select **Build your first page**. Use [the direct entry point](http://127.0.0.1:8000/index.php/framework) if your server lacks clean URLs.

| What to do | Where to start |
| --- | --- |
| Build a controller, model, and template | **Framework** and **Templates** in the documentation menu |
| Handle forms, sessions, and permissions | **Core services** |
| Build and call JSON endpoints | **API** and **JavaScript** |
| Style pages and configure themes | **CSS** and **Appearance** |
| Install or build a plugin | **Plugins** |

## Work with the CLI

```sh
php fusion plugin:list
php fusion plugin:install admin-panel
php fusion plugin:make status-page
php fusion migrate:make create_articles
php fusion migrate:status
php fusion migrate
```

After installing the Admin Panel, open `/admin` and sign in as `admin` with the generated password printed by the CLI. Keep that password in a secure place. The starter panel is a read-only dashboard.

The migration generator creates an editable PHP file. Define its `up` and `down` steps before running migrations. Find the complete SQLite example in **Core services → Database and models**.

## Test and deploy

Run the integration suite with Python 3 and PHP on your path. Enable PDO SQLite in CLI PHP for the database integration tests.

```sh
python3 -B -m unittest discover -s tests -v
```

See the [production deployment guide](docs/DEPLOYMENT.md) for Nginx, Apache, environment settings, and a release checklist. See [release notes](RELEASE_NOTES.md) for version details.
