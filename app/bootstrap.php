<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

// Autoloader PSR-4 minimal (App\ => app/)
spl_autoload_register(static function (string $class): void {
    if (strncmp($class, 'App\\', 4) !== 0) {
        return;
    }
    $file = APP_ROOT . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require APP_ROOT . '/app/helpers.php';

$config = require APP_ROOT . '/config/config.php';
App\Core\App::boot($config);
