<?php

namespace App\Framework;

final class FrameworkModel
{
    public function page(): array
    {
        return [
            'environmentExample' => <<<'EXAMPLE'
APP_NAME="My FusionPHP app"
APP_URL=http://127.0.0.1:8000
APP_MODE=both
FUSIONPHP_DEBUG=false
EXAMPLE,
            'welcomeController' => <<<'EXAMPLE'
<?php

namespace App\Welcome;

use Fusion\Core\Controller;

final class WelcomeController extends Controller
{
    protected function views(): array
    {
        return [
            'welcome' => [
                'aliases' => ['welcome', 'hello'],
                'methods' => ['GET'],
                'handlers' => ['GET' => 'show'],
            ],
        ];
    }

    public function show(): void
    {
        $name = $this->request->query('name');
        $data = (new WelcomeModel())->page(is_string($name) ? $name : '');
        $data['homeUrl'] = $this->request->url('/');
        $this->view('welcome', $data);
    }
}
EXAMPLE,
            'welcomeModel' => <<<'EXAMPLE'
<?php

namespace App\Welcome;

final class WelcomeModel
{
    public function page(string $name): array
    {
        return ['title' => 'Welcome', 'name' => trim($name)];
    }
}
EXAMPLE,
            'welcomeTemplate' => <<<'EXAMPLE'
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ title }}</title>
</head>
<body>
    <main>
        <h1>{{ title }}</h1>
        <p>Hello, {{ default name 'visitor' }}!</p>
        <a href="{{ url homeUrl }}">Home</a>
    </main>
</body>
</html>
EXAMPLE,
            'title' => 'Framework guide',
            'files' => [
                ['name' => 'ExampleController.php', 'role' => 'Declares aliases, HTTP methods, and handlers. Passes page data to the view.'],
                ['name' => 'ExampleModel.php', 'role' => 'Prepares page data. Extend the core Model when the page needs database persistence.'],
                ['name' => 'example.fpf', 'role' => 'Renders the page data using the built-in template language.'],
            ],
            'command' => "cp -n .env.example .env\nphp -S 127.0.0.1:8000 -t public",
            'route' => "'example' => [\n    'aliases' => ['example'],\n    'methods' => ['GET'],\n    'handlers' => ['GET' => 'show'],\n],",
            'settings' => [
                ['keys' => 'APP_NAME, APP_VERSION', 'purpose' => 'Site name and application version.'],
                ['keys' => 'APP_MODE', 'purpose' => 'both enables web pages and the API. web enables pages only. api enables the JSON endpoints only.'],
                ['keys' => 'APP_URL, APP_BASE_PATH', 'purpose' => 'Use the site origin for APP_URL and a separate mount path for APP_BASE_PATH, such as /fusion. Leave the mount path empty at the domain root.'],
                ['keys' => 'FUSIONPHP_DEBUG', 'purpose' => 'Enable local diagnostics. Defaults to false.'],
                ['keys' => 'API_PREFIX, API_SESSION', 'purpose' => 'Set the JSON route prefix and enable session cookies for API requests if needed. Defaults are /api and false.'],
                ['keys' => 'API_KEY, API_BASE_URL', 'purpose' => 'Optional configuration for application code.'],
                ['keys' => 'DB_DRIVER, DB_PATH', 'purpose' => 'Select sqlite, mysql, or pgsql. SQLite paths can be absolute, project-relative, or :memory:.'],
                ['keys' => 'DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD, DB_CHARSET', 'purpose' => 'Configure the database connection. Leave the port empty to use the driver default.'],
                ['keys' => 'SESSION_NAME, SESSION_LIFETIME', 'purpose' => 'Set the cookie name and lifetime in seconds. Zero means until the browser closes. Use an HTTPS APP_URL in production to set secure session cookies.'],
                ['keys' => 'MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD, MAIL_FROM', 'purpose' => 'Settings for an application mail integration.'],
            ],
        ];
    }
}
