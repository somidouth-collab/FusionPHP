# FusionPHP 1.0.0

First public release of FusionPHP under the `somidouth-collab` GitHub account.

## Included

- Web and JSON routing, controllers, request validation, FPF templates, and PHP views.
- Sessions, flash messages, password verification, permissions, and opt-in CSRF protection.
- PDO-backed database models and transactional SQLite and PostgreSQL migrations.
- FusionPHPStyle, appearance presets, and first-party JavaScript components and AJAX helpers.
- A plugin CLI, plugin scaffolding, and a bundled password-protected Admin Panel.
- Guided documentation, a class reference, integration tests, and a GitHub Actions workflow.

## Install and upgrade notes

- Start with the [README](README.md) for local setup and the [deployment guide](docs/DEPLOYMENT.md) for a production document root and environment settings.
- Earlier development copies that link to `fusionphp.css` should use `fusionphpstyle.css`.
- CSRF protection is opt-in. Call `Csrf::protect()` before writes in session-backed handlers and include the token in the form or AJAX request.
- Keep the original files for migrations already applied. SQLite and PostgreSQL are supported by the migration runner. MySQL schema changes use external tooling.
- Plugin installation preserves an existing installation. Preserve the Admin Panel password hash across deployments, or generate a new password with `php fusion plugin:reset-password admin-panel`.
