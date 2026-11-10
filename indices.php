<?php
/**
 * Indices du troupeau : synthèse d'une année par regroupement, formulaire de
 * calcul (POST), synthèse des indices individuels, export PDF (?pdf=1).
 */
require __DIR__ . '/inc/init.php';
require INC . '/pdf.php';
require INC . '/indices.php';
exiger_permission('indices.voir');

$TYPES = ['troupeau', 'espece', 'categorie', 'race', 'lot'];

// ------------------------------------------------------------ calcul
if (est_post()) {
    exiger_permission('indices.calculer');
    $annee = (int) ($_POST['annee'] ?? date('Y'));
    $ids = array_map('intval', (array) ($_POST['indices'] ?? []));
    $debut = microtime(true);
    $messages = [];
    if (($_POST['cible'] ?? 'troupeau') === 'animal') {
        $r = calculer_indices_animaux($annee, null, $ids);
        $messages[] = "{$r['enregistres']} résultat(s) pour {$r['animaux']} animal(aux)";
        $vers = url('indices_resultats.php', ['annee' => $annee, 'type_groupe' => 'animal']);
    } else {
        $types = array_values(array_intersect((array) ($_POST['types'] ?? ['troupeau']), $TYPES)) ?: ['troupeau'];
        foreach ($types as $t) {
            $messages[] = libelle('type_groupe', $t) . ' : ' . calculer_indices_troupeau($annee, $t, $ids);
        }
        $vers = url('indices.php', ['annee' => $annee, 'type_groupe' => $types[0]]);
    }
    $periode = $annee ?: 'vie entière';
    tracer('calcul', 'indice', null, "Calcul des indices ($periode) : " . implode(', ', $messages));
    flash('success', sprintf('Indices %s calculés et enregistrés — %s (%.2f s).', $periode, implode(' ; ', $messages), microtime(true) - $debut));
    rediriger($vers);
}

$annee = isset($_GET['annee']) && is_numeric($_GET['annee']) ? (int) $_GET['annee']
    : (int) (bd_valeur("SELECT MAX(annee) FROM resultats_indices WHERE type_groupe = 'troupeau'") ?? date('Y'));
$typeGroupe = in_array($_GET['type_groupe'] ?? '', $TYPES, true) ? $_GET['type_groupe'] : 'troupeau';
$resultats = cache_memoriser(cache_cle(['indices'], "synthese:$annee:$typeGroupe"), 300, static fn() => bd_tous(
    'SELECT r.*, i.nom, i.unite, i.decimales, i.description FROM resultats_indices r JOIN indices i ON i.id = r.indice_id
     WHERE r.annee = :a AND r.type_groupe = :t ORDER BY r.libelle_groupe, i.ordre', ['a' => $annee, 't' => $typeGroupe]));
$parGroupe = [];
foreach ($resultats as $r) {
    $parGroupe[$r['libelle_groupe'] ?: 'Troupeau entier'][] = $r;
}

// ------------------------------------------------------------ export PDF
if (!empty($_GET['pdf'])) {
    exiger_permission('indices.exporter');
    tracer('export', 'indice', null, "Export PDF de la synthèse des indices ($annee, $typeGroupe)");
    $pdf = new PdfRapport('Indices zootechniques — ' . ($annee ?: 'vie entière'), 'Regroupement : ' . libelle('type_groupe', $typeGroupe), 'L');
    $pdf->ajouterPage();
    foreach ($parGroupe as $lib => $lignes) {
        $pdf->titreSection($lib);
        $pdf->tableau([['label' => 'Indice', 'key' => 'nom', 'w' => 2], ['label' => 'Valeur', 'key' => 'valeur', 'w' => 1, 'align' => 'R'],
            ['label' => 'Niveau', 'key' => 'niveau', 'w' => 0.7], ['label' => 'Interprétation', 'key' => 'interpretation', 'w' => 5]],
            array_map(static fn($r) => ['nom' => $r['nom'], 'valeur' => $r['valeur'] !== null ? fmt_nombre($r['valeur'], (int) $r['decimales']) . ' ' . $r['unite'] : 'N/D',
                'niveau' => libelle('niveau', $r['niveau']), 'interpretation' => $r['interpretation']], $lignes), 7.5);
    }
    $pdf->envoyer('indices_' . $annee . '_' . $typeGroupe . '.pdf');
}

$synthese = bd_tous(
    "SELECT i.id, i.nom, i.unite, i.decimales, COUNT(r.valeur) AS n, AVG(r.valeur) AS moyenne, MIN(r.valeur) AS mini, MAX(r.valeur) AS maxi,
            SUM(r.niveau = 'bon') AS bon, SUM(r.niveau = 'moyen') AS moyen, SUM(r.niveau = 'faible') AS faible
     FROM indices i LEFT JOIN resultats_indices r ON r.indice_id = i.id AND r.type_groupe = 'animal' AND r.annee = :a
     WHERE i.portee = 'animal' AND i.actif = 1 GROUP BY i.id, i.nom, i.unite, i.decimales, i.ordre ORDER BY i.ordre", ['a' => $annee]);
$annees = annees_disponibles();

$titre = 'Indices du troupeau';
require INC . '/entete.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 titre-page mb-0"><i class="bi bi-graph-up-arrow me-2"></i>Indices zootechniques — <?= $annee ?: 'vie entière' ?></h1>
    <div class="d-flex gap-2 d-print-none">
        <form method="get" action="indices.php" class="d-flex gap-1">
            <select name="annee" class="form-select form-select-sm" data-auto aria-label="Année"><?= options(array_combine($annees, $annees) + [0 => 'Vie entière'], $annee) ?></select>
            <select name="type_groupe" class="form-select form-select-sm" data-auto aria-label="Regroupement"><?= options(array_intersect_key(libelles('type_groupe'), array_flip($TYPES)), $typeGroupe) ?></select>
        </form>
        <button class="btn btn-outline-secondary btn-sm" data-imprimer><i class="bi bi-printer me-1"></i>Imprimer</button>
        <?php if (peut('indices.exporter')): ?><a class="btn btn-outline-danger btn-sm" target="_blank" href="<?= e(url('indices.php', ['annee' => $annee, 'type_groupe' => $typeGroupe, 'pdf' => 1])) ?>"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a><?php endif; ?>
    </div>
</div>

<?php if (peut('indices.calculer')): ?>
<div class="card mb-3 d-print-none"><div class="card-header"><i class="bi bi-calculator me-1"></i>Calculer et enregistrer les indices</div><div class="card-body">
    <form method="post" action="indices.php" class="row g-2 align-items-end"><?= champ_csrf() ?>
        <div class="col-6 col-md-2"><label class="form-label small">Période</label>
            <select name="annee" class="form-select form-select-sm"><?= options(array_combine($annees, $annees) + [0 => 'Vie entière'], $annee) ?></select></div>
        <div class="col-6 col-md-2"><label class="form-label small">Cible</label>
            <select name="cible" id="cible" class="form-select form-select-sm"><option value="troupeau">Troupeau / groupes</option><option value="animal">Chaque animal</option></select></div>
        <div class="col-md-4" data-cible="troupeau"><label class="form-label small">Regroupements</label><div class="d-flex flex-wrap gap-2">
            <?php foreach ($TYPES as $t): ?>
                <div class="form-check form-check-inline small mb-0"><input class="form-check-input" type="checkbox" name="types[]" value="<?= $t ?>" id="t_<?= $t ?>"<?= coche(in_array($t, ['troupeau', 'espece'], true)) ?>>
                    <label class="form-check-label" for="t_<?= $t ?>"><?= e(libelle('type_groupe', $t)) ?></label></div>
            <?php endforeach; ?></div></div>
        <div class="col-md-3" data-cible="troupeau"><label class="form-label small">Indices (aucun = tous)</label>
            <select name="indices[]" class="form-select form-select-sm" multiple size="3"><?= options(array_column(definitions_indices('troupeau'), 'nom', 'id'), null) ?></select></div>
        <div class="col-md-7 d-none" data-cible="animal"><label class="form-label small">Indices par animal (aucun = tous)</label>
            <select name="indices[]" class="form-select form-select-sm" multiple size="3"><?= options(array_column(definitions_indices('animal'), 'nom', 'id'), null) ?></select></div>
        <div class="col-md-1"><button class="btn btn-foret btn-sm w-100"><i class="bi bi-play-fill"></i> Calculer</button></div>
    </form>
    <div class="form-text">Les résultats sont enregistrés (et remplacés s'ils existent déjà) avec la formule et les variables utilisées. Les formules se modifient dans <a href="formules.php">Formules des indices</a>.</div>
</div></div>
<?php endif; ?>

<?php foreach ($parGroupe as $lib => $lignes): ?>
    <div class="card mb-3"><div class="card-header d-flex justify-content-between"><span><i class="bi bi-diagram-3 me-1"></i><?= e(libelle('type_groupe', $typeGroupe)) ?> : <?= e($lib) ?></span>
        <span class="small text-muted fw-normal">calculé le <?= fmt_date($lignes[0]['calcule_le'], true) ?></span></div>
        <div class="card-body"><div class="row g-2">
            <?php foreach ($lignes as $r): ?>
                <div class="col-md-6 col-xl-4"><div class="border rounded p-2 h-100 bg-white niveau-<?= e($r['niveau']) ?>">
                    <div class="d-flex justify-content-between align-items-start"><div class="small fw-semibold" title="<?= e($r['description']) ?>"><?= e($r['nom']) ?></div><?= badge_niveau($r['niveau']) ?></div>
                    <div class="fs-4 fw-bold text-foret"><?= $r['valeur'] !== null ? fmt_nombre($r['valeur'], (int) $r['decimales']) : 'N/D' ?> <small class="fs-6 text-muted"><?= e($r['unite']) ?></small></div>
                    <div class="small text-muted"><?= e($r['interpretation']) ?></div>
                    <a class="small d-print-none" href="<?= e(url('indice_detail.php', ['id' => $r['id']])) ?>">Détail du calcul</a>
                </div></div>
            <?php endforeach; ?>
        </div></div></div>
<?php endforeach; ?>
<?php if (!$parGroupe): ?><div class="alert alert-info">Aucun indice enregistré pour cette période et ce regroupement : lancez le calcul ci-dessus.</div><?php endif; ?>

<div class="card"><div class="card-header"><i class="bi bi-person-badge me-1"></i>Synthèse des indices individuels — <?= $annee ?: 'vie entière' ?></div>
    <div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th>Indice</th><th class="text-end">Animaux</th><th class="text-end">Moyenne</th><th class="text-end">Min</th><th class="text-end">Max</th><th class="text-center">Bon</th><th class="text-center">Moyen</th><th class="text-center">Faible</th><th class="d-print-none"></th></tr></thead>
        <tbody><?php foreach ($synthese as $x): ?>
            <tr><td><?= e($x['nom']) ?></td><td class="text-end"><?= (int) $x['n'] ?></td>
                <td class="text-end fw-semibold"><?= $x['moyenne'] !== null ? fmt_nombre($x['moyenne'], (int) $x['decimales']) . ' ' . e($x['unite']) : '—' ?></td>
                <td class="text-end"><?= $x['mini'] !== null ? fmt_nombre($x['mini'], (int) $x['decimales']) : '—' ?></td>
                <td class="text-end"><?= $x['maxi'] !== null ? fmt_nombre($x['maxi'], (int) $x['decimales']) : '—' ?></td>
                <td class="text-center"><span class="badge text-bg-success"><?= (int) $x['bon'] ?></span></td>
                <td class="text-center"><span class="badge text-bg-warning"><?= (int) $x['moyen'] ?></span></td>
                <td class="text-center"><span class="badge text-bg-danger"><?= (int) $x['faible'] ?></span></td>
                <td class="d-print-none"><a class="btn btn-sm btn-outline-secondary" title="Classement" href="<?= e(url('indices_resultats.php', ['annee' => $annee, 'type_groupe' => 'animal', 'indice_id' => $x['id'], 'tri' => 'valeur', 'sens' => 'desc'])) ?>"><i class="bi bi-sort-down"></i></a></td></tr>
        <?php endforeach; ?></tbody>
    </table></div>
</div>
<?php require INC . '/pied.php';
