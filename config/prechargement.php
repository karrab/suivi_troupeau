<?php
/**
 * Préchargement OPcache (opcache.preload, PHP-FPM) : compile au démarrage
 * les bibliothèques de inc/ (les pages restent mises en cache par OPcache).
 */
foreach (glob(dirname(__DIR__) . '/inc/*.php') as $f) {
    if (!in_array(basename($f), ['config.php', 'config.local.php', 'init.php', 'entete.php', 'pied.php', 'entete_simple.php', 'pied_simple.php', 'libelles.php'], true)) {
        opcache_compile_file($f);
    }
}
