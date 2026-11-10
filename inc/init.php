<?php
/**
 * Initialisation commune : à inclure en tête de chaque page.
 *   require __DIR__ . '/inc/init.php';
 */
declare(strict_types=1);

define('RACINE', dirname(__DIR__));
define('INC', __DIR__);

$GLOBALS['CONFIG'] = require INC . '/config.php';

date_default_timezone_set(config('appli.fuseau', 'UTC'));
mb_internal_encoding('UTF-8');
error_reporting(E_ALL);
ini_set('display_errors', config('appli.debogage') ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', RACINE . '/stockage/journaux/erreurs-php.log');

require INC . '/bd.php';
require INC . '/cache.php';
require INC . '/fonctions.php';
require INC . '/html.php';
require INC . '/validation.php';
require INC . '/auth.php';
require INC . '/traces.php';
require INC . '/pagination.php';
require INC . '/referentiels.php';
require INC . '/suivis.php';

/** Lecture de la configuration : config('bd.hote'). */
function config(string $chemin, mixed $defaut = null): mixed
{
    $v = $GLOBALS['CONFIG'];
    foreach (explode('.', $chemin) as $k) {
        if (!is_array($v) || !array_key_exists($k, $v)) {
            return $defaut;
        }
        $v = $v[$k];
    }
    return $v;
}

set_exception_handler(static function (Throwable $e): void {
    error_log((string) $e);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, (string) $e . PHP_EOL);
        exit(1);
    }
    http_response_code(500);
    echo config('appli.debogage')
        ? '<pre>' . htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') . '</pre>'
        : '<h1>Erreur interne</h1><p>L\'incident a été enregistré dans stockage/journaux.</p><p><a href="index.php">Retour</a></p>';
});

if (PHP_SAPI !== 'cli') {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    // Toutes les ressources sont locales : aucun script inline ni externe autorisé.
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; font-src 'self'; img-src 'self' data:; frame-ancestors 'self'; form-action 'self'");
    session_demarrer();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verifier_csrf();
    }
}
