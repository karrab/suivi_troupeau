<?php $title = 'Traces'; $g = $_GET;
$colors = ['create' => 'success', 'update' => 'primary', 'delete' => 'danger', 'login' => 'info', 'logout' => 'secondary', 'login_failed' => 'warning', 'forbidden' => 'warning', 'export' => 'dark', 'compute' => 'success'];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 page-title mb-0"><i class="bi bi-journal-text me-2"></i>Traces (journal d'audit)</h1>
    <?php $exportRoute = 'audit/export'; $exportPerm = 'audit.export'; include __DIR__ . '/../partials/list_actions.php'; ?>
</div>
<div class="card filters-card mb-3 d-print-none"><div class="card-body">
    <form method="get" action="<?= e(url()) ?>" class="row g-2 align-items-end">
        <input type="hidden" name="r" value="audit">
        <div class="col-6 col-md-2"><label class="form-label">Utilisateur</label><select name="user_id" class="form-select form-select-sm"><option value="">Tous</option>
            <?php foreach ($users as $id => $n): ?><option value="<?= $id ?>"<?= selected($g['user_id'] ?? '', $id) ?>><?= e($n) ?></option><?php endforeach; ?></select></div>
        <div class="col-6 col-md-2"><label class="form-label">Action</label><select name="action" class="form-select form-select-sm"><option value="">Toutes</option>
            <?php foreach (labels('audit_action') as $k => $l): ?><option value="<?= $k ?>"<?= selected($g['action'] ?? '', $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
        <div class="col-6 col-md-2"><label class="form-label">Objet</label><select name="entity" class="form-select form-select-sm"><option value="">Tous</option>
            <?php foreach ($entities as $en): ?><option value="<?= e($en) ?>"<?= selected($g['entity'] ?? '', $en) ?>><?= e($en) ?></option><?php endforeach; ?></select></div>
        <div class="col-6 col-md-1"><label class="form-label">N° objet</label><input name="entity_id" class="form-control form-control-sm" value="<?= e($g['entity_id'] ?? '') ?>"></div>
        <div class="col-6 col-md-1"><label class="form-label">Du</label><input type="date" name="from" class="form-control form-control-sm" value="<?= e($g['from'] ?? '') ?>"></div>
        <div class="col-6 col-md-1"><label class="form-label">Au</label><input type="date" name="to" class="form-control form-control-sm" value="<?= e($g['to'] ?? '') ?>"></div>
        <div class="col-md-2"><label class="form-label">Texte</label><input type="search" name="q" class="form-control form-control-sm" value="<?= e($g['q'] ?? '') ?>"></div>
        <div class="col-md-auto d-flex gap-1"><button class="btn btn-forest btn-sm"><i class="bi bi-search"></i></button><a class="btn btn-outline-secondary btn-sm" href="<?= e(url('audit')) ?>"><i class="bi bi-x-lg"></i></a></div>
    </form>
</div></div>
<div class="card"><div class="table-responsive"><table class="table table-sm table-hover mb-0">
    <thead><tr><th>Date</th><th>Utilisateur</th><th>Action</th><th>Objet</th><th>Description</th><th>IP</th><th class="d-print-none"></th></tr></thead>
    <tbody><?php foreach ($page['rows'] as $l): ?>
        <tr><td class="text-nowrap small"><?= fmt_date($l['created_at'], true) ?></td><td><?= e($l['username'] ?? '—') ?></td>
            <td><span class="badge text-bg-<?= $colors[$l['action']] ?? 'secondary' ?>"><?= e(label('audit_action', $l['action'])) ?></span></td>
            <td class="small"><?= e($l['entity']) ?><?= $l['entity_id'] ? ' #' . (int) $l['entity_id'] : '' ?></td>
            <td class="small"><?= e($l['summary']) ?></td><td class="small text-muted"><?= e($l['ip_address']) ?></td>
            <td class="d-print-none"><?php if ($l['old_values'] || $l['new_values']): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('audit/show', ['id' => $l['id']])) ?>" title="Détail avant / après"><i class="bi bi-eye"></i></a><?php endif; ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$page['rows']): ?><tr><td colspan="7" class="text-center text-muted py-4">Aucune trace.</td></tr><?php endif; ?>
    </tbody>
</table></div></div>
<?php include __DIR__ . '/../partials/pager.php'; ?>
