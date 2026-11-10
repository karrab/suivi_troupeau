<?php
/**
 * Préchargement OPcache (opcache.preload) : compile une fois au démarrage
 * de PHP-FPM toutes les classes de l'application.
 */
$root = dirname(__DIR__);
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app', FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) {
    $path = $file->getPathname();
    // Les vues sont incluses à la demande (et restent mises en cache par OPcache)
    if (substr($path, -4) === '.php' && strpos($path, '/Views/') === false && strpos($path, '/Config/') === false) {
        opcache_compile_file($path);
    }
}
