<?php $title = 'Résultats des indices'; $g = $_GET; ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 page-title mb-0"><i class="bi bi-table me-2"></i>Résultats enregistrés <span class="badge text-bg-light"><?= fmt_num($total, 0) ?></span></h1>
    <div class="d-flex gap-2 d-print-none">
        <?php if (can('indices.delete') && isset($g['year']) && $g['year'] !== '' && !empty($g['group_type'])): ?>
            <form method="post" action="<?= e(url('indices/delete')) ?>" data-confirm="Supprimer tous les résultats de cette année pour ce regroupement ?">
                <?= csrf_field() ?><input type="hidden" name="year" value="<?= (int) $g['year'] ?>"><input type="hidden" name="group_type" value="<?= e($g['group_type']) ?>">
                <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i>Purger la sélection</button>
            </form>
        <?php endif; ?>
        <?php $exportRoute = 'indices/export'; $exportPerm = 'indices.export'; include __DIR__ . '/../partials/list_actions.php'; ?>
    </div>
</div>
<div class="card filters-card mb-3 d-print-none"><div class="card-body">
    <form method="get" action="<?= e(url()) ?>" class="row g-2 align-items-end">
        <input type="hidden" name="r" value="indices/results">
        <div class="col-6 col-md-2"><label class="form-label">Année</label>
            <select name="year" class="form-select form-select-sm"><option value="">Toutes</option>
                <?php foreach ($years as $y): ?><option value="<?= $y ?>"<?= selected($g['year'] ?? '', $y) ?>><?= $y ?></option><?php endforeach; ?>
                <option value="0"<?= selected($g['year'] ?? '', '0') ?>>Vie entière</option></select></div>
        <div class="col-6 col-md-2"><label class="form-label">Regroupement</label>
            <select name="group_type" class="form-select form-select-sm"><option value="">Tous</option>
                <?php foreach (labels('group_type') as $k => $l): ?><option value="<?= $k ?>"<?= selected($g['group_type'] ?? '', $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label">Indice</label>
            <select name="index_id" class="form-select form-select-sm"><option value="">Tous</option>
                <?php foreach ($defs as $d): ?><option value="<?= $d['id'] ?>"<?= selected($g['index_id'] ?? '', $d['id']) ?>><?= e($d['name']) ?> (<?= e($d['scope']) ?>)</option><?php endforeach; ?></select></div>
        <div class="col-6 col-md-1"><label class="form-label">Niveau</label>
            <select name="level" class="form-select form-select-sm"><option value="">Tous</option>
                <?php foreach (labels('level') as $k => $l): ?><option value="<?= $k ?>"<?= selected($g['level'] ?? '', $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
        <div class="col-6 col-md-2"><label class="form-label">Groupe / animal</label><input name="q" class="form-control form-control-sm" value="<?= e($g['q'] ?? '') ?>" data-animal-search data-all="1"></div>
        <div class="col-6 col-md-1"><label class="form-label">Tri</label>
            <select name="sort" class="form-select form-select-sm"><option value="id">Récent</option><option value="value"<?= selected($g['sort'] ?? '', 'value') ?>>Valeur</option></select></div>
        <div class="col-6 col-md-1"><label class="form-label">Sens</label>
            <select name="dir" class="form-select form-select-sm"><option value="desc">Décroissant</option><option value="asc"<?= selected($g['dir'] ?? '', 'asc') ?>>Croissant</option></select></div>
        <div class="col-12 col-md-auto d-flex gap-1"><button class="btn btn-forest btn-sm"><i class="bi bi-search"></i></button>
            <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('indices/results')) ?>"><i class="bi bi-x-lg"></i></a></div>
    </form>
</div></div>
<div class="card"><div class="table-responsive">
    <table class="table table-sm table-hover mb-0">
        <thead><tr><th>Année</th><th>Regroupement</th><th>Groupe / animal</th><th>Indice</th><th class="text-end">Valeur</th><th>Niveau</th><th>Interprétation</th><th class="table-actions d-print-none"></th></tr></thead>
        <tbody>
        <?php foreach ($page['rows'] as $r): ?>
            <tr class="level-<?= e($r['level']) ?>">
                <td><?= $r['year'] ?: 'Vie' ?></td><td class="small"><?= e(label('group_type', $r['group_type'])) ?></td>
                <td><?= $r['animal_id'] ? '<a href="' . e(url('animals/show', ['id' => $r['animal_id'], 'tab' => 'indices'])) . '">' . e($r['group_label']) . '</a>' : e($r['group_label']) ?></td>
                <td><?= e($r['name']) ?></td>
                <td class="text-end fw-semibold text-nowrap"><?= $r['value'] !== null ? fmt_num($r['value'], (int) $r['decimals']) . ' ' . e($r['unit']) : 'N/D' ?></td>
                <td><?= level_badge($r['level']) ?></td><td class="small"><?= e($r['interpretation']) ?></td>
                <td class="table-actions d-print-none">
                    <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('indices/show', ['id' => $r['id']])) ?>" title="Détail"><i class="bi bi-info-circle"></i></a>
                    <?php if (can('indices.delete')): $action = url('indices/delete'); $id = $r['id']; $message = 'Supprimer ce résultat ?'; include __DIR__ . '/../partials/delete_button.php'; endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$page['rows']): ?><tr><td colspan="8" class="text-center text-muted py-4">Aucun résultat.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div></div>
<?php include __DIR__ . '/../partials/pager.php'; ?>
