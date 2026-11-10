<?php
/** Journal des traces (audit) : filtres, keyset, export PDF (?pdf=1). */
require __DIR__ . '/inc/init.php';
require INC . '/pdf.php';
exiger_permission('traces.voir');

$g = $_GET;
$where = [];
$p = [];
if (!empty($g['utilisateur_id']) && ctype_digit((string) $g['utilisateur_id'])) {
    $where[] = 't.utilisateur_id = :u';
    $p['u'] = (int) $g['utilisateur_id'];
}
if (!empty($g['action']) && isset(libelles('action_trace')[$g['action']])) {
    $where[] = 't.action = :a';
    $p['a'] = $g['action'];
}
if (!empty($g['objet']) && preg_match('/^\w+$/', (string) $g['objet'])) {
    $where[] = 't.objet = :o';
    $p['o'] = $g['objet'];
}
if (!empty($g['objet_id']) && ctype_digit((string) $g['objet_id'])) {
    $where[] = 't.objet_id = :oid';
    $p['oid'] = (int) $g['objet_id'];
}
if (!empty($g['du']) && est_date((string) $g['du'])) {
    $where[] = 't.cree_le >= :du';
    $p['du'] = $g['du'] . ' 00:00:00';
}
if (!empty($g['au']) && est_date((string) $g['au'])) {
    $where[] = 't.cree_le <= :au';
    $p['au'] = $g['au'] . ' 23:59:59';
}
if (($q = trim((string) ($g['q'] ?? ''))) !== '') {
    $where[] = 't.resume LIKE :q';
    $p['q'] = '%' . $q . '%';
}
$requete = ['select' => 't.*', 'from' => 'FROM traces t', 'where' => $where, 'params' => $p,
            'tri' => 't.id', 'cle_tri' => 'id', 'id' => 't.id', 'sens' => 'DESC', 'par_page' => par_page()];

if (!empty($g['pdf'])) {
    exiger_permission('traces.exporter');
    $lignes = tout_lire($requete);
    tracer('export', 'traces', null, 'Export PDF du journal des traces (' . count($lignes) . ' lignes)');
    $pdf = new PdfRapport('Journal des traces', resume_filtres(['utilisateur_id' => 'Utilisateur n°', 'action' => 'Action', 'objet' => 'Objet', 'du' => 'Du', 'au' => 'Au', 'q' => 'Recherche']), 'L');
    $pdf->ajouterPage();
    $pdf->tableau([['label' => 'Date', 'key' => 'date', 'w' => 1.1], ['label' => 'Utilisateur', 'key' => 'identifiant', 'w' => 0.9],
        ['label' => 'Action', 'key' => 'action', 'w' => 0.9], ['label' => 'Objet', 'key' => 'objet', 'w' => 1.1],
        ['label' => 'Description', 'key' => 'resume', 'w' => 4.5], ['label' => 'IP', 'key' => 'adresse_ip', 'w' => 0.9]],
        array_map(static fn($t) => ['date' => fmt_date($t['cree_le'], true), 'identifiant' => $t['identifiant'], 'action' => libelle('action_trace', $t['action']),
            'objet' => $t['objet'] . ($t['objet_id'] ? ' n° ' . $t['objet_id'] : ''), 'resume' => $t['resume'], 'adresse_ip' => $t['adresse_ip']], $lignes), 7.5);
    $pdf->envoyer('traces_' . date('Ymd_His') . '.pdf');
}

$page = paginer($requete);
$couleurs = ['ajout' => 'success', 'modification' => 'primary', 'suppression' => 'danger', 'connexion' => 'info', 'deconnexion' => 'secondary',
             'echec_connexion' => 'warning', 'acces_refuse' => 'warning', 'export' => 'dark', 'calcul' => 'success'];
$titre = 'Traces';
require INC . '/entete.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 titre-page mb-0"><i class="bi bi-journal-text me-2"></i>Traces (journal d'audit)</h1>
    <?= boutons_liste('traces.exporter') ?>
</div>
<div class="card carte-filtres mb-3 d-print-none"><div class="card-body">
    <form method="get" action="traces.php" class="row g-2 align-items-end">
        <div class="col-6 col-md-2"><label class="form-label">Utilisateur</label><select name="utilisateur_id" class="form-select form-select-sm"><?= options(bd_paires('SELECT id, identifiant FROM utilisateurs ORDER BY identifiant'), $g['utilisateur_id'] ?? '', 'Tous') ?></select></div>
        <div class="col-6 col-md-2"><label class="form-label">Action</label><select name="action" class="form-select form-select-sm"><?= options(libelles('action_trace'), $g['action'] ?? '', 'Toutes') ?></select></div>
        <div class="col-6 col-md-2"><label class="form-label">Objet</label><select name="objet" class="form-select form-select-sm"><?php $objets = bd_colonne('SELECT DISTINCT objet FROM traces ORDER BY objet'); ?><?= options(array_combine($objets, $objets) ?: [], $g['objet'] ?? '', 'Tous') ?></select></div>
        <div class="col-6 col-md-1"><label class="form-label">N° objet</label><input name="objet_id" class="form-control form-control-sm" value="<?= e($g['objet_id'] ?? '') ?>"></div>
        <div class="col-6 col-md-1"><label class="form-label">Du</label><input type="date" name="du" class="form-control form-control-sm" value="<?= e($g['du'] ?? '') ?>"></div>
        <div class="col-6 col-md-1"><label class="form-label">Au</label><input type="date" name="au" class="form-control form-control-sm" value="<?= e($g['au'] ?? '') ?>"></div>
        <div class="col-md-2"><label class="form-label">Texte</label><input type="search" name="q" class="form-control form-control-sm" value="<?= e($g['q'] ?? '') ?>"></div>
        <div class="col-md-auto d-flex gap-1"><button class="btn btn-foret btn-sm"><i class="bi bi-search"></i></button><a class="btn btn-outline-secondary btn-sm" href="traces.php"><i class="bi bi-x-lg"></i></a></div>
    </form>
</div></div>
<div class="card"><div class="table-responsive"><table class="table table-sm table-hover mb-0">
    <thead><tr><th>Date</th><th>Utilisateur</th><th>Action</th><th>Objet</th><th>Description</th><th>IP</th><th class="d-print-none"></th></tr></thead>
    <tbody><?php foreach ($page['lignes'] as $t): ?>
        <tr><td class="text-nowrap small"><?= fmt_date($t['cree_le'], true) ?></td><td><?= e($t['identifiant'] ?? '—') ?></td>
            <td><span class="badge text-bg-<?= $couleurs[$t['action']] ?? 'secondary' ?>"><?= e(libelle('action_trace', $t['action'])) ?></span></td>
            <td class="small"><?= e($t['objet']) ?><?= $t['objet_id'] ? ' n° ' . (int) $t['objet_id'] : '' ?></td>
            <td class="small"><?= e($t['resume']) ?></td><td class="small text-muted"><?= e($t['adresse_ip']) ?></td>
            <td class="d-print-none"><?php if ($t['anciennes_valeurs'] || $t['nouvelles_valeurs']): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('trace.php', ['id' => $t['id']])) ?>" title="Avant / après"><i class="bi bi-eye"></i></a><?php endif; ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$page['lignes']): ?><tr><td colspan="7" class="text-center text-muted py-4">Aucune trace.</td></tr><?php endif; ?>
    </tbody>
</table></div></div>
<?= barre_pagination($page) ?>
<?php require INC . '/pied.php';
