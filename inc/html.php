<?php
defined('INC') || exit; // fichier inclus uniquement
/**
 * Petits composants HTML réutilisés par les pages.
 */

function invalide(array $erreurs, string $champ): string
{
    return isset($erreurs[$champ]) ? ' is-invalid' : '';
}

function erreur_champ(array $erreurs, string $champ): string
{
    return isset($erreurs[$champ]) ? '<div class="invalid-feedback d-block">' . e($erreurs[$champ]) . '</div>' : '';
}

function selectionne(mixed $a, mixed $b): string
{
    return (string) $a === (string) $b ? ' selected' : '';
}

function coche(mixed $v): string
{
    return $v ? ' checked' : '';
}

/** <option> d'une liste de libellés. */
function options(array $liste, mixed $courant, ?string $vide = null): string
{
    $h = $vide !== null ? '<option value="">' . e($vide) . '</option>' : '';
    foreach ($liste as $k => $l) {
        $h .= '<option value="' . e($k) . '"' . selectionne($courant, $k) . '>' . e($l) . '</option>';
    }
    return $h;
}

function badge_niveau(?string $n): string
{
    $c = ['bon' => 'success', 'moyen' => 'warning', 'faible' => 'danger', 'nd' => 'secondary'];
    $t = ['bon' => 'Bon', 'moyen' => 'Moyen', 'faible' => 'Faible', 'nd' => 'N/D'];
    $n ??= 'nd';
    return '<span class="badge text-bg-' . ($c[$n] ?? 'secondary') . '">' . ($t[$n] ?? e($n)) . '</span>';
}

function badge_statut(string $s): string
{
    $c = ['actif' => 'success', 'vendu' => 'info', 'mort' => 'dark', 'reforme' => 'warning', 'perdu' => 'secondary'];
    return '<span class="badge text-bg-' . ($c[$s] ?? 'secondary') . '">' . e(libelle('statut', $s)) . '</span>';
}

/** En-tête de colonne triable. */
function lien_tri(string $cle, string $libelle, string $defaut = 'id'): string
{
    $actuel = $_GET['tri'] ?? $defaut;
    $sens = strtolower((string) ($_GET['sens'] ?? 'desc'));
    $nouveau = $actuel === $cle && $sens === 'asc' ? 'desc' : 'asc';
    $icone = $actuel === $cle ? ($sens === 'asc' ? ' <i class="bi bi-caret-up-fill"></i>' : ' <i class="bi bi-caret-down-fill"></i>') : '';
    return '<a class="text-reset text-decoration-none" href="' . e(url_avec(['tri' => $cle, 'sens' => $nouveau], ['curseur', 'sens_page'])) . '">' . e($libelle) . $icone . '</a>';
}

/**
 * Bouton « Supprimer » : formulaire POST + jeton CSRF + confirmation (fenêtre modale).
 */
function bouton_supprimer(string $action, int $id, string $message, array $champs = [], bool $texte = false): string
{
    $h = '<form method="post" action="' . e($action) . '" class="d-inline" data-confirmer="' . e($message) . '">' . champ_csrf()
        . '<input type="hidden" name="action" value="supprimer"><input type="hidden" name="id" value="' . $id . '">';
    foreach ($champs as $k => $v) {
        $h .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
    }
    return $h . '<button type="submit" class="btn btn-sm btn-outline-danger" title="Supprimer"><i class="bi bi-trash"></i>' . ($texte ? ' Supprimer' : '') . '</button></form>';
}

/** Boutons « Imprimer » et « Exporter PDF » d'une liste (le PDF reprend les filtres). */
function boutons_liste(string $permissionExport): string
{
    $h = '<div class="btn-group d-print-none"><button type="button" class="btn btn-outline-secondary btn-sm" data-imprimer><i class="bi bi-printer me-1"></i>Imprimer</button>';
    if (peut($permissionExport)) {
        $h .= '<a class="btn btn-outline-danger btn-sm" target="_blank" href="' . e(url_avec(['pdf' => 1], ['curseur', 'sens_page', 'par_page'])) . '"><i class="bi bi-file-earmark-pdf me-1"></i>Exporter PDF</a>';
    }
    return $h . '</div>';
}

/** Navigation keyset : Première / Précédent / Suivant + lignes par page. */
function barre_pagination(array $page, ?int $total = null): string
{
    $h = '<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-2 d-print-none"><div class="small text-muted">'
        . count($page['lignes']) . ' ligne(s) affichée(s)' . ($total !== null ? ' sur ' . fmt_nombre($total, 0) : '') . '</div>'
        . '<div class="d-flex align-items-center gap-2"><form method="get" action="' . e(page_courante()) . '">';
    foreach ($_GET as $k => $v) {
        if (!in_array($k, ['par_page', 'curseur', 'sens_page'], true) && !is_array($v)) {
            $h .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
        }
    }
    $h .= '<select name="par_page" class="form-select form-select-sm" data-auto aria-label="Lignes par page">';
    foreach ([10, 25, 50, 100, 200] as $n) {
        $h .= '<option value="' . $n . '"' . selectionne($n, $page['par_page']) . '>' . $n . ' / page</option>';
    }
    $h .= '</select></form><ul class="pagination pagination-sm mb-0">';
    $h .= '<li class="page-item' . ($page['premiere'] ? ' disabled' : '') . '"><a class="page-link" href="' . e(url_avec([], ['curseur', 'sens_page'])) . '" title="Première page"><i class="bi bi-chevron-double-left"></i></a></li>';
    $h .= '<li class="page-item' . ($page['precedent'] ? '' : ' disabled') . '"><a class="page-link" href="' . ($page['precedent'] ? e(url_avec(['curseur' => $page['precedent'], 'sens_page' => 'avant'])) : '#') . '"><i class="bi bi-chevron-left"></i> Précédent</a></li>';
    $h .= '<li class="page-item' . ($page['suivant'] ? '' : ' disabled') . '"><a class="page-link" href="' . ($page['suivant'] ? e(url_avec(['curseur' => $page['suivant'], 'sens_page' => 'apres'])) : '#') . '">Suivant <i class="bi bi-chevron-right"></i></a></li>';
    return $h . '</ul></div></div>';
}

/** Graphique Chart.js déclaré en données (aucun script inline). */
function graphique(array $config, int $hauteur = 240): string
{
    return '<div style="height:' . $hauteur . 'px"><canvas data-graphique="' . e(json_encode($config, JSON_UNESCAPED_UNICODE)) . '"></canvas></div>';
}
