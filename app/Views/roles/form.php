<?php
$isEdit = !empty($role['id']);
$title = $isEdit ? 'Rôle ' . $role['name'] : 'Nouveau rôle';
$err = $errors;
$allActions = [];
foreach ($catalog as $m) {
    $allActions += $m['actions'];
}
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 page-title mb-0"><i class="bi bi-shield-lock me-2"></i><?= e($title) ?></h1>
    <a href="<?= e(url('roles')) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Retour</a>
</div>
<form method="post" action="<?= e(url('roles/save')) ?>">
    <?= csrf_field() ?>
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $role['id'] ?>"><?php endif; ?>
    <div class="card mb-3"><div class="card-body row g-3">
        <div class="col-md-4"><label class="form-label">Nom *</label><input name="name" class="form-control<?= invalid($err, 'name') ?>" value="<?= e(fv($role, 'name')) ?>" required><?= field_error($err, 'name') ?></div>
        <div class="col-md-6"><label class="form-label">Description</label><input name="description" class="form-control" value="<?= e(fv($role, 'description')) ?>"></div>
        <div class="col-md-2 d-flex align-items-end"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_super" value="1" id="sup"<?= checked(fv($role, 'is_super')) ?>>
            <label class="form-check-label" for="sup">Accès complet</label></div></div>
        <?= field_error($err, 'is_super') ?>
    </div></div>
    <div class="card"><div class="card-header">Matrice des permissions <span class="small text-muted fw-normal">(ignorée si « accès complet »)</span></div>
        <div class="table-responsive"><table class="table table-sm table-hover mb-0 align-middle">
            <thead><tr><th>Module</th><th class="text-center">Tout</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($catalog as $module => $m): ?>
                <tr><td class="fw-semibold"><?= e($m['label']) ?></td>
                    <td class="text-center"><input type="checkbox" class="form-check-input" data-check-row="<?= e($module) ?>" aria-label="Tout cocher"></td>
                    <td><?php foreach ($m['actions'] as $action => $lbl): $code = "$module.$action"; ?>
                        <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="perms[]" value="<?= e($code) ?>" id="p_<?= e($code) ?>" data-module="<?= e($module) ?>"<?= checked(isset($granted[$code])) ?>>
                            <label class="form-check-label small" for="p_<?= e($code) ?>"><?= e($lbl) ?></label></div>
                    <?php endforeach; ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
    <button class="btn btn-forest mt-3"><i class="bi bi-check2 me-1"></i>Enregistrer</button>
</form>
