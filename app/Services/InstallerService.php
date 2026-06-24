<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use Throwable;

/**
 * Performs first-run installation without external dependencies.
 */
final class InstallerService
{
    /**
     * Store the application root path.
     */
    public function __construct(private string $rootPath)
    {
    }

    /**
     * Return true when installation is locked.
     */
    public function isInstalled(): bool
    {
        return is_file($this->rootPath . '/config.php') && is_file($this->rootPath . '/storage/install.lock');
    }

    /**
     * Install database schema, config file, folders, and first admin user.
     *
     * @param array<string, string> $input
     * @return array{ok:bool,errors:array<int,string>}
     */
    public function install(array $input): array
    {
        $errors = $this->validate($input);
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        try {
            $pdo = $this->connect($input);
            $this->createStorageFolders();
            $this->runSchema($pdo);
            $this->createAdmin($pdo, $input);
            $this->writeConfig($input);
            file_put_contents($this->rootPath . '/storage/install.lock', gmdate('c') . PHP_EOL, LOCK_EX);
        } catch (Throwable $throwable) {
            return ['ok' => false, 'errors' => [$throwable->getMessage()]];
        }

        return ['ok' => true, 'errors' => []];
    }

    /**
     * Validate installation input.
     *
     * @param array<string, string> $input
     * @return array<int, string>
     */
    private function validate(array $input): array
    {
        $errors = [];
        foreach (['db_host', 'db_name', 'db_user', 'admin_email', 'admin_password'] as $field) {
            if (trim((string) ($input[$field] ?? '')) === '') {
                $errors[] = $field . ' is required.';
            }
        }
        if (!filter_var((string) ($input['admin_email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Admin email is invalid.';
        }
        if (strlen((string) ($input['admin_password'] ?? '')) < 10) {
            $errors[] = 'Admin password must have at least 10 characters.';
        }
        return $errors;
    }

    /**
     * Create a PDO connection from installer input.
     *
     * @param array<string, string> $input
     */
    private function connect(array $input): PDO
    {
        $host = trim($input['db_host']);
        $port = (int) ($input['db_port'] ?? 3306);
        $name = trim($input['db_name']);
        $charset = 'utf8mb4';
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $name, $charset);

        return new PDO($dsn, trim($input['db_user']), (string) ($input['db_password'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    /**
     * Create writable storage folders.
     */
    private function createStorageFolders(): void
    {
        foreach (['storage', 'storage/campaigns', 'storage/logs', 'storage/tmp'] as $dir) {
            $path = $this->rootPath . '/' . $dir;
            if (!is_dir($path)) {
                mkdir($path, 0775, true);
            }
        }
    }

    /**
     * Run the database schema file.
     */
    private function runSchema(PDO $pdo): void
    {
        $schema = file_get_contents($this->rootPath . '/database/schema.sql');
        if (!is_string($schema)) {
            throw new \RuntimeException('Database schema file is missing.');
        }
        $pdo->exec($schema);
    }

    /**
     * Create the first administrator.
     *
     * @param array<string, string> $input
     */
    private function createAdmin(PDO $pdo, array $input): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO users (email, password_hash, display_name, role, locale, is_active, created_at, updated_at)
             VALUES (:email, :password_hash, :display_name, :role, :locale, 1, :created_at, :updated_at)'
        );
        $now = gmdate('Y-m-d H:i:s');
        $stmt->execute([
            'email' => mb_strtolower(trim($input['admin_email'])),
            'password_hash' => password_hash($input['admin_password'], PASSWORD_DEFAULT),
            'display_name' => trim($input['admin_email']),
            'role' => 'admin',
            'locale' => $input['default_locale'] === 'cs' ? 'cs' : 'en',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $settings = $pdo->prepare('INSERT INTO settings (setting_key, setting_value, updated_at) VALUES (:setting_key, :setting_value, :updated_at)');
        $settings->execute(['setting_key' => 'app_name', 'setting_value' => 'DnD ChatGPT Campaign', 'updated_at' => $now]);
    }

    /**
     * Write config.php from installer input.
     *
     * @param array<string, string> $input
     */
    private function writeConfig(array $input): void
    {
        $config = [
            'app' => [
                'name' => 'DnD ChatGPT Campaign',
                'base_url' => '',
                'default_locale' => $input['default_locale'] === 'cs' ? 'cs' : 'en',
                'installed' => true,
            ],
            'db' => [
                'driver' => 'mysql',
                'host' => trim($input['db_host']),
                'port' => (int) ($input['db_port'] ?? 3306),
                'database' => trim($input['db_name']),
                'username' => trim($input['db_user']),
                'password' => (string) ($input['db_password'] ?? ''),
                'charset' => 'utf8mb4',
            ],
            'security' => [
                'secret_key' => bin2hex(random_bytes(32)),
                'session_name' => 'dnd_chatgpt_campaign',
            ],
        ];

        $export = var_export($config, true);
        $content = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . $export . ";\n";
        file_put_contents($this->rootPath . '/config.php', $content, LOCK_EX);
    }
}
