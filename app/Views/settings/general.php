<?php
$title = 'Paramètres';
$groupLabels = ['general' => 'Exploitation', 'zootechnie' => 'Paramètres zootechniques (utilisés par les indices)', 'alertes' => 'Alertes du tableau de bord', 'affichage' => 'Affichage'];
?>
<h1 class="h4 page-title mb-3"><i class="bi bi-gear me-2"></i>Paramètres</h1>
<form method="post" action="<?= e(url('settings/save')) ?>">
    <?= csrf_field() ?>
    <div class="row g-3">
        <?php foreach ($groups as $g => $rows): ?>
            <div class="col-lg-6">
                <div class="card h-100"><div class="card-header"><?= e($groupLabels[$g] ?? ucfirst($g)) ?></div><div class="card-body">
                    <?php foreach ($rows as $s): $k = $s['skey']; ?>
                        <div class="mb-2">
                            <label class="form-label small" for="s_<?= e($k) ?>"><?= e($s['label']) ?></label>
                            <input id="s_<?= e($k) ?>" name="s[<?= e($k) ?>]" class="form-control form-control-sm<?= invalid($errors, $k) ?>"
                                   type="<?= in_array($s['stype'], ['int', 'number'], true) ? 'number' : 'text' ?>"<?= $s['stype'] === 'number' ? ' step="any"' : '' ?>
                                   value="<?= e($s['svalue']) ?>">
                            <?= field_error($errors, $k) ?>
                        </div>
                    <?php endforeach; ?>
                </div></div>
            </div>
        <?php endforeach; ?>
    </div>
    <button class="btn btn-forest mt-3"><i class="bi bi-check2 me-1"></i>Enregistrer les paramètres</button>
    <a class="btn btn-outline-secondary mt-3" href="<?= e(url('settings/indices')) ?>"><i class="bi bi-calculator me-1"></i>Formules des indices</a>
</form>
