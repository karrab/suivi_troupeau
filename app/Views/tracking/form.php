<?php
use App\Services\Tracking;

$title = ($isEdit ? 'Modifier : ' : 'Ajouter : ') . $cfg['singular'];
$err = $errors;
$back = $_GET['back'] ?? (old('back') ?: '');
$cancel = $back === 'animal' && !empty($row['animal_id']) ? url('animals/show', ['id' => $row['animal_id'], 'tab' => $type]) : url('tracking', ['type' => $type]);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 page-title mb-0"><i class="bi <?= $cfg['icon'] ?> me-2"></i><?= e(ucfirst($title)) ?></h1>
    <a href="<?= e($cancel) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Retour</a>
</div>
<?php if ($err): ?><div class="alert alert-danger">Veuillez corriger les erreurs signalées.</div><?php endif; ?>
<form method="post" action="<?= e(url($isEdit ? 'tracking/update' : 'tracking/store')) ?>" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="type" value="<?= e($type) ?>">
    <input type="hidden" name="back" value="<?= e($back) ?>">
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><?php endif; ?>
    <div class="card"><div class="card-body row g-3">
        <?php foreach ($cfg['fields'] as $f => $def):
            $req = !empty($def['required']) ? ' *' : '';
            if ($def['type'] === 'virtual'):
                if ($isEdit || !empty($def['only_create']) && $isEdit) continue; ?>
                <div class="col-12"><div class="form-check">
                    <input class="form-check-input" type="checkbox" name="<?= $f ?>" value="1" id="f_<?= $f ?>"<?= checked(old($f, '1')) ?>>
                    <label class="form-check-label" for="f_<?= $f ?>"><?= e($def['label']) ?></label>
                </div></div>
            <?php continue; endif; ?>
            <div class="<?= $def['type'] === 'textarea' ? 'col-12' : 'col-md-4' ?>">
                <label class="form-label" for="f_<?= $f ?>"><?= e($def['label']) . $req ?></label>
                <?php switch ($def['type']):
                    case 'animal':
                        $tagVal = isset($_SESSION['_old'][$f . '_tag']) ? $_SESSION['_old'][$f . '_tag']
                            : ($f === 'animal_id' ? ($row['animal_tag'] ?? '') : ($row[Tracking::alias($f, 'animal')] ?? '')); ?>
                        <input id="f_<?= $f ?>" name="<?= $f ?>_tag" class="form-control<?= invalid($err, $f) ?>" value="<?= e($tagVal) ?>"
                               data-animal-search<?= !empty($def['sex']) ? ' data-sex="' . $def['sex'] . '"' : '' ?> placeholder="N° boucle"<?= $req ? ' required' : '' ?>>
                        <?php break;
                    case 'date': ?>
                        <input type="date" id="f_<?= $f ?>" name="<?= $f ?>" class="form-control<?= invalid($err, $f) ?>" value="<?= e(fv($row, $f)) ?>"<?= $req ? ' required' : '' ?>>
                        <?php break;
                    case 'number': ?>
                        <input type="number" id="f_<?= $f ?>" name="<?= $f ?>" class="form-control<?= invalid($err, $f) ?>" value="<?= e(fv($row, $f)) ?>"
                               step="<?= e($def['step'] ?? 'any') ?>"<?= isset($def['min']) ? ' min="' . $def['min'] . '"' : '' ?><?= isset($def['max']) ? ' max="' . $def['max'] . '"' : '' ?><?= $req ? ' required' : '' ?>>
                        <?php break;
                    case 'select': ?>
                        <select id="f_<?= $f ?>" name="<?= $f ?>" class="form-select<?= invalid($err, $f) ?>">
                            <?php if (empty($def['required']) && !isset($def['default'])): ?><option value="">—</option><?php endif; ?>
                            <?php foreach (labels($def['options']) as $k => $l): ?><option value="<?= e($k) ?>"<?= selected(fv($row, $f), $k) ?>><?= e($l) ?></option><?php endforeach; ?>
                        </select>
                        <?php break;
                    case 'lot': ?>
                        <select id="f_<?= $f ?>" name="<?= $f ?>" class="form-select<?= invalid($err, $f) ?>"><option value="">—</option>
                            <?php foreach ($lots as $l): ?><option value="<?= $l['id'] ?>"<?= selected(fv($row, $f), $l['id']) ?>><?= e($l['name']) ?></option><?php endforeach; ?>
                        </select>
                        <?php break;
                    case 'bool': ?>
                        <div class="form-check form-switch mt-1"><input class="form-check-input" type="checkbox" id="f_<?= $f ?>" name="<?= $f ?>" value="1"<?= checked(fv($row, $f)) ?>></div>
                        <?php break;
                    case 'textarea': ?>
                        <textarea id="f_<?= $f ?>" name="<?= $f ?>" rows="3" class="form-control<?= invalid($err, $f) ?>"><?= e(fv($row, $f)) ?></textarea>
                        <?php break;
                    default: ?>
                        <input id="f_<?= $f ?>" name="<?= $f ?>" class="form-control<?= invalid($err, $f) ?>" value="<?= e(fv($row, $f)) ?>"<?= isset($def['maxlen']) ? ' maxlength="' . $def['maxlen'] . '"' : '' ?>>
                <?php endswitch; ?>
                <?= field_error($err, $f) ?>
            </div>
        <?php endforeach; ?>
    </div></div>
    <div class="mt-3 d-flex gap-2">
        <button class="btn btn-forest"><i class="bi bi-check2 me-1"></i>Enregistrer</button>
        <?php if (!$isEdit): ?><button class="btn btn-outline-success" name="save_new" value="1"><i class="bi bi-plus me-1"></i>Enregistrer et nouveau</button><?php endif; ?>
        <a href="<?= e($cancel) ?>" class="btn btn-outline-secondary">Annuler</a>
    </div>
</form>
