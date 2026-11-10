<?php $title = 'Détail d\'un indice'; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 page-title mb-0"><i class="bi bi-info-circle me-2"></i><?= e($r['name']) ?> — <?= $r['year'] ?: 'vie entière' ?></h1>
    <div class="d-print-none"><button class="btn btn-outline-secondary btn-sm" data-print><i class="bi bi-printer me-1"></i>Imprimer</button>
        <a href="<?= e(url('indices/results', ['year' => $r['year'], 'group_type' => $r['group_type']])) ?>" class="btn btn-outline-secondary btn-sm">Retour</a></div>
</div>
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card level-<?= e($r['level']) ?>"><div class="card-body">
            <div class="text-muted small"><?= e(label('group_type', $r['group_type'])) ?> : <?= e($r['group_label']) ?></div>
            <div class="display-6 fw-bold text-forest my-2"><?= $r['value'] !== null ? fmt_num($r['value'], (int) $r['decimals']) : 'N/D' ?> <small class="fs-5 text-muted"><?= e($r['unit']) ?></small></div>
            <div class="mb-2"><?= level_badge($r['level']) ?></div>
            <p class="mb-2"><?= e($r['interpretation']) ?></p>
            <dl class="row small mb-0">
                <dt class="col-5">Seuils</dt><dd class="col-7">bas <?= $r['threshold_low'] !== null ? fmt_num($r['threshold_low'], (int) $r['decimals']) : '—' ?> / haut <?= $r['threshold_high'] !== null ? fmt_num($r['threshold_high'], (int) $r['decimals']) : '—' ?> (<?= e(label('better', $r['better'])) ?>)</dd>
                <dt class="col-5">Calculé le</dt><dd class="col-7"><?= fmt_date($r['computed_at'], true) ?> <?= $r['computed_by_name'] ? 'par ' . e($r['computed_by_name']) : '' ?></dd>
            </dl>
        </div></div>
    </div>
    <div class="col-lg-7">
        <div class="card"><div class="card-header">Formule appliquée</div><div class="card-body">
            <code class="formula d-block p-2 bg-light rounded"><?= e($r['formula_used']) ?></code>
            <?php if ($r['formula_used'] !== $r['current_formula']): ?>
                <div class="alert alert-warning small mt-2 mb-0"><i class="bi bi-exclamation-triangle me-1"></i>La formule a été modifiée depuis ce calcul. Formule actuelle : <code><?= e($r['current_formula']) ?></code>. Relancez le calcul pour mettre à jour.</div>
            <?php endif; ?>
            <h2 class="h6 mt-3">Variables utilisées</h2>
            <table class="table table-sm mb-0">
                <?php foreach ($vars as $k => $v): ?>
                    <tr><td><code><?= e($k) ?></code></td><td class="small text-muted"><?= e($catalog[$k] ?? '') ?></td><td class="text-end fw-semibold"><?= $v === null ? 'N/D' : fmt_num($v, 2) ?></td></tr>
                <?php endforeach; ?>
            </table>
        </div></div>
    </div>
</div>
