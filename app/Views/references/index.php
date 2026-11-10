<?php $title = $cfg['title']; $err = $errors; $obj = $edit ?? []; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 page-title mb-0"><i class="bi bi-tags me-2"></i>Référentiels</h1>
    <button class="btn btn-outline-secondary btn-sm d-print-none" data-print><i class="bi bi-printer me-1"></i>Imprimer</button>
</div>
<ul class="nav nav-pills mb-3 d-print-none">
    <?php foreach ($types as $k => $tc): ?><li class="nav-item"><a class="nav-link<?= $k === $t ? ' active bg-forest' : '' ?>" href="<?= e(url('references', ['type' => $k])) ?>"><?= e($tc['title']) ?></a></li><?php endforeach; ?>
</ul>
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card"><table class="table table-sm table-hover mb-0">
            <thead><tr><?php foreach ($cfg['fields'] as $f => [$l]): ?><th><?= e($l) ?></th><?php endforeach; ?><th class="text-end">Animaux actifs</th><th class="table-actions d-print-none"></th></tr></thead>
            <tbody><?php foreach ($rows as $r): ?>
                <tr><?php foreach ($cfg['fields'] as $f => $_): ?><td><?= e($f === 'species' ? label('species', $r[$f]) : $r[$f]) ?></td><?php endforeach; ?>
                    <td class="text-end"><a href="<?= e(url('animals', [$cfg['fk'] => $r['id']])) ?>"><?= (int) $r['nb'] ?></a></td>
                    <td class="table-actions d-print-none"><?php if (can('references.manage')): ?>
                        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('references', ['type' => $t, 'edit' => $r['id']])) ?>"><i class="bi bi-pencil"></i></a>
                        <?php $action = url('references/delete'); $id = $r['id']; $message = 'Supprimer « ' . $r['name'] . ' » ? Les animaux concernés n\'auront plus de ' . $cfg['singular'] . '.'; $extra = ['type' => $t]; include __DIR__ . '/../partials/delete_button.php'; ?>
                    <?php endif; ?></td></tr>
            <?php endforeach; ?></tbody>
        </table></div>
    </div>
    <?php if (can('references.manage')): ?>
    <div class="col-lg-4 d-print-none">
        <div class="card"><div class="card-header"><?= $edit ? 'Modifier' : 'Ajouter' ?> : <?= e($cfg['singular']) ?></div><div class="card-body">
            <form method="post" action="<?= e(url('references/save')) ?>">
                <?= csrf_field() ?><input type="hidden" name="type" value="<?= e($t) ?>">
                <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
                <?php foreach ($cfg['fields'] as $f => [$l, $rule]): ?>
                    <div class="mb-2"><label class="form-label small"><?= e($l) ?><?= str_contains($rule, 'required') ? ' *' : '' ?></label>
                        <?php if ($f === 'species'): ?>
                            <select name="species" class="form-select form-select-sm"><?php foreach (labels('species') as $k => $sl): ?><option value="<?= $k ?>"<?= selected(fv($obj, $f), $k) ?>><?= e($sl) ?></option><?php endforeach; ?></select>
                        <?php else: ?>
                            <input name="<?= $f ?>" class="form-control form-control-sm<?= invalid($err, $f) ?>" value="<?= e(fv($obj, $f)) ?>">
                        <?php endif; ?><?= field_error($err, $f) ?></div>
                <?php endforeach; ?>
                <button class="btn btn-forest btn-sm"><i class="bi bi-check2 me-1"></i>Enregistrer</button>
                <?php if ($edit): ?><a class="btn btn-outline-secondary btn-sm" href="<?= e(url('references', ['type' => $t])) ?>">Annuler</a><?php endif; ?>
            </form>
        </div></div>
    </div>
    <?php endif; ?>
</div>
