<?php
defined('INC') || exit; // fichier inclus uniquement
/**
 * Configuration principale.
 * Pour l'adapter sans modifier ce fichier, créez inc/config.local.php
 * (modèle : inc/config.local.php.exemple), qui n'est pas versionné.
 * Les variables d'environnement BD_HOTE, BD_NOM, BD_UTILISATEUR et BD_MOT_DE_PASSE sont aussi prises en compte.
 */
$env = static fn(string $k, $defaut = null) => ($v = getenv($k)) !== false ? $v : $defaut;

$CONFIG = [
    'appli' => [
        'nom'          => 'Suivi Troupeau',
        'debogage'     => (bool) $env('APP_DEBUG', false),
        'fuseau'       => 'Africa/Casablanca',
        'nom_session'  => 'TROUPEAU',
        'duree_session'=> 7200,
    ],
    'bd' => [
        'hote'          => $env('BD_HOTE', '127.0.0.1'),
        'port'          => (int) $env('BD_PORT', 3306),
        'nom'           => $env('BD_NOM', 'suivi_troupeau'),
        'utilisateur'   => $env('BD_UTILISATEUR', 'troupeau'),
        'mot_de_passe'  => $env('BD_MOT_DE_PASSE', 'troupeau'),
    ],
    'cache' => [
        'prefixe' => $env('CACHE_PREFIXE', 'troupeau:'),
        'duree'   => 300,
    ],
    'securite' => [
        'tentatives_max'   => 5,
        'blocage_minutes'  => 15,
    ],
    'pagination' => [
        'par_page'        => 25,
        'max_par_page'    => 200,
        'max_lignes_pdf'  => 5000,
    ],
];

if (is_file(__DIR__ . '/config.local.php')) {
    $CONFIG = array_replace_recursive($CONFIG, require __DIR__ . '/config.local.php');
}
return $CONFIG;
