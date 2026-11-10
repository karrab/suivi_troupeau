<?php $title = 'Trace #' . $log['id']; $keys = array_unique(array_merge(array_keys($old), array_keys($new))); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 page-title mb-0"><i class="bi bi-journal-text me-2"></i>Trace n° <?= (int) $log['id'] ?></h1>
    <div class="d-print-none"><button class="btn btn-outline-secondary btn-sm" data-print><i class="bi bi-printer me-1"></i>Imprimer</button>
        <a href="<?= e(url('audit')) ?>" class="btn btn-outline-secondary btn-sm">Retour</a></div>
</div>
<div class="card mb-3"><div class="card-body"><dl class="row mb-0 small">
    <dt class="col-sm-2">Date</dt><dd class="col-sm-10"><?= fmt_date($log['created_at'], true) ?></dd>
    <dt class="col-sm-2">Utilisateur</dt><dd class="col-sm-10"><?= e($log['username'] ?? '—') ?></dd>
    <dt class="col-sm-2">Action</dt><dd class="col-sm-10"><?= e(label('audit_action', $log['action'])) ?></dd>
    <dt class="col-sm-2">Objet</dt><dd class="col-sm-10"><?= e($log['entity']) ?> <?= $log['entity_id'] ? '#' . (int) $log['entity_id'] : '' ?>
        <?php if ($log['entity'] === 'animal' && $log['entity_id'] && $log['action'] !== 'delete'): ?> — <a href="<?= e(url('animals/show', ['id' => $log['entity_id']])) ?>">voir la fiche</a><?php endif; ?></dd>
    <dt class="col-sm-2">Description</dt><dd class="col-sm-10"><?= e($log['summary']) ?></dd>
    <dt class="col-sm-2">IP / navigateur</dt><dd class="col-sm-10"><?= e($log['ip_address']) ?> — <span class="text-muted"><?= e($log['user_agent']) ?></span></dd>
</dl></div></div>
<div class="card"><div class="card-header">Valeurs modifiées</div><div class="table-responsive"><table class="table table-sm mb-0">
    <thead><tr><th>Champ</th><th>Avant</th><th>Après</th></tr></thead>
    <tbody><?php foreach ($keys as $k): ?>
        <tr><td><code><?= e($k) ?></code></td>
            <td class="text-danger"><?= e(is_array($old[$k] ?? null) ? json_encode($old[$k], JSON_UNESCAPED_UNICODE) : ($old[$k] ?? '')) ?></td>
            <td class="text-success"><?= e(is_array($new[$k] ?? null) ? json_encode($new[$k], JSON_UNESCAPED_UNICODE) : ($new[$k] ?? '')) ?></td></tr>
    <?php endforeach; ?></tbody>
</table></div></div>
