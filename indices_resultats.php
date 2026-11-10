<?php
/**
 * Résultats d'indices enregistrés : filtres, tri (récent / valeur), keyset,
 * export PDF (?pdf=1), suppression d'un résultat ou purge (POST).
 */
require __DIR__ . '/inc/init.php';
require INC . '/pdf.php';
require INC . '/indices.php';
exiger_permission('indices.voir');

if (est_post()) {
    exiger_permission('indices.supprimer');
    if (($_POST['action'] ?? '') === 'supprimer') {
        $id = (int) ($_POST['id'] ?? 0);
        $ancien = bd_trouver('resultats_indices', $id) ?? abandonner(404);
        bd_supprimer('resultats_indices', $id);
        tracer('suppression', 'resultat_indice', $id, 'Suppression d\'un résultat d\'indice', $ancien, null);
        $n = 1;
    } else {
        $annee = (int) ($_POST['annee'] ?? -1);
        $t = (string) ($_POST['type_groupe'] ?? '');
        $n = bd_requete('DELETE FROM resultats_indices WHERE annee = :a AND type_groupe = :t', ['a' => $annee, 't' => $t])->rowCount();
        tracer('suppression', 'resultat_indice', null, "Purge des résultats : année $annee, " . libelle('type_groupe', $t) . " ($n lignes)");
    }
    cache_invalider('indices');
    flash('success', "$n résultat(s) supprimé(s).");
    rediriger($_SERVER['HTTP_REFERER'] ?? 'indices_resultats.php');
}

$g = $_GET;
$where = [];
$p = [];
if (isset($g['annee']) && is_numeric($g['annee'])) {
    $where[] = 'r.annee = :a';
    $p['a'] = (int) $g['annee'];
}
if (!empty($g['type_groupe']) && isset(libelles('type_groupe')[$g['type_groupe']])) {
    $where[] = 'r.type_groupe = :t';
    $p['t'] = $g['type_groupe'];
}
if (!empty($g['indice_id']) && ctype_digit((string) $g['indice_id'])) {
    $where[] = 'r.indice_id = :i';
    $p['i'] = (int) $g['indice_id'];
}
if (!empty($g['niveau']) && isset(libelles('niveau')[$g['niveau']])) {
    $where[] = 'r.niveau = :n';
    $p['n'] = $g['niveau'];
}
if (($q = trim((string) ($g['q'] ?? ''))) !== '') {
    $where[] = 'r.libelle_groupe LIKE :q';
    $p['q'] = $q . '%';
}
$parValeur = ($g['tri'] ?? '') === 'valeur';
if ($parValeur) {
    $where[] = 'r.valeur IS NOT NULL';
}
$requete = [
    'select' => 'r.*, i.nom, i.unite, i.decimales', 'from' => 'FROM resultats_indices r JOIN indices i ON i.id = r.indice_id',
    'where' => $where, 'params' => $p, 'tri' => $parValeur ? 'r.valeur' : 'r.id', 'cle_tri' => $parValeur ? 'valeur' : 'id', 'id' => 'r.id',
    'sens' => strtolower((string) ($g['sens'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC', 'par_page' => par_page(),
];

if (!empty($g['pdf'])) {
    exiger_permission('indices.exporter');
    $lignes = tout_lire($requete);
    tracer('export', 'indice', null, 'Export PDF des résultats d\'indices (' . count($lignes) . ' lignes)');
    $pdf = new PdfRapport('Résultats des indices', resume_filtres(['annee' => 'Année', 'type_groupe' => 'Regroupement', 'indice_id' => 'Indice n°', 'niveau' => 'Niveau', 'q' => 'Groupe / animal']), 'L');
    $pdf->ajouterPage();
    $pdf->tableau([['label' => 'Année', 'key' => 'annee', 'w' => 0.6], ['label' => 'Groupe / animal', 'key' => 'groupe', 'w' => 1.8],
        ['label' => 'Indice', 'key' => 'nom', 'w' => 2], ['label' => 'Valeur', 'key' => 'valeur', 'w' => 1, 'align' => 'R'],
        ['label' => 'Niveau', 'key' => 'niveau', 'w' => 0.7], ['label' => 'Interprétation', 'key' => 'interpretation', 'w' => 5]],
        array_map(static fn($r) => ['annee' => $r['annee'] ?: 'Vie', 'groupe' => libelle('type_groupe', $r['type_groupe']) . ' : ' . $r['libelle_groupe'],
            'nom' => $r['nom'], 'valeur' => $r['valeur'] !== null ? fmt_nombre($r['valeur'], (int) $r['decimales']) . ' ' . $r['unite'] : 'N/D',
            'niveau' => libelle('niveau', $r['niveau']), 'interpretation' => $r['interpretation']], $lignes), 7.5);
    $pdf->envoyer('resultats_indices_' . date('Ymd_His') . '.pdf');
}

$page = paginer($requete);
$total = compter($requete, 'indices');
$annees = annees_disponibles();
$titre = 'Résultats des indices';
require INC . '/entete.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 titre-page mb-0"><i class="bi bi-table me-2"></i>Résultats enregistrés <span class="badge text-bg-light"><?= fmt_nombre($total, 0) ?></span></h1>
    <div class="d-flex gap-2 d-print-none">
        <?php if (peut('indices.supprimer') && isset($g['annee']) && is_numeric($g['annee']) && !empty($g['type_groupe'])): ?>
            <form method="post" action="indices_resultats.php" data-confirmer="Supprimer tous les résultats de cette année pour ce regroupement ?"><?= champ_csrf() ?>
                <input type="hidden" name="action" value="purger"><input type="hidden" name="annee" value="<?= (int) $g['annee'] ?>"><input type="hidden" name="type_groupe" value="<?= e($g['type_groupe']) ?>">
                <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i>Purger la sélection</button></form>
        <?php endif; ?>
        <?= boutons_liste('indices.exporter') ?>
    </div>
</div>
<div class="card carte-filtres mb-3 d-print-none"><div class="card-body">
    <form method="get" action="indices_resultats.php" class="row g-2 align-items-end">
        <div class="col-6 col-md-2"><label class="form-label">Année</label><select name="annee" class="form-select form-select-sm"><?= options(array_combine($annees, $annees) + ['0' => 'Vie entière'], $g['annee'] ?? '', 'Toutes') ?></select></div>
        <div class="col-6 col-md-2"><label class="form-label">Regroupement</label><select name="type_groupe" class="form-select form-select-sm"><?= options(libelles('type_groupe'), $g['type_groupe'] ?? '', 'Tous') ?></select></div>
        <div class="col-md-3"><label class="form-label">Indice</label><select name="indice_id" class="form-select form-select-sm"><?= options(bd_paires("SELECT id, CONCAT(nom, ' (', portee, ')') FROM indices ORDER BY portee, ordre"), $g['indice_id'] ?? '', 'Tous') ?></select></div>
        <div class="col-6 col-md-1"><label class="form-label">Niveau</label><select name="niveau" class="form-select form-select-sm"><?= options(libelles('niveau'), $g['niveau'] ?? '', 'Tous') ?></select></div>
        <div class="col-6 col-md-2"><label class="form-label">Groupe / animal</label><input name="q" class="form-control form-control-sm" value="<?= e($g['q'] ?? '') ?>" data-recherche-animal data-tous="1"></div>
        <div class="col-6 col-md-1"><label class="form-label">Tri</label><select name="tri" class="form-select form-select-sm"><?= options(['id' => 'Récent', 'valeur' => 'Valeur'], $g['tri'] ?? 'id') ?></select></div>
        <div class="col-6 col-md-1"><label class="form-label">Sens</label><select name="sens" class="form-select form-select-sm"><?= options(['desc' => 'Décroissant', 'asc' => 'Croissant'], $g['sens'] ?? 'desc') ?></select></div>
        <div class="col-12 col-md-auto d-flex gap-1"><button class="btn btn-foret btn-sm"><i class="bi bi-search"></i></button><a class="btn btn-outline-secondary btn-sm" href="indices_resultats.php"><i class="bi bi-x-lg"></i></a></div>
    </form>
</div></div>
<div class="card"><div class="table-responsive"><table class="table table-sm table-hover mb-0">
    <thead><tr><th>Année</th><th>Regroupement</th><th>Groupe / animal</th><th>Indice</th><th class="text-end">Valeur</th><th>Niveau</th><th>Interprétation</th><th class="actions d-print-none"></th></tr></thead>
    <tbody>
    <?php foreach ($page['lignes'] as $r): ?>
        <tr class="niveau-<?= e($r['niveau']) ?>"><td><?= $r['annee'] ?: 'Vie' ?></td><td class="small"><?= e(libelle('type_groupe', $r['type_groupe'])) ?></td>
            <td><?= $r['animal_id'] ? '<a href="' . e(url('animal.php', ['id' => $r['animal_id'], 'onglet' => 'indices'])) . '">' . e($r['libelle_groupe']) . '</a>' : e($r['libelle_groupe']) ?></td>
            <td><?= e($r['nom']) ?></td><td class="text-end fw-semibold text-nowrap"><?= $r['valeur'] !== null ? fmt_nombre($r['valeur'], (int) $r['decimales']) . ' ' . e($r['unite']) : 'N/D' ?></td>
            <td><?= badge_niveau($r['niveau']) ?></td><td class="small"><?= e($r['interpretation']) ?></td>
            <td class="actions d-print-none"><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('indice_detail.php', ['id' => $r['id']])) ?>" title="Détail"><i class="bi bi-info-circle"></i></a>
                <?php if (peut('indices.supprimer')): ?><?= bouton_supprimer('indices_resultats.php', (int) $r['id'], 'Supprimer ce résultat ?') ?><?php endif; ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$page['lignes']): ?><tr><td colspan="8" class="text-center text-muted py-4">Aucun résultat.</td></tr><?php endif; ?>
    </tbody>
</table></div></div>
<?= barre_pagination($page, $total) ?>
<?php require INC . '/pied.php';
