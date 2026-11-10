<?php
/** Détail d'une trace : valeurs avant / après. */
require __DIR__ . '/inc/init.php';
exiger_permission('traces.voir');

$t = bd_trouver('traces', entier_get('id')) ?? abandonner(404, 'Trace introuvable.');
$avant = json_decode((string) $t['anciennes_valeurs'], true) ?: [];
$apres = json_decode((string) $t['nouvelles_valeurs'], true) ?: [];
$champs = array_unique(array_merge(array_keys($avant), array_keys($apres)));
$texte = static fn($v) => is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (string) ($v ?? '');
$titre = 'Trace n° ' . $t['id'];
require INC . '/entete.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 titre-page mb-0"><i class="bi bi-journal-text me-2"></i>Trace n° <?= (int) $t['id'] ?></h1>
    <div class="d-print-none"><button class="btn btn-outline-secondary btn-sm" data-imprimer><i class="bi bi-printer me-1"></i>Imprimer</button> <a href="traces.php" class="btn btn-outline-secondary btn-sm">Retour</a></div>
</div>
<div class="card mb-3"><div class="card-body"><dl class="row mb-0 small">
    <dt class="col-sm-2">Date</dt><dd class="col-sm-10"><?= fmt_date($t['cree_le'], true) ?></dd>
    <dt class="col-sm-2">Utilisateur</dt><dd class="col-sm-10"><?= e($t['identifiant'] ?? '—') ?></dd>
    <dt class="col-sm-2">Action</dt><dd class="col-sm-10"><?= e(libelle('action_trace', $t['action'])) ?></dd>
    <dt class="col-sm-2">Objet</dt><dd class="col-sm-10"><?= e($t['objet']) ?> <?= $t['objet_id'] ? 'n° ' . (int) $t['objet_id'] : '' ?>
        <?php if ($t['objet'] === 'animal' && $t['objet_id'] && $t['action'] !== 'suppression'): ?> — <a href="<?= e(url('animal.php', ['id' => $t['objet_id']])) ?>">voir la fiche</a><?php endif; ?></dd>
    <dt class="col-sm-2">Description</dt><dd class="col-sm-10"><?= e($t['resume']) ?></dd>
    <dt class="col-sm-2">IP / navigateur</dt><dd class="col-sm-10"><?= e($t['adresse_ip']) ?> — <span class="text-muted"><?= e($t['navigateur']) ?></span></dd>
</dl></div></div>
<div class="card"><div class="card-header">Valeurs modifiées</div><div class="table-responsive"><table class="table table-sm mb-0">
    <thead><tr><th>Champ</th><th>Avant</th><th>Après</th></tr></thead>
    <tbody><?php foreach ($champs as $c): ?>
        <tr><td><code><?= e($c) ?></code></td><td class="text-danger"><?= e($texte($avant[$c] ?? null)) ?></td><td class="text-success"><?= e($texte($apres[$c] ?? null)) ?></td></tr>
    <?php endforeach; ?></tbody>
</table></div></div>
<?php require INC . '/pied.php';
