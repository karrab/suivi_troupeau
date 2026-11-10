<?php
defined('INC') || exit; // fichier inclus uniquement
/**
 * Fonctions utilitaires : URL, redirections, messages, formulaires, formats.
 */

function e(mixed $v): string
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** URL relative vers une page : url('animal.php', ['id' => 5]). */
function url(string $page, array $params = []): string
{
    $params = array_filter($params, static fn($v) => $v !== null && $v !== '');
    return $page . ($params ? '?' . http_build_query($params) : '');
}

function asset(string $chemin): string
{
    $f = RACINE . '/assets/' . $chemin;
    return 'assets/' . $chemin . '?v=' . (is_file($f) ? filemtime($f) : 0);
}

function page_courante(): string
{
    return basename((string) parse_url($_SERVER['SCRIPT_NAME'] ?? 'index.php', PHP_URL_PATH));
}

/** URL de la page courante avec des paramètres GET modifiés (null = retirer). */
function url_avec(array $modifs, array $retirer = []): string
{
    $q = $_GET;
    foreach ($retirer as $r) {
        unset($q[$r]);
    }
    foreach ($modifs as $k => $v) {
        if ($v === null) {
            unset($q[$k]);
        } else {
            $q[$k] = $v;
        }
    }
    return url(page_courante(), $q);
}

function rediriger(string $vers): never
{
    header('Location: ' . $vers, true, 302);
    exit;
}

function abandonner(int $code, string $message = ''): never
{
    http_response_code($code);
    if (PHP_SAPI === 'cli') {
        throw new RuntimeException("HTTP $code : $message");
    }
    $titre = 'Erreur ' . $code;
    if (est_connecte()) {
        require INC . '/entete.php';
    } else {
        require INC . '/entete_simple.php';
    }
    echo '<div class="text-center py-5"><div class="display-3 fw-bold text-foret">' . $code . '</div>'
        . '<p class="lead">' . e($message ?: 'Une erreur est survenue.') . '</p>'
        . '<a href="index.php" class="btn btn-foret"><i class="bi bi-house me-1"></i>Retour à l\'accueil</a></div>';
    require INC . (est_connecte() ? '/pied.php' : '/pied_simple.php');
    exit;
}

function json_reponse(mixed $donnees, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($donnees, JSON_UNESCAPED_UNICODE);
    exit;
}

function est_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function exiger_post(): void
{
    if (!est_post()) {
        abandonner(405, 'Méthode non autorisée.');
    }
}

function entier_get(string $cle, int $defaut = 0): int
{
    return isset($_GET[$cle]) && is_numeric($_GET[$cle]) ? (int) $_GET[$cle] : $defaut;
}

// ------------------------------------------------------------- CSRF
function jeton_csrf(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function champ_csrf(): string
{
    return '<input type="hidden" name="_csrf" value="' . jeton_csrf() . '">';
}

function verifier_csrf(): void
{
    $envoye = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($envoye) || !hash_equals(jeton_csrf(), $envoye)) {
        abandonner(419, 'Jeton de sécurité expiré ou invalide. Rechargez la page et réessayez.');
    }
}

// ------------------------------------------------- messages & formulaires
function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function flashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

/** Mémorise la saisie et les erreurs avant de rediriger vers le formulaire. */
function garder_saisie(array $saisie, array $erreurs = []): void
{
    unset($saisie['_csrf']);
    $_SESSION['_saisie'] = $saisie;
    $_SESSION['_erreurs'] = $erreurs;
}

/** Récupère (et efface) les erreurs du formulaire précédent. */
function erreurs_form(): array
{
    $e = $_SESSION['_erreurs'] ?? [];
    $GLOBALS['_SAISIE'] = $_SESSION['_saisie'] ?? null;
    unset($_SESSION['_erreurs'], $_SESSION['_saisie']);
    return $e;
}

function ancien(string $cle, mixed $defaut = ''): mixed
{
    return $GLOBALS['_SAISIE'][$cle] ?? $defaut;
}

/** Valeur d'un champ : saisie précédente sinon valeur de l'enregistrement. */
function fv(array $obj, string $champ, mixed $defaut = ''): string
{
    $s = $GLOBALS['_SAISIE'] ?? null;
    if (is_array($s) && array_key_exists($champ, $s)) {
        return is_array($s[$champ]) ? '' : (string) $s[$champ];
    }
    return (string) ($obj[$champ] ?? $defaut);
}

function parametre(string $cle, mixed $defaut = null): mixed
{
    $tous = cache_memoriser(cache_cle(['parametres'], 'tous'), 3600, static function () {
        $out = [];
        foreach (bd_tous('SELECT cle, valeur, type FROM parametres') as $p) {
            $out[$p['cle']] = match ($p['type']) {
                'entier' => (int) $p['valeur'],
                'nombre' => (float) $p['valeur'],
                default  => $p['valeur'],
            };
        }
        return $out;
    });
    return $tous[$cle] ?? $defaut;
}

// ------------------------------------------------------------- formats
function fmt_date(?string $d, bool $heure = false): string
{
    if (!$d) {
        return '';
    }
    $t = strtotime($d);
    return $t ? date($heure ? 'd/m/Y H:i' : 'd/m/Y', $t) : $d;
}

function fmt_nombre(mixed $n, int $dec = 2): string
{
    return ($n === null || $n === '') ? '' : number_format((float) $n, $dec, ',', ' ');
}

function fmt_argent(mixed $n): string
{
    return ($n === null || $n === '') ? '' : fmt_nombre($n) . ' ' . parametre('devise', 'DH');
}

function fmt_age(?string $naissance, ?string $jusqua = null): string
{
    if (!$naissance) {
        return '';
    }
    $a = new DateTime($naissance);
    $b = new DateTime($jusqua ?: 'today');
    if ($b < $a) {
        return '';
    }
    $d = $a->diff($b);
    if ($d->y > 0) {
        return $d->y . ' an' . ($d->y > 1 ? 's' : '') . ($d->m ? ' ' . $d->m . ' mois' : '');
    }
    return $d->m > 0 ? $d->m . ' mois' : $d->days . ' j';
}

// ------------------------------------------------------------ libellés
function libelles(string $groupe): array
{
    static $l = null;
    $l ??= require INC . '/libelles.php';
    return $l[$groupe] ?? [];
}

function libelle(string $groupe, ?string $cle): string
{
    return libelles($groupe)[$cle ?? ''] ?? (string) $cle;
}

/** Résumé des filtres actifs (en-tête des exports PDF). */
function resume_filtres(array $libelles): string
{
    $p = [];
    foreach ($libelles as $k => $l) {
        if (isset($_GET[$k]) && $_GET[$k] !== '' && !is_array($_GET[$k])) {
            $p[] = $l . ' : ' . $_GET[$k];
        }
    }
    return $p ? 'Filtres — ' . implode(' | ', $p) : 'Aucun filtre';
}

function par_page(): int
{
    $defaut = (int) parametre('lignes_par_page', 25);
    $pp = entier_get('par_page', $defaut);
    return in_array($pp, [10, 25, 50, 100, 200], true) ? $pp : $defaut;
}
