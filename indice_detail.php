<?php
/** Détail d'un résultat d'indice : valeur, interprétation, formule et variables utilisées. */
require __DIR__ . '/inc/init.php';
require INC . '/indices.php';
exiger_permission('indices.voir');

$r = bd_un('SELECT r.*, i.nom, i.unite, i.decimales, i.portee, i.formule AS formule_actuelle, i.seuil_bas, i.seuil_haut, i.sens,
                   u.nom_complet AS calcule_par_nom
            FROM resultats_indices r JOIN indices i ON i.id = r.indice_id LEFT JOIN utilisateurs u ON u.id = r.calcule_par
            WHERE r.id = :id', ['id' => entier_get('id')]) ?? abandonner(404, 'Résultat introuvable.');
$vars = json_decode((string) $r['variables'], true) ?: [];
$catalogue = variables_portee($r['portee']);
$dec = (int) $r['decimales'];

$titre = 'Détail d\'un indice';
require INC . '/entete.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 titre-page mb-0"><i class="bi bi-info-circle me-2"></i><?= e($r['nom']) ?> — <?= $r['annee'] ?: 'vie entière' ?></h1>
    <div class="d-print-none"><button class="btn btn-outline-secondary btn-sm" data-imprimer><i class="bi bi-printer me-1"></i>Imprimer</button>
        <a href="<?= e(url('indices_resultats.php', ['annee' => $r['annee'], 'type_groupe' => $r['type_groupe']])) ?>" class="btn btn-outline-secondary btn-sm">Retour</a></div>
</div>
<div class="row g-3">
    <div class="col-lg-5"><div class="card niveau-<?= e($r['niveau']) ?>"><div class="card-body">
        <div class="text-muted small"><?= e(libelle('type_groupe', $r['type_groupe'])) ?> : <?= e($r['libelle_groupe']) ?></div>
        <div class="display-6 fw-bold text-foret my-2"><?= $r['valeur'] !== null ? fmt_nombre($r['valeur'], $dec) : 'N/D' ?> <small class="fs-5 text-muted"><?= e($r['unite']) ?></small></div>
        <div class="mb-2"><?= badge_niveau($r['niveau']) ?></div>
        <p class="mb-2"><?= e($r['interpretation']) ?></p>
        <dl class="row small mb-0">
            <dt class="col-5">Seuils</dt><dd class="col-7">bas <?= $r['seuil_bas'] !== null ? fmt_nombre($r['seuil_bas'], $dec) : '—' ?> / haut <?= $r['seuil_haut'] !== null ? fmt_nombre($r['seuil_haut'], $dec) : '—' ?> (<?= e(libelle('sens', $r['sens'])) ?>)</dd>
            <dt class="col-5">Calculé le</dt><dd class="col-7"><?= fmt_date($r['calcule_le'], true) ?> <?= $r['calcule_par_nom'] ? 'par ' . e($r['calcule_par_nom']) : '' ?></dd>
        </dl>
    </div></div></div>
    <div class="col-lg-7"><div class="card"><div class="card-header">Formule appliquée</div><div class="card-body">
        <code class="formule d-block p-2 bg-light rounded"><?= e($r['formule_utilisee']) ?></code>
        <?php if ($r['formule_utilisee'] !== $r['formule_actuelle']): ?>
            <div class="alert alert-warning small mt-2 mb-0"><i class="bi bi-exclamation-triangle me-1"></i>La formule a changé depuis ce calcul. Formule actuelle : <code><?= e($r['formule_actuelle']) ?></code>. Relancez le calcul pour mettre le résultat à jour.</div>
        <?php endif; ?>
        <h2 class="h6 mt-3">Variables utilisées</h2>
        <table class="table table-sm mb-0"><?php foreach ($vars as $k => $v): ?>
            <tr><td><code><?= e($k) ?></code></td><td class="small text-muted"><?= e($catalogue[$k] ?? '') ?></td><td class="text-end fw-semibold"><?= $v === null ? 'N/D' : fmt_nombre($v, 2) ?></td></tr>
        <?php endforeach; ?></table>
    </div></div></div>
</div>
<?php require INC . '/pied.php';
