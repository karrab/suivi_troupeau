<?php
/**
 * Liste d'un suivi (?type=pesees|soins|saillies|mises_bas|lait|mouvements) :
 * filtres, pagination keyset, impression, export PDF (?pdf=1), suppression (POST).
 */
require __DIR__ . '/inc/init.php';
require INC . '/pdf.php';

$type = (string) ($_GET['type'] ?? '');
$s = suivi($type);
exiger_permission("$type.voir");

// --------------------------------------------------------- suppression
if (est_post() && ($_POST['action'] ?? '') === 'supprimer') {
    exiger_permission("$type.supprimer");
    $id = (int) ($_POST['id'] ?? 0);
    $ancien = suivi_charger($s, $id);
    bd_transaction(static function () use ($s, $id, $ancien) {
        bd_supprimer($s['table'], $id);
        bd_requete("DELETE FROM historique_animaux WHERE table_ref = :t AND ref_id = :r AND type_evenement <> 'naissance'", ['t' => $s['table'], 'r' => $id]);
        historique_ajouter((int) $ancien['animal_id'], date('Y-m-d'), 'suppression',
            'Suppression : ' . suivi_resume($s, $ancien) . ' du ' . fmt_date($ancien['date_evenement']), $s['table'], $id);
    });
    $instantane = array_filter($ancien, static fn($k) => !preg_match('/(_boucle|_nom|_espece)$/', (string) $k), ARRAY_FILTER_USE_KEY);
    tracer('suppression', $type, $id, ucfirst($s['singulier']) . ' n° ' . $id . ' (' . $ancien['animal_boucle'] . ')', $instantane, null);
    cache_invalider('suivis');
    flash('success', 'Enregistrement supprimé.');
    rediriger(($_POST['retour'] ?? '') === 'animal'
        ? url('animal.php', ['id' => (int) $ancien['animal_id'], 'onglet' => $type])
        : url('suivis.php', ['type' => $type]));
}

// --------------------------------------------------------------- filtres
[$select, $from] = suivi_sql($s);
$g = $_GET;
$where = [];
$p = [];
if (($q = trim((string) ($g['animal'] ?? ''))) !== '') {
    $where[] = '(a.boucle LIKE :aq1 OR a.nom LIKE :aq2)';
    $p['aq1'] = $q . '%';
    $p['aq2'] = $q . '%';
}
if (!empty($g['du']) && est_date((string) $g['du'])) {
    $where[] = 't.date_evenement >= :du';
    $p['du'] = $g['du'];
}
if (!empty($g['au']) && est_date((string) $g['au'])) {
    $where[] = 't.date_evenement <= :au';
    $p['au'] = $g['au'];
}
if (!empty($g['espece']) && isset(libelles('espece')[$g['espece']])) {
    $where[] = 'a.espece = :espece';
    $p['espece'] = $g['espece'];
}
if (!empty($g['lot_id']) && ctype_digit((string) $g['lot_id'])) {
    $where[] = 'a.lot_id = :lot';
    $p['lot'] = (int) $g['lot_id'];
}
foreach ($s['filtres'] as $f) {
    if (!empty($g[$f]) && isset(libelles($s['champs'][$f]['options'])[$g[$f]])) {
        $where[] = "t.$f = :f_$f";
        $p["f_$f"] = $g[$f];
    }
}
if ($s['recherche'] && ($txt = trim((string) ($g['q'] ?? ''))) !== '') {
    $ou = [];
    foreach ($s['recherche'] as $i => $f) {
        $ou[] = "t.$f LIKE :r$i";
        $p["r$i"] = '%' . $txt . '%';
    }
    $where[] = '(' . implode(' OR ', $ou) . ')';
}
$requete = [
    'select' => $select, 'from' => $from, 'where' => $where, 'params' => $p,
    'tri' => 't.date_evenement', 'cle_tri' => 'date_evenement', 'id' => 't.id',
    'sens' => strtolower((string) ($g['sens'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC', 'par_page' => par_page(),
];

// ------------------------------------------------------------ export PDF
if (!empty($g['pdf'])) {
    exiger_permission("$type.exporter");
    $lignes = tout_lire($requete);
    tracer('export', $type, null, 'Export PDF : ' . $s['titre'] . ' (' . count($lignes) . ' lignes)');
    $libs = ['animal' => 'Animal', 'du' => 'Du', 'au' => 'Au', 'espece' => 'Espèce', 'q' => 'Recherche'];
    foreach ($s['filtres'] as $f) {
        $libs[$f] = $s['champs'][$f]['libelle'];
    }
    $pdf = new PdfRapport($s['titre'], resume_filtres($libs), 'L');
    $pdf->ajouterPage();
    $pdf->tableau(array_map(static fn($c) => ['label' => $s['champs'][$c]['libelle'], 'key' => $c,
        'w' => in_array($c, ['remarques', 'diagnostic', 'animal_id'], true) ? 1.8 : 1, 'align' => $s['champs'][$c]['type'] === 'nombre' ? 'R' : 'L'], $s['colonnes']),
        array_map(static function ($l) use ($s) {
            $o = [];
            foreach ($s['colonnes'] as $c) {
                $o[$c] = suivi_valeur($s, $c, $l, false);
            }
            return $o;
        }, $lignes));
    $pdf->envoyer($type . '_' . date('Ymd_His') . '.pdf');
}

$page = paginer($requete);
$total = compter($requete, 'suivis');
$titre = $s['titre'];
require INC . '/entete.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 titre-page mb-0"><i class="bi <?= $s['icone'] ?> me-2"></i><?= e($s['titre']) ?> <span class="badge text-bg-light"><?= fmt_nombre($total, 0) ?></span></h1>
    <div class="d-flex gap-2 d-print-none">
        <?= boutons_liste("$type.exporter") ?>
        <?php if (peut("$type.ajouter")): ?><a href="<?= e(url('suivi_form.php', ['type' => $type])) ?>" class="btn btn-foret btn-sm"><i class="bi bi-plus-lg me-1"></i>Ajouter</a><?php endif; ?>
    </div>
</div>

<div class="card carte-filtres mb-3 d-print-none"><div class="card-body">
    <form method="get" action="suivis.php" class="row g-2 align-items-end">
        <input type="hidden" name="type" value="<?= e($type) ?>">
        <div class="col-md-2"><label class="form-label">Animal (n° boucle)</label><input type="search" name="animal" class="form-control form-control-sm" value="<?= e($g['animal'] ?? '') ?>" data-recherche-animal data-tous="1"></div>
        <div class="col-6 col-md-2"><label class="form-label">Du</label><input type="date" name="du" class="form-control form-control-sm" value="<?= e($g['du'] ?? '') ?>"></div>
        <div class="col-6 col-md-2"><label class="form-label">Au</label><input type="date" name="au" class="form-control form-control-sm" value="<?= e($g['au'] ?? '') ?>"></div>
        <div class="col-6 col-md-1"><label class="form-label">Espèce</label><select name="espece" class="form-select form-select-sm"><?= options(libelles('espece'), $g['espece'] ?? '', 'Toutes') ?></select></div>
        <div class="col-6 col-md-1"><label class="form-label">Lot</label><select name="lot_id" class="form-select form-select-sm"><?= options(array_column(ref_lots(), 'nom', 'id'), $g['lot_id'] ?? '', 'Tous') ?></select></div>
        <?php foreach ($s['filtres'] as $f): ?>
            <div class="col-6 col-md-1"><label class="form-label"><?= e($s['champs'][$f]['libelle']) ?></label>
                <select name="<?= $f ?>" class="form-select form-select-sm"><?= options(libelles($s['champs'][$f]['options']), $g[$f] ?? '', 'Tous') ?></select></div>
        <?php endforeach; ?>
        <?php if ($s['recherche']): ?><div class="col-md-2"><label class="form-label">Texte</label><input type="search" name="q" class="form-control form-control-sm" value="<?= e($g['q'] ?? '') ?>" placeholder="Diagnostic, produit..."></div><?php endif; ?>
        <div class="col-6 col-md-2"><label class="form-label">Ordre</label><select name="sens" class="form-select form-select-sm"><?= options(['desc' => 'Récent', 'asc' => 'Ancien'], $g['sens'] ?? 'desc') ?></select></div>
        <div class="col-md-auto d-flex gap-1"><button class="btn btn-foret btn-sm"><i class="bi bi-search me-1"></i>Filtrer</button>
            <a href="<?= e(url('suivis.php', ['type' => $type])) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-lg"></i></a></div>
    </form>
</div></div>

<div class="impression-seule mb-2"><strong><?= e($s['titre']) ?></strong> — imprimé le <?= date('d/m/Y H:i') ?></div>
<div class="card"><div class="table-responsive"><table class="table table-hover table-sm mb-0">
    <thead><tr><?php foreach ($s['colonnes'] as $c): ?><th class="<?= $s['champs'][$c]['type'] === 'nombre' ? 'text-end' : '' ?>"><?= e($s['champs'][$c]['libelle']) ?></th><?php endforeach; ?><th class="actions d-print-none"></th></tr></thead>
    <tbody>
    <?php foreach ($page['lignes'] as $l): ?>
        <tr><?php foreach ($s['colonnes'] as $c): ?><td class="<?= $s['champs'][$c]['type'] === 'nombre' ? 'text-end' : '' ?>"><?= suivi_valeur($s, $c, $l) ?></td><?php endforeach; ?>
            <td class="actions d-print-none">
                <?php if (peut("$type.modifier")): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('suivi_form.php', ['type' => $type, 'id' => $l['id']])) ?>" title="Modifier"><i class="bi bi-pencil"></i></a><?php endif; ?>
                <?php if (peut("$type.exporter")): ?><a class="btn btn-sm btn-outline-danger" target="_blank" href="<?= e(url('suivi_pdf.php', ['type' => $type, 'id' => $l['id']])) ?>" title="PDF"><i class="bi bi-file-earmark-pdf"></i></a><?php endif; ?>
                <?php if (peut("$type.supprimer")): ?><?= bouton_supprimer(url('suivis.php', ['type' => $type]), (int) $l['id'], 'Supprimer cet enregistrement (' . $l['animal_boucle'] . ', ' . fmt_date($l['date_evenement']) . ') ?') ?><?php endif; ?>
            </td></tr>
    <?php endforeach; ?>
    <?php if (!$page['lignes']): ?><tr><td colspan="<?= count($s['colonnes']) + 1 ?>" class="text-center text-muted py-4">Aucun enregistrement.</td></tr><?php endif; ?>
    </tbody>
</table></div></div>
<?= barre_pagination($page, $total) ?>
<?php require INC . '/pied.php';
