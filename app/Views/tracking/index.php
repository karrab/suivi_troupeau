<?php
use App\Services\Tracking;

$g = $_GET;
$title = $cfg['title'];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 page-title mb-0"><i class="bi <?= $cfg['icon'] ?> me-2"></i><?= e($cfg['title']) ?> <span class="badge text-bg-light"><?= fmt_num($total, 0) ?></span></h1>
    <div class="d-flex gap-2 d-print-none">
        <?php $exportRoute = 'tracking/export'; $exportPerm = "$type.export"; include __DIR__ . '/../partials/list_actions.php'; ?>
        <?php if (can("$type.create")): ?><a href="<?= e(url('tracking/create', ['type' => $type])) ?>" class="btn btn-forest btn-sm"><i class="bi bi-plus-lg me-1"></i>Ajouter</a><?php endif; ?>
    </div>
</div>

<div class="card filters-card mb-3 d-print-none"><div class="card-body">
    <form method="get" action="<?= e(url()) ?>" class="row g-2 align-items-end">
        <input type="hidden" name="r" value="tracking"><input type="hidden" name="type" value="<?= e($type) ?>">
        <div class="col-md-2"><label class="form-label">Animal (n° boucle)</label>
            <input type="search" name="animal" class="form-control form-control-sm" value="<?= e($g['animal'] ?? '') ?>" data-animal-search data-all="1"></div>
        <div class="col-6 col-md-2"><label class="form-label">Du</label><input type="date" name="from" class="form-control form-control-sm" value="<?= e($g['from'] ?? '') ?>"></div>
        <div class="col-6 col-md-2"><label class="form-label">Au</label><input type="date" name="to" class="form-control form-control-sm" value="<?= e($g['to'] ?? '') ?>"></div>
        <div class="col-6 col-md-1"><label class="form-label">Espèce</label>
            <select name="species" class="form-select form-select-sm"><option value="">Toutes</option>
                <?php foreach (labels('species') as $k => $l): ?><option value="<?= $k ?>"<?= selected($g['species'] ?? '', $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
        <div class="col-6 col-md-1"><label class="form-label">Lot</label>
            <select name="lot_id" class="form-select form-select-sm"><option value="">Tous</option>
                <?php foreach ($lots as $l): ?><option value="<?= $l['id'] ?>"<?= selected($g['lot_id'] ?? '', $l['id']) ?>><?= e($l['name']) ?></option><?php endforeach; ?></select></div>
        <?php foreach ($cfg['filters'] as $f): $def = $cfg['fields'][$f]; ?>
            <div class="col-6 col-md-1"><label class="form-label"><?= e($def['label']) ?></label>
                <select name="<?= $f ?>" class="form-select form-select-sm"><option value="">Tous</option>
                    <?php foreach (labels($def['options']) as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($g[$f] ?? '', $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
        <?php endforeach; ?>
        <?php if ($cfg['search']): ?>
            <div class="col-md-2"><label class="form-label">Texte</label><input type="search" name="q" class="form-control form-control-sm" value="<?= e($g['q'] ?? '') ?>" placeholder="Diagnostic, produit..."></div>
        <?php endif; ?>
        <div class="col-6 col-md-1"><label class="form-label">Ordre</label>
            <select name="dir" class="form-select form-select-sm"><option value="desc">Récent</option><option value="asc"<?= selected($g['dir'] ?? '', 'asc') ?>>Ancien</option></select></div>
        <div class="col-md-auto d-flex gap-1">
            <button class="btn btn-forest btn-sm"><i class="bi bi-search me-1"></i>Filtrer</button>
            <a href="<?= e(url('tracking', ['type' => $type])) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-lg"></i></a>
        </div>
    </form>
</div></div>

<div class="print-only mb-2"><strong><?= e($cfg['title']) ?></strong> — imprimé le <?= date('d/m/Y H:i') ?></div>
<div class="card"><div class="table-responsive">
    <table class="table table-hover table-sm mb-0">
        <thead><tr>
            <?php foreach ($cfg['list'] as $f): ?><th class="<?= $cfg['fields'][$f]['type'] === 'number' ? 'text-end' : '' ?>"><?= e($cfg['fields'][$f]['label']) ?></th><?php endforeach; ?>
            <th class="table-actions d-print-none"></th>
        </tr></thead>
        <tbody>
        <?php foreach ($page['rows'] as $r): ?>
            <tr>
                <?php foreach ($cfg['list'] as $f): ?>
                    <td class="<?= $cfg['fields'][$f]['type'] === 'number' ? 'text-end' : '' ?>"><?= Tracking::format($cfg, $f, $r) ?></td>
                <?php endforeach; ?>
                <td class="table-actions d-print-none">
                    <?php if (can("$type.edit")): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('tracking/edit', ['type' => $type, 'id' => $r['id']])) ?>" title="Modifier"><i class="bi bi-pencil"></i></a><?php endif; ?>
                    <?php if (can("$type.export")): ?><a class="btn btn-sm btn-outline-danger" target="_blank" href="<?= e(url('tracking/pdf', ['type' => $type, 'id' => $r['id']])) ?>" title="PDF"><i class="bi bi-file-earmark-pdf"></i></a><?php endif; ?>
                    <?php if (can("$type.delete")): ?>
                        <?php $action = url('tracking/delete'); $id = $r['id']; $message = 'Supprimer cet enregistrement (' . $r['animal_tag'] . ', ' . fmt_date($r['event_date']) . ') ?'; $extra = ['type' => $type]; include __DIR__ . '/../partials/delete_button.php'; ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$page['rows']): ?><tr><td colspan="<?= count($cfg['list']) + 1 ?>" class="text-center text-muted py-4">Aucun enregistrement.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div></div>
<?php include __DIR__ . '/../partials/pager.php'; ?>
