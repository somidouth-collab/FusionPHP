<?php

use Fusion\Core\Autoloader;
use Fusion\Core\Database;
use Fusion\Core\Migrations;

function runMigrationCommand(string $root, string $command, array $arguments): void
{
    if ($command === 'migrate:make' && count($arguments) === 1) {
        makeMigration($root, $arguments[0]);
        return;
    }
    if ($arguments !== [] || !in_array($command, ['migrate', 'migrate:status', 'migrate:rollback'], true)) {
        throw new RuntimeException(
            "Usage:\n  php fusion migrate:make NAME\n  php fusion migrate:status\n  php fusion migrate\n  php fusion migrate:rollback"
        );
    }

    define('BASE_PATH', $root);
    require BASE_PATH . '/app/config.php';
    require BASE_PATH . '/fusionphp/core/Autoloader.php';
    Autoloader::register(true);
    $driver = config('database.driver');
    if (!in_array($driver, ['sqlite', 'pgsql'], true)) {
        throw new RuntimeException('The migration runner supports SQLite and PostgreSQL.');
    }
    if (!in_array($driver, PDO::getAvailableDrivers(), true)) {
        throw new RuntimeException("Enable the PDO {$driver} driver in CLI PHP to use migrations.");
    }
    $migrations = new Migrations(Database::getInstance(), $root);

    switch ($command) {
        case 'migrate':
            $applied = $migrations->apply();
            echo $applied === [] ? "No pending migrations.\n" : 'Applied ' . implode("\nApplied ", $applied) . "\n";
            break;
        case 'migrate:status':
            $states = $migrations->status();
            if ($states === []) {
                echo "No migration files found.\n";
            }
            foreach ($states as $id => $state) {
                printf("%-8s %s\n", $state, $id);
            }
            break;
        case 'migrate:rollback':
            $id = $migrations->rollback();
            echo $id === null ? "No applied migrations.\n" : "Rolled back {$id}.\n";
            break;
    }
}

function makeMigration(string $root, string $name): void
{
    if (!preg_match('/\A[a-z][a-z0-9_]{0,79}\z/', $name)) {
        throw new RuntimeException('Use a lowercase migration name such as create_articles.');
    }

    $template = <<<'MIGRATION'
<?php

use Fusion\Core\Database;

return [
    'up' => static function (Database $db): void {
        // Apply the schema change.
    },
    'down' => static function (Database $db): void {
        // Reverse the schema change.
    },
];
MIGRATION;
    $directory = $root . '/migrations';
    if (!is_dir($directory) && !@mkdir($directory, 0755) && !is_dir($directory)) {
        throw new RuntimeException('Could not create migrations/.');
    }
    $filename = gmdate('YmdHis') . '_' . $name . '.php';
    $path = $directory . '/' . $filename;
    $handle = @fopen($path, 'x');
    if ($handle === false) {
        throw new RuntimeException("Could not create migrations/{$filename}. Check permissions or retry with a different name.");
    }
    $contents = $template . PHP_EOL;
    $written = fwrite($handle, $contents);
    fclose($handle);
    if ($written !== strlen($contents)) {
        unlink($path);
        throw new RuntimeException('Could not write the migration file.');
    }
    echo "Created migrations/{$filename}.\n";
}
