<?php
/** Autocomplétion des numéros de boucle (réponse JSON). */
require __DIR__ . '/inc/init.php';
exiger_permission('animaux.voir');

$q = trim((string) ($_GET['q'] ?? ''));
$where = ['(boucle LIKE :q1 OR nom LIKE :q2)'];
$p = ['q1' => $q . '%', 'q2' => $q . '%'];
if (in_array($_GET['sexe'] ?? '', ['M', 'F'], true)) {
    $where[] = 'sexe = :sexe';
    $p['sexe'] = $_GET['sexe'];
}
if (empty($_GET['tous'])) {
    $where[] = "statut = 'actif'";
}
$lignes = bd_tous('SELECT id, boucle, nom, espece, sexe FROM animaux WHERE ' . implode(' AND ', $where) . ' ORDER BY boucle LIMIT 20', $p);
json_reponse(array_map(static fn($a) => [
    'id' => (int) $a['id'], 'boucle' => $a['boucle'],
    'libelle' => $a['boucle'] . ($a['nom'] ? ' — ' . $a['nom'] : '') . ' (' . libelle('espece', $a['espece']) . ', ' . $a['sexe'] . ')',
], $lignes));
