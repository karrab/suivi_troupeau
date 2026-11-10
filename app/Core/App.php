<?php
declare(strict_types=1);

namespace App\Core;

final class App
{
    private static array $config = [];

    public static function boot(array $config): void
    {
        self::$config = $config;
        date_default_timezone_set($config['app']['timezone'] ?? 'UTC');
        mb_internal_encoding('UTF-8');

        $debug = (bool) ($config['app']['debug'] ?? false);
        error_reporting(E_ALL);
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');
        ini_set('error_log', APP_ROOT . '/storage/logs/php-error.log');

        set_exception_handler(static function (\Throwable $e) use ($debug): void {
            error_log((string) $e);
            if (PHP_SAPI === 'cli') {
                fwrite(STDERR, (string) $e . PHP_EOL);
                exit(1);
            }
            http_response_code(500);
            if ($debug) {
                echo '<pre>' . htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') . '</pre>';
            } else {
                echo View::render('errors/500', [], 'layouts/blank');
            }
        });
    }

    public static function config(string $path, mixed $default = null): mixed
    {
        $value = self::$config;
        foreach (explode('.', $path) as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return $default;
            }
            $value = $value[$key];
        }
        return $value;
    }
}
