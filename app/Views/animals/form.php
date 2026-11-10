<?php
$a = $animal;
$title = $isEdit ? 'Modifier ' . ($a['tag'] ?? '') : 'Nouvel animal';
$err = $errors;
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 page-title mb-0"><i class="bi bi-<?= $isEdit ? 'pencil' : 'plus-circle' ?> me-2"></i><?= e($title) ?></h1>
    <a href="<?= e($isEdit ? url('animals/show', ['id' => $a['id']]) : url('animals')) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Retour</a>
</div>
<?php if ($err): ?><div class="alert alert-danger">Veuillez corriger les erreurs signalées.</div><?php endif; ?>

<form method="post" action="<?= e(url($isEdit ? 'animals/update' : 'animals/store')) ?>" novalidate>
    <?= csrf_field() ?>
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><?php endif; ?>
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card h-100"><div class="card-header">Identification</div><div class="card-body row g-2">
                <div class="col-md-6"><label class="form-label">N° boucle *</label>
                    <input name="tag" class="form-control<?= invalid($err, 'tag') ?>" value="<?= e(fv($a, 'tag')) ?>" required maxlength="30"><?= field_error($err, 'tag') ?></div>
                <div class="col-md-6"><label class="form-label">Nom</label>
                    <input name="name" class="form-control<?= invalid($err, 'name') ?>" value="<?= e(fv($a, 'name')) ?>" maxlength="80"><?= field_error($err, 'name') ?></div>
                <div class="col-md-6"><label class="form-label">Catégorie * <span class="small text-muted">(détermine espèce et sexe)</span></label>
                    <select name="category_id" class="form-select<?= invalid($err, 'category_id') ?>" required>
                        <option value="">—</option>
                        <?php foreach (['caprin' => 'Caprins', 'ovin' => 'Ovins'] as $sp => $spl): ?>
                            <optgroup label="<?= $spl ?>">
                                <?php foreach ($categories as $c): if ($c['species'] !== $sp) continue; ?>
                                    <option value="<?= $c['id'] ?>"<?= selected(fv($a, 'category_id'), $c['id']) ?>><?= e($c['label']) ?> (<?= $c['sex'] ?>)</option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select><?= field_error($err, 'category_id') ?></div>
                <div class="col-md-6"><label class="form-label">Race</label>
                    <select name="breed_id" class="form-select<?= invalid($err, 'breed_id') ?>"><option value="">—</option>
                        <?php foreach ($breeds as $b): ?><option value="<?= $b['id'] ?>"<?= selected(fv($a, 'breed_id'), $b['id']) ?>><?= e($b['name']) ?> (<?= e(label('species', $b['species'])) ?>)</option><?php endforeach; ?>
                    </select><?= field_error($err, 'breed_id') ?></div>
                <div class="col-md-6"><label class="form-label">Robe / couleur</label>
                    <input name="color" class="form-control" value="<?= e(fv($a, 'color')) ?>" maxlength="60"></div>
                <div class="col-md-6"><label class="form-label">Lot</label>
                    <select name="lot_id" class="form-select"><option value="">—</option>
                        <?php foreach ($lots as $l): ?><option value="<?= $l['id'] ?>"<?= selected(fv($a, 'lot_id'), $l['id']) ?>><?= e($l['name']) ?></option><?php endforeach; ?>
                    </select></div>
            </div></div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100"><div class="card-header">Naissance & généalogie</div><div class="card-body row g-2">
                <div class="col-md-6"><label class="form-label">Date de naissance *</label>
                    <input type="date" name="birth_date" class="form-control<?= invalid($err, 'birth_date') ?>" value="<?= e(fv($a, 'birth_date')) ?>" max="<?= date('Y-m-d') ?>" required><?= field_error($err, 'birth_date') ?>
                    <div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="birth_date_estimated" value="1" id="bde"<?= checked(fv($a, 'birth_date_estimated')) ?>><label class="form-check-label small" for="bde">Date estimée</label></div></div>
                <div class="col-md-3"><label class="form-label">Poids naiss. (kg)</label>
                    <input type="number" step="0.01" min="0" name="birth_weight" class="form-control<?= invalid($err, 'birth_weight') ?>" value="<?= e(fv($a, 'birth_weight')) ?>"><?= field_error($err, 'birth_weight') ?></div>
                <div class="col-md-3"><label class="form-label">Type</label>
                    <select name="birth_type" class="form-select"><option value="">—</option>
                        <?php foreach (labels('birth_type') as $k => $l): ?><option value="<?= $k ?>"<?= selected(fv($a, 'birth_type'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-6"><label class="form-label">Mère (n° boucle)</label>
                    <input name="mother_tag" class="form-control<?= invalid($err, 'mother_tag') ?>" value="<?= e(fv($a, 'mother_tag')) ?>" data-animal-search data-sex="F" data-all="1"><?= field_error($err, 'mother_tag') ?></div>
                <div class="col-md-6"><label class="form-label">Père (n° boucle)</label>
                    <input name="father_tag" class="form-control<?= invalid($err, 'father_tag') ?>" value="<?= e(fv($a, 'father_tag')) ?>" data-animal-search data-sex="M" data-all="1"><?= field_error($err, 'father_tag') ?></div>
            </div></div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100"><div class="card-header">Entrée</div><div class="card-body row g-2">
                <div class="col-md-6"><label class="form-label">Origine *</label>
                    <select name="origin" id="origin" class="form-select">
                        <?php foreach (labels('origin') as $k => $l): ?><option value="<?= $k ?>"<?= selected(fv($a, 'origin'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-6"><label class="form-label">Date d'entrée *</label>
                    <input type="date" name="entry_date" class="form-control<?= invalid($err, 'entry_date') ?>" value="<?= e(fv($a, 'entry_date')) ?>" required><?= field_error($err, 'entry_date') ?></div>
                <div class="col-md-6 purchase-fields"><label class="form-label">Prix d'achat (<?= e(setting('devise', 'DH')) ?>)</label>
                    <input type="number" step="0.01" min="0" name="purchase_price" class="form-control" value="<?= e(fv($a, 'purchase_price')) ?>"></div>
            </div></div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100"><div class="card-header">Statut</div><div class="card-body row g-2">
                <div class="col-md-6"><label class="form-label">Statut *</label>
                    <select name="status" id="status" class="form-select">
                        <?php foreach (labels('status') as $k => $l): ?><option value="<?= $k ?>"<?= selected(fv($a, 'status'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-6 exit-fields"><label class="form-label">Date de sortie *</label>
                    <input type="date" name="exit_date" class="form-control<?= invalid($err, 'exit_date') ?>" value="<?= e(fv($a, 'exit_date')) ?>"><?= field_error($err, 'exit_date') ?></div>
                <div class="col-md-8 exit-fields"><label class="form-label">Motif de sortie</label>
                    <input name="exit_reason" class="form-control" value="<?= e(fv($a, 'exit_reason')) ?>" maxlength="160"></div>
                <div class="col-md-4 exit-fields"><label class="form-label">Prix de vente</label>
                    <input type="number" step="0.01" min="0" name="sale_price" class="form-control" value="<?= e(fv($a, 'sale_price')) ?>"></div>
            </div></div>
        </div>
        <div class="col-12">
            <div class="card"><div class="card-body">
                <label class="form-label">Remarques</label>
                <textarea name="notes" rows="3" class="form-control"><?= e(fv($a, 'notes')) ?></textarea>
            </div></div>
        </div>
    </div>
    <div class="mt-3 d-flex gap-2">
        <button class="btn btn-forest"><i class="bi bi-check2 me-1"></i>Enregistrer</button>
        <?php if (!$isEdit): ?><button class="btn btn-outline-success" name="save_new" value="1"><i class="bi bi-plus me-1"></i>Enregistrer et nouveau</button><?php endif; ?>
        <a href="<?= e(url('animals')) ?>" class="btn btn-outline-secondary">Annuler</a>
    </div>
</form>
