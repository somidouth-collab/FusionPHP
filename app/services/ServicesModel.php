<?php

namespace App\Services;

final class ServicesModel
{
    public function page(): array
    {
        return [
            'preferencesController' => <<<'EXAMPLE'
<?php

namespace App\Preferences;

use Fusion\Core\Controller;
use Fusion\Core\Csrf;
use Fusion\Core\Flash;
use Fusion\Core\Session;

final class PreferencesController extends Controller
{
    protected function views(): array
    {
        return [
            'preferences' => [
                'aliases' => ['preferences'],
                'methods' => ['GET', 'POST'],
                'handlers' => ['GET' => 'show', 'POST' => 'save'],
            ],
        ];
    }

    public function show(): void
    {
        $name = $this->old('name', Session::get('display_name', ''));
        $this->view('preferences', [
            'formUrl' => $this->request->url('/preferences'),
            'csrf' => Csrf::token(),
            'name' => is_string($name) ? $name : '',
            'error' => $this->error('name'),
            'notice' => Flash::get('success'),
        ]);
        $this->clearValidation();
    }

    public function save(): void
    {
        Csrf::protect($this->request);

        $data = $this->validate(['name' => 'required|string|max:80']);
        Session::set('display_name', $data['name']);
        $this->redirectWith($this->request->url('/preferences'), 'Preferences saved.');
    }
}
EXAMPLE,
            'preferencesTemplate' => <<<'EXAMPLE'
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Preferences</title>
</head>
<body>
    <h1>Preferences</h1>
    {% if notice %}<p role="status">{{ notice }}</p>{% endif %}
    {% if error %}<p role="alert">{{ error }}</p>{% endif %}
    <form method="post" action="{{ url formUrl }}">
        <input type="hidden" name="_csrf" value="{{ csrf }}">
        <label for="display-name">Display name</label>
        <input id="display-name" name="name" value="{{ name }}" required maxlength="80">
        <button type="submit">Save</button>
    </form>
</body>
</html>
EXAMPLE,
            'accountMiddleware' => <<<'EXAMPLE'
<?php

namespace App\Account;

use Fusion\Core\Middleware;

final class AccountMiddleware extends Middleware
{
    protected function protected(): array
    {
        return ['/account/*'];
    }

    public function before(): bool
    {
        return $this->handleRouteAccess();
    }
}
EXAMPLE,
            'signIn' => <<<'EXAMPLE'
public function signIn(): void
{
    \Fusion\Core\Csrf::protect($this->request);

    $email = $this->request->post('email');
    $password = $this->request->post('password');
    if (!is_string($email) || !is_string($password)) {
        $this->abort(401, 'Invalid credentials.');
    }

    $account = \Fusion\Core\Database::getInstance()->query(
        'SELECT id, name, password_hash FROM users WHERE email = ?',
        [trim($email)]
    )->fetch(\PDO::FETCH_ASSOC);

    if (is_array($account)) {
        $account['permissions'] = ['account.view'];
    }
    if (!\Fusion\Core\Auth::attempt($password, $account ?: null)) {
        $this->abort(401, 'Invalid credentials.');
    }

    $this->redirect($this->request->url('/account'), 303);
}
EXAMPLE,
            'permissionExample' => <<<'EXAMPLE'
public function account(): void
{
    \Fusion\Core\Auth::requirePermission('account.view');
    $this->view('account', ['user' => \Fusion\Core\Session::user()]);
}

public function signOut(): void
{
    \Fusion\Core\Csrf::protect($this->request);
    \Fusion\Core\Session::logout();
    $this->redirect($this->request->url('/auth/login'), 303);
}
EXAMPLE,
            'articleMigration' => <<<'EXAMPLE'
<?php

use Fusion\Core\Database;

return [
    'up' => static function (Database $db): void {
        $db->query('CREATE TABLE articles (
            id INTEGER PRIMARY KEY,
            title TEXT NOT NULL,
            status TEXT NOT NULL
        )');
    },
    'down' => static function (Database $db): void {
        $db->query('DROP TABLE articles');
    },
];
EXAMPLE,
            'schemaExample' => <<<'EXAMPLE'
CREATE TABLE articles (
    id INTEGER PRIMARY KEY,
    title TEXT NOT NULL,
    status TEXT NOT NULL
);
EXAMPLE,
            'articleFile' => <<<'EXAMPLE'
<?php

namespace App\Articles;

use Fusion\Core\Model;

final class Article extends Model
{
    protected static string $table = 'articles';
    protected static array $fillable = ['title', 'status'];
}
EXAMPLE,
            'articleUsage' => <<<'EXAMPLE'
<?php

use App\Articles\Article;
use Fusion\Core\Autoloader;
use Fusion\Core\Database;

define('BASE_PATH', __DIR__);
require BASE_PATH . '/app/config.php';
require BASE_PATH . '/fusionphp/core/Autoloader.php';
Autoloader::register(true);

$db = Database::getInstance();
$article = $db->transaction(function (): Article {
    $article = Article::create(['title' => 'Hello', 'status' => 'draft']);
    $article->status = 'published';
    $article->save();
    return $article;
});

$row = $db->query(
    'SELECT title FROM articles WHERE id = ? AND status = ?',
    [$article->id, 'published']
)->fetch();

echo 'Published: ' . $row['title'] . PHP_EOL;
EXAMPLE,
            'title' => 'Core services',
            'middleware' => <<<'EXAMPLE'
protected function protected(): array
{
    return ['/account', '/account/*'];
}

public function before(): bool
{
    return $this->handleRouteAccess();
}
EXAMPLE,
            'session' => <<<'EXAMPLE'
Session::set('timezone', 'UTC');
$timezone = Session::get('timezone', 'UTC');
Flash::success('Profile saved');
EXAMPLE,
            'model' => <<<'EXAMPLE'
final class Article extends \Fusion\Core\Model
{
    protected static string $table = 'articles';
    protected static array $fillable = ['title', 'body'];
}

$article = Article::find(1);
$published = Article::where('status', 'published');
EXAMPLE,
            'query' => <<<'EXAMPLE'
$db = \Fusion\Core\Database::getInstance();
$rows = $db->query(
    'SELECT title FROM articles WHERE status = ?',
    ['published']
)->fetchAll();
EXAMPLE,
        ];
    }
}
