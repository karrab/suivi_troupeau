<?php
defined('INC') || exit; // fichier inclus uniquement
/**
 * Référentiels (catégories, races, lots) mis en cache APCu.
 */

function ref_categories(): array
{
    return cache_memoriser(cache_cle(['referentiels'], 'categories'), 3600, static fn() =>
        array_column(bd_tous('SELECT * FROM categories ORDER BY ordre, id'), null, 'id'));
}

function ref_races(): array
{
    return cache_memoriser(cache_cle(['referentiels'], 'races'), 3600, static fn() =>
        array_column(bd_tous('SELECT * FROM races ORDER BY espece, nom'), null, 'id'));
}

function ref_lots(): array
{
    return cache_memoriser(cache_cle(['referentiels'], 'lots'), 3600, static fn() =>
        array_column(bd_tous('SELECT * FROM lots ORDER BY nom'), null, 'id'));
}

function ref_libelle_categorie(?int $id): string
{
    return $id ? (ref_categories()[$id]['libelle'] ?? '') : '';
}

function ref_nom_race(?int $id): string
{
    return $id ? (ref_races()[$id]['nom'] ?? '') : '';
}

function ref_nom_lot(?int $id): string
{
    return $id ? (ref_lots()[$id]['nom'] ?? '') : '';
}

/** Catalogue des permissions : module => [libellé, actions]. */
function catalogue_permissions(): array
{
    $crud = ['voir' => 'Consulter', 'ajouter' => 'Ajouter', 'modifier' => 'Modifier', 'supprimer' => 'Supprimer', 'exporter' => 'Imprimer / exporter PDF'];
    return [
        'animaux'      => ['libelle' => 'Animaux (fiches)', 'actions' => $crud],
        'pesees'       => ['libelle' => 'Suivi : pesées', 'actions' => $crud],
        'soins'        => ['libelle' => 'Suivi : santé', 'actions' => $crud],
        'saillies'     => ['libelle' => 'Suivi : saillies', 'actions' => $crud],
        'mises_bas'    => ['libelle' => 'Suivi : mises bas', 'actions' => $crud],
        'lait'         => ['libelle' => 'Suivi : production laitière', 'actions' => $crud],
        'mouvements'   => ['libelle' => 'Suivi : mouvements', 'actions' => $crud],
        'indices'      => ['libelle' => 'Indices zootechniques', 'actions' => [
            'voir' => 'Consulter les résultats', 'calculer' => 'Calculer / enregistrer', 'supprimer' => 'Supprimer des résultats',
            'exporter' => 'Imprimer / exporter PDF', 'formules' => 'Modifier les formules',
        ]],
        'referentiels' => ['libelle' => 'Référentiels (races, lots)', 'actions' => ['voir' => 'Consulter', 'gerer' => 'Gérer']],
        'utilisateurs' => ['libelle' => 'Utilisateurs', 'actions' => $crud],
        'roles'        => ['libelle' => 'Rôles & permissions', 'actions' => ['voir' => 'Consulter', 'gerer' => 'Gérer']],
        'traces'       => ['libelle' => 'Traces (journal)', 'actions' => ['voir' => 'Consulter', 'exporter' => 'Exporter PDF']],
        'parametres'   => ['libelle' => 'Paramètres', 'actions' => ['gerer' => 'Gérer les paramètres', 'systeme' => 'Système & cache']],
    ];
}

/** Insère les permissions manquantes du catalogue. Retourne le nombre ajouté. */
function synchroniser_permissions(): int
{
    $n = 0;
    foreach (catalogue_permissions() as $module => $m) {
        foreach ($m['actions'] as $action => $lib) {
            $n += bd_requete('INSERT INTO permissions (code, module, libelle) VALUES (:c, :m, :l)
                              ON DUPLICATE KEY UPDATE module = VALUES(module), libelle = VALUES(libelle)',
                ['c' => "$module.$action", 'm' => $module, 'l' => $lib])->rowCount() === 1 ? 1 : 0;
        }
    }
    cache_invalider('permissions');
    return $n;
}

/** Donne à un rôle les permissions correspondant aux motifs (« *.voir », « soins.* »). */
function accorder_permissions(int $roleId, array $motifs): void
{
    foreach (bd_paires('SELECT id, code FROM permissions') as $id => $code) {
        foreach ($motifs as $m) {
            if (fnmatch($m, $code)) {
                bd_requete('INSERT IGNORE INTO roles_permissions (role_id, permission_id) VALUES (:r, :p)', ['r' => $roleId, 'p' => (int) $id]);
                break;
            }
        }
    }
    cache_invalider('permissions');
}
