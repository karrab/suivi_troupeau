<?php
/**
 * Contrôleur frontal : toutes les requêtes passent par ici (index.php?r=module/action).
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
// Toutes les ressources (Bootstrap, icônes, Chart.js) sont servies localement : CSP stricte sans script inline.
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; font-src 'self'; img-src 'self' data:; frame-ancestors 'self'; form-action 'self'");

Auth::startSession();

$routes = require APP_ROOT . '/app/routes.php';
$route = (string) ($_GET['r'] ?? 'dashboard');
if (!isset($routes[$route])) {
    Auth::check() ? abort(404, 'Page introuvable.') : redirect(url('login'));
}

[$class, $method] = $routes[$route];
$opts = $routes[$route][2] ?? [];

if (!empty($opts['post']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    abort(405, 'Méthode non autorisée.');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
}
if (empty($opts['public'])) {
    Auth::requireLogin();
}

(new $class())->$method();
