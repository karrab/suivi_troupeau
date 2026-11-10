<?php
/**
 * Routeur pour le serveur de développement intégré de PHP (qui ignore .htaccess) :
 *   php -S 127.0.0.1:8080 outils/routeur.php
 * Bloque l'accès direct aux dossiers internes, sert le reste normalement.
 */
$chemin = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (preg_match('#^/(inc|sql|outils|config|stockage)(/|$)|/\.#', $chemin)) {
    http_response_code(403);
    exit('Accès interdit.');
}
return false;
