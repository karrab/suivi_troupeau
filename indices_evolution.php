<?php
/** Évolution annuelle des indices d'un groupe (tableau, tendance, graphiques, PDF). */
require __DIR__ . '/inc/init.php';
require INC . '/pdf.php';
require INC . '/indices.php';
exiger_permission('indices.voir');

$TYPES = ['troupeau', 'espece', 'categorie', 'race', 'lot'];
$typeGroupe = in_array($_GET['type_groupe'] ?? '', $TYPES, true) ? $_GET['type_groupe'] : 'troupeau';
$groupes = groupes($typeGroupe);
$valeur = (string) ($_GET['valeur_groupe'] ?? array_key_first($groupes));
if (!array_key_exists($valeur, $groupes)) {
    $valeur = (string) array_key_first($groupes);
}

$annees = [];
$matrice = [];
foreach (bd_tous('SELECT r.annee, r.valeur, r.niveau, i.id, i.nom, i.unite, i.decimales FROM resultats_indices r JOIN indices i ON i.id = r.indice_id
                  WHERE r.type_groupe = :t AND r.valeur_groupe = :v AND r.annee > 0 ORDER BY i.ordre, r.annee', ['t' => $typeGroupe, 'v' => $valeur]) as $r) {
    $annees[$r['annee']] = true;
    $matrice[$r['id']]['def'] = $r;
    $matrice[$r['id']]['valeurs'][$r['annee']] = $r;
}
$annees = array_keys($annees);
sort($annees);

if (!empty($_GET['pdf'])) {
    exiger_permission('indices.exporter');
    tracer('export', 'indice', null, 'Export PDF de l\'évolution des indices');
    $pdf = new PdfRapport('Évolution des indices', libelle('type_groupe', $typeGroupe) . ' : ' . ($groupes[$valeur] ?? ''), 'L');
    $pdf->ajouterPage();
    $cols = [['label' => 'Indice', 'key' => 'nom', 'w' => 3]];
    foreach ($annees as $a) {
        $cols[] = ['label' => (string) $a, 'key' => "a$a", 'w' => 1, 'align' => 'R'];
    }
    $lignes = [];
    foreach ($matrice as $m) {
        $l = ['nom' => $m['def']['nom'] . ($m['def']['unite'] ? ' (' . $m['def']['unite'] . ')' : '')];
        foreach ($annees as $a) {
            $c = $m['valeurs'][$a] ?? null;
            $l["a$a"] = $c && $c['valeur'] !== null ? fmt_nombre($c['valeur'], (int) $m['def']['decimales']) . ' [' . mb_substr(libelle('niveau', $c['niveau']), 0, 1) . ']' : '—';
        }
        $lignes[] = $l;
    }
    $pdf->tableau($cols, $lignes, 8);
    $pdf->setFont(false, 7.5);
    $pdf->paragraph('[B] Bon  [M] Moyen  [F] Faible  [N] Non déterminé', 4);
    $pdf->envoyer('evolution_indices.pdf');
}

$palette = ['#195c3e', '#c97b3d', '#2f6fb0', '#a83c5a', '#6a4c93', '#3c9d9b', '#d4a017', '#7a7a7a'];
$titre = 'Évolution des indices';
require INC . '/entete.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 titre-page mb-0"><i class="bi bi-bar-chart-line me-2"></i>Évolution annuelle des indices</h1>
    <div class="d-flex gap-2 d-print-none">
        <form method="get" action="indices_evolution.php" class="d-flex gap-1">
            <select name="type_groupe" class="form-select form-select-sm" data-auto><?= options(array_intersect_key(libelles('type_groupe'), array_flip($TYPES)), $typeGroupe) ?></select>
            <?php if ($typeGroupe !== 'troupeau'): ?><select name="valeur_groupe" class="form-select form-select-sm" data-auto><?= options($groupes, $valeur) ?></select><?php endif; ?>
        </form>
        <button class="btn btn-outline-secondary btn-sm" data-imprimer><i class="bi bi-printer me-1"></i>Imprimer</button>
        <?php if (peut('indices.exporter')): ?><a class="btn btn-outline-danger btn-sm" target="_blank" href="<?= e(url_avec(['pdf' => 1])) ?>"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a><?php endif; ?>
    </div>
</div>
<?php if (!$annees): ?>
    <div class="alert alert-info">Aucun résultat annuel pour ce groupe. Calculez les indices de plusieurs années depuis <a href="indices.php">Indices du troupeau</a>.</div>
<?php else: ?>
    <div class="card mb-3"><div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th>Indice</th><?php foreach ($annees as $a): ?><th class="text-end"><?= $a ?></th><?php endforeach; ?><th class="text-end">Tendance</th></tr></thead>
        <tbody><?php foreach ($matrice as $m): $vals = array_values(array_filter(array_map(static fn($c) => $c['valeur'], $m['valeurs']), static fn($v) => $v !== null)); ?>
            <tr><td><?= e($m['def']['nom']) ?> <span class="text-muted small"><?= e($m['def']['unite']) ?></span></td>
                <?php foreach ($annees as $a): $c = $m['valeurs'][$a] ?? null; ?>
                    <td class="text-end"><?php if ($c): ?><span class="me-1"><?= $c['valeur'] !== null ? fmt_nombre($c['valeur'], (int) $m['def']['decimales']) : 'N/D' ?></span><?= badge_niveau($c['niveau']) ?><?php else: ?>—<?php endif; ?></td>
                <?php endforeach; ?>
                <td class="text-end"><?php if (count($vals) > 1): $d = end($vals) - $vals[0]; ?><i class="bi <?= $d > 0 ? 'bi-arrow-up-right text-success' : ($d < 0 ? 'bi-arrow-down-right text-danger' : 'bi-arrow-right') ?>"></i> <?= fmt_nombre($d, (int) $m['def']['decimales']) ?><?php endif; ?></td></tr>
        <?php endforeach; ?></tbody>
    </table></div></div>
    <div class="row g-3">
        <?php $i = 0; foreach ($matrice as $m): $coul = $palette[$i++ % count($palette)]; ?>
            <div class="col-md-6 col-xl-4"><div class="card h-100"><div class="card-header small"><?= e($m['def']['nom']) ?> (<?= e($m['def']['unite']) ?>)</div><div class="card-body">
                <?= graphique(['type' => 'line', 'labels' => array_map('strval', $annees), 'zero' => true,
                    'datasets' => [['label' => $m['def']['nom'], 'data' => array_map(static fn($a) => isset($m['valeurs'][$a]['valeur']) ? (float) $m['valeurs'][$a]['valeur'] : null, $annees),
                        'borderColor' => $coul, 'backgroundColor' => $coul, 'tension' => .2, 'spanGaps' => true]]], 160) ?>
            </div></div></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php require INC . '/pied.php';
