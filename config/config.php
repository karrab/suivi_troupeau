<?php
/**
 * Configuration principale.
 * Ne modifiez pas ce fichier : créez config/config.local.php qui retourne
 * un tableau surchargeant ces valeurs (il est ignoré par git).
 * Les variables d'environnement (DB_HOST, DB_NAME, ...) sont aussi prises en compte.
 */
$env = static fn(string $k, $default = null) => ($v = getenv($k)) !== false ? $v : $default;

$config = [
    'app' => [
        'name'      => 'Suivi Troupeau',
        'env'       => $env('APP_ENV', 'production'),   // production | development
        'debug'     => (bool) $env('APP_DEBUG', false),
        'timezone'  => 'Africa/Casablanca',
        'base_url'  => $env('APP_BASE_URL', ''),          // ex : /suivi_troupeau/public
        'session_name' => 'TROUPEAUSESSID',
        'session_lifetime' => 7200,
    ],
    'db' => [
        'host'     => $env('DB_HOST', '127.0.0.1'),
        'port'     => (int) $env('DB_PORT', 3306),
        'name'     => $env('DB_NAME', 'suivi_troupeau'),
        'user'     => $env('DB_USER', 'troupeau'),
        'pass'     => $env('DB_PASS', 'troupeau'),
        'charset'  => 'utf8mb4',
        'persistent' => false,
    ],
    'cache' => [
        'prefix'      => $env('CACHE_PREFIX', 'troupeau:'),
        'default_ttl' => 300,
    ],
    'security' => [
        'max_login_attempts' => 5,
        'lockout_minutes'    => 15,
    ],
    'pagination' => [
        'per_page'        => 25,
        'max_per_page'    => 200,
        'max_export_rows' => 5000,
    ],
];

$local = __DIR__ . '/config.local.php';
if (is_file($local)) {
    $config = array_replace_recursive($config, require $local);
}
return $config;
