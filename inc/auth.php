<?php
defined('INC') || exit; // fichier inclus uniquement
/**
 * Authentification (session) et contrôle des permissions par rôle.
 * Les permissions d'un rôle sont mises en cache APCu (étiquette « permissions »).
 */

function session_demarrer(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name(config('appli.nom_session', 'TROUPEAU'));
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();
    $duree = (int) config('appli.duree_session', 7200);
    if (isset($_SESSION['derniere_activite']) && time() - $_SESSION['derniere_activite'] > $duree) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['derniere_activite'] = time();
}

/** @return true|string true si la connexion réussit, sinon le message d'erreur. */
function connecter(string $identifiant, string $motDePasse): bool|string
{
    $u = bd_un('SELECT * FROM utilisateurs WHERE identifiant = :i', ['i' => $identifiant]);
    $generique = 'Identifiant ou mot de passe incorrect.';
    if (!$u) {
        password_verify($motDePasse, '$2y$10$abcdefghijklmnopqrstuuJ8dZ1q0w8m3H2l7m5Qx6W9sYb1c2d3e'); // temps constant
        tracer('echec_connexion', 'utilisateur', null, 'Identifiant inconnu : ' . $identifiant, null, null, $identifiant);
        return $generique;
    }
    if ($u['bloque_jusqua'] && strtotime($u['bloque_jusqua']) > time()) {
        return 'Compte temporairement bloqué après plusieurs tentatives. Réessayez plus tard.';
    }
    if (!(int) $u['actif']) {
        return 'Ce compte est désactivé.';
    }
    if (!password_verify($motDePasse, $u['mot_de_passe'])) {
        $n = (int) $u['tentatives_echouees'] + 1;
        $bloque = $n >= (int) config('securite.tentatives_max', 5)
            ? date('Y-m-d H:i:s', time() + 60 * (int) config('securite.blocage_minutes', 15)) : null;
        bd_requete('UPDATE utilisateurs SET tentatives_echouees = :n, bloque_jusqua = :b WHERE id = :id',
            ['n' => $bloque ? 0 : $n, 'b' => $bloque, 'id' => (int) $u['id']]);
        tracer('echec_connexion', 'utilisateur', (int) $u['id'], 'Mot de passe incorrect', null, null, $identifiant);
        return $generique;
    }
    if (password_needs_rehash($u['mot_de_passe'], PASSWORD_DEFAULT)) {
        bd_requete('UPDATE utilisateurs SET mot_de_passe = :h WHERE id = :id', ['h' => password_hash($motDePasse, PASSWORD_DEFAULT), 'id' => (int) $u['id']]);
    }
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $u['id'];
    unset($GLOBALS['_UTILISATEUR']);
    bd_requete('UPDATE utilisateurs SET derniere_connexion = NOW(), tentatives_echouees = 0, bloque_jusqua = NULL WHERE id = :id', ['id' => (int) $u['id']]);
    tracer('connexion', 'utilisateur', (int) $u['id'], 'Connexion');
    return true;
}

function deconnecter(): void
{
    if (est_connecte()) {
        tracer('deconnexion', 'utilisateur', utilisateur_id(), 'Déconnexion');
    }
    $_SESSION = [];
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    session_destroy();
    $GLOBALS['_UTILISATEUR'] = null;
}

function utilisateur(): ?array
{
    if (!array_key_exists('_UTILISATEUR', $GLOBALS)) {
        $GLOBALS['_UTILISATEUR'] = null;
        if (!empty($_SESSION['uid'])) {
            $u = bd_un('SELECT u.id, u.identifiant, u.nom_complet, u.email, u.role_id, u.actif, r.nom AS role, r.acces_complet
                        FROM utilisateurs u JOIN roles r ON r.id = u.role_id WHERE u.id = :id', ['id' => (int) $_SESSION['uid']]);
            if ($u && (int) $u['actif']) {
                $GLOBALS['_UTILISATEUR'] = $u;
            } else {
                unset($_SESSION['uid']);
            }
        }
    }
    return $GLOBALS['_UTILISATEUR'];
}

function est_connecte(): bool
{
    return session_status() === PHP_SESSION_ACTIVE && utilisateur() !== null;
}

function utilisateur_id(): ?int
{
    return (PHP_SAPI !== 'cli' && est_connecte()) ? (int) utilisateur()['id'] : null;
}

function permissions_role(int $roleId): array
{
    return cache_memoriser(cache_cle(['permissions'], 'role:' . $roleId), 3600, static fn() => array_fill_keys(bd_colonne(
        'SELECT p.code FROM roles_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = :r', ['r' => $roleId]
    ), true));
}

/** L'utilisateur connecté a-t-il la permission (ex. « animaux.modifier ») ? */
function peut(string $permission): bool
{
    $u = utilisateur();
    if (!$u) {
        return false;
    }
    return (int) $u['acces_complet'] === 1 || isset(permissions_role((int) $u['role_id'])[$permission]);
}

function exiger_connexion(): void
{
    if (!est_connecte()) {
        $_SESSION['page_demandee'] = basename((string) ($_SERVER['REQUEST_URI'] ?? ''));
        rediriger('connexion.php');
    }
}

/** À appeler en haut de chaque page protégée. */
function exiger_permission(string $permission): void
{
    exiger_connexion();
    if (!peut($permission)) {
        tracer('acces_refuse', 'permission', null, 'Accès refusé : ' . $permission);
        abandonner(403, "Vous n'avez pas la permission « $permission ».");
    }
}
