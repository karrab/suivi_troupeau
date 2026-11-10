<?php
$isEdit = !empty($def['id']);
$title = $isEdit ? 'Modifier l\'indice ' . $def['name'] : 'Nouvel indice';
$err = $errors;
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 page-title mb-0"><i class="bi bi-calculator me-2"></i><?= e($title) ?></h1>
    <a href="<?= e(url('settings/indices')) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Retour</a>
</div>
<?php if ($err): ?><div class="alert alert-danger">Veuillez corriger les erreurs signalées.</div><?php endif; ?>
<form method="post" action="<?= e(url('settings/index-save')) ?>" novalidate>
    <?= csrf_field() ?>
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $def['id'] ?>"><?php endif; ?>
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card mb-3"><div class="card-header">Définition</div><div class="card-body row g-2">
                <div class="col-md-4"><label class="form-label">Code *</label>
                    <input name="code" class="form-control<?= invalid($err, 'code') ?>" value="<?= e(fv($def, 'code')) ?>" pattern="[a-z0-9_]+" required><?= field_error($err, 'code') ?>
                    <div class="form-text">minuscules, chiffres, _</div></div>
                <div class="col-md-8"><label class="form-label">Nom *</label>
                    <input name="name" class="form-control<?= invalid($err, 'name') ?>" value="<?= e(fv($def, 'name')) ?>" required><?= field_error($err, 'name') ?></div>
                <div class="col-12"><label class="form-label">Description</label>
                    <input name="description" class="form-control" value="<?= e(fv($def, 'description')) ?>"></div>
                <div class="col-md-4"><label class="form-label">Portée *</label>
                    <select name="scope" id="scope" class="form-select">
                        <?php foreach (labels('scope') as $k => $l): ?><option value="<?= $k ?>"<?= selected(fv($def, 'scope'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3"><label class="form-label">Unité</label><input name="unit" class="form-control" value="<?= e(fv($def, 'unit')) ?>"></div>
                <div class="col-md-2"><label class="form-label">Décimales</label><input type="number" min="0" max="6" name="decimals" class="form-control<?= invalid($err, 'decimals') ?>" value="<?= e(fv($def, 'decimals', 2)) ?>"></div>
                <div class="col-md-3"><label class="form-label">Ordre</label><input type="number" name="sort_order" class="form-control" value="<?= e(fv($def, 'sort_order', 0)) ?>"></div>
                <div class="col-12">
                    <label class="form-label" for="formula">Formule *</label>
                    <textarea name="formula" id="formula" rows="3" class="form-control formula<?= invalid($err, 'formula') ?>" required><?= e(fv($def, 'formula')) ?></textarea>
                    <?= field_error($err, 'formula') ?>
                    <div class="form-text">Opérateurs : <code>+ - * / % ^ ( )</code>, comparaisons <code>&lt; &lt;= &gt; &gt;= == !=</code>, logique <code>et ou !</code>.
                        Fonctions : <code>min() max() abs() round(x,n) floor() ceil() sqrt() si(cond, a, b) coalesce(a, b) nz(x) pct(a, b)</code>.
                        Une division par zéro donne « N/D ». Cliquez sur une variable pour l'insérer.</div>
                </div>
                <div class="col-12 form-check ms-2"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="act"<?= checked(fv($def, 'is_active', 1)) ?>>
                    <label class="form-check-label" for="act">Indice actif (inclus dans les calculs)</label></div>
            </div></div>

            <div class="card mb-3"><div class="card-header">Interprétation automatique</div><div class="card-body row g-2">
                <div class="col-md-4"><label class="form-label">Sens favorable</label>
                    <select name="better" class="form-select"><?php foreach (labels('better') as $k => $l): ?><option value="<?= $k ?>"<?= selected(fv($def, 'better'), $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-4"><label class="form-label">Seuil bas</label><input name="threshold_low" type="number" step="any" class="form-control<?= invalid($err, 'threshold_low') ?>" value="<?= e(fv($def, 'threshold_low')) ?>"><?= field_error($err, 'threshold_low') ?></div>
                <div class="col-md-4"><label class="form-label">Seuil haut</label><input name="threshold_high" type="number" step="any" class="form-control<?= invalid($err, 'threshold_high') ?>" value="<?= e(fv($def, 'threshold_high')) ?>"><?= field_error($err, 'threshold_high') ?></div>
                <div class="col-12 small text-muted">Sens « haut » : valeur ≥ seuil haut = <b>bon</b>, &lt; seuil bas = <b>faible</b>, sinon <b>moyen</b>. Sens « bas » : ≤ seuil bas = bon, &gt; seuil haut = faible.
                    Textes : utilisez <code>{valeur}</code> <code>{unite}</code> <code>{seuil_bas}</code> <code>{seuil_haut}</code>.</div>
                <div class="col-12"><label class="form-label text-success">Texte « bon »</label><input name="text_good" class="form-control" value="<?= e(fv($def, 'text_good')) ?>"></div>
                <div class="col-12"><label class="form-label text-warning">Texte « moyen »</label><input name="text_medium" class="form-control" value="<?= e(fv($def, 'text_medium')) ?>"></div>
                <div class="col-12"><label class="form-label text-danger">Texte « faible »</label><input name="text_bad" class="form-control" value="<?= e(fv($def, 'text_bad')) ?>"></div>
            </div></div>
        </div>

        <div class="col-lg-5">
            <div class="card mb-3"><div class="card-header d-flex justify-content-between align-items-center"><span>Tester sur les données réelles</span>
                <div class="d-flex gap-1"><select id="testYear" class="form-select form-select-sm" style="width:auto">
                    <?php for ($y = (int) date('Y'); $y >= (int) date('Y') - 5; $y--): ?><option value="<?= $y ?>"><?= $y ?></option><?php endfor; ?><option value="0">Vie entière</option>
                </select><button type="button" class="btn btn-sm btn-outline-success" id="btnTestFormula"><i class="bi bi-play-fill"></i> Tester</button></div></div>
                <div class="card-body" id="testResult"><p class="small text-muted mb-0">Le test évalue la formule saisie (sans l'enregistrer) sur le troupeau entier ou sur 5 animaux.</p></div>
            </div>
            <div class="card mb-3"><div class="card-header">Variables disponibles</div><div class="card-body" style="max-height:420px;overflow:auto">
                <?php foreach (['animal' => $animalVars, 'troupeau' => $herdVars] as $sc => $vars): ?>
                    <div data-vars-scope="<?= $sc ?>">
                        <?php foreach ($vars as $k => $desc): ?>
                            <div class="mb-1"><span class="badge text-bg-light border var-badge" data-var="<?= e($k) ?>"><?= e($k) ?></span> <span class="small text-muted"><?= e($desc) ?></span></div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div></div>
            <?php if ($history): ?>
                <div class="card"><div class="card-header">Historique des formules</div>
                    <ul class="list-group list-group-flush small">
                        <?php foreach ($history as $h): ?>
                            <li class="list-group-item"><div class="text-muted"><?= fmt_date($h['changed_at'], true) ?> · <?= e($h['full_name'] ?? '—') ?></div>
                                <?php if ($h['old_formula']): ?><del class="formula text-danger"><?= e($h['old_formula']) ?></del><br><?php endif; ?>
                                <span class="formula text-success"><?= e($h['new_formula']) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <button class="btn btn-forest"><i class="bi bi-check2 me-1"></i>Enregistrer l'indice</button>
</form>
