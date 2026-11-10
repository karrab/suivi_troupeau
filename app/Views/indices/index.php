<?php $title = 'Indices du troupeau'; ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 page-title mb-0"><i class="bi bi-graph-up-arrow me-2"></i>Indices zootechniques — <?= $year ?: 'vie entière' ?></h1>
    <div class="d-flex gap-2 d-print-none">
        <form method="get" action="<?= e(url()) ?>" class="d-flex gap-1">
            <input type="hidden" name="r" value="indices">
            <select name="year" class="form-select form-select-sm" data-autosubmit aria-label="Année">
                <?php foreach ($years as $y): ?><option value="<?= $y ?>"<?= selected($year, $y) ?>><?= $y ?></option><?php endforeach; ?>
                <option value="0"<?= selected($year, 0) ?>>Vie entière</option>
            </select>
            <select name="group_type" class="form-select form-select-sm" data-autosubmit aria-label="Regroupement">
                <?php foreach (['global', 'espece', 'categorie', 'race', 'lot'] as $gt): ?><option value="<?= $gt ?>"<?= selected($groupType, $gt) ?>><?= e(label('group_type', $gt)) ?></option><?php endforeach; ?>
            </select>
        </form>
        <button class="btn btn-outline-secondary btn-sm" data-print><i class="bi bi-printer me-1"></i>Imprimer</button>
        <?php if (can('indices.export')): ?><a class="btn btn-outline-danger btn-sm" target="_blank" href="<?= e(url('indices/export', ['mode' => 'overview', 'year' => $year, 'group_type' => $groupType])) ?>"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a><?php endif; ?>
    </div>
</div>

<?php if (can('indices.compute')): ?>
<div class="card mb-3 d-print-none"><div class="card-header"><i class="bi bi-calculator me-1"></i>Calculer et enregistrer les indices</div><div class="card-body">
    <form method="post" action="<?= e(url('indices/compute')) ?>" class="row g-2 align-items-end">
        <?= csrf_field() ?>
        <div class="col-6 col-md-2"><label class="form-label small">Période</label>
            <select name="year" class="form-select form-select-sm">
                <?php foreach ($years as $y): ?><option value="<?= $y ?>"<?= selected($year, $y) ?>><?= $y ?></option><?php endforeach; ?>
                <option value="0">Vie entière</option>
            </select></div>
        <div class="col-6 col-md-2"><label class="form-label small">Cible</label>
            <select name="target" id="target" class="form-select form-select-sm">
                <option value="troupeau">Troupeau / groupes</option><option value="animal">Chaque animal</option>
            </select></div>
        <div class="col-md-4" data-target-scope="troupeau"><label class="form-label small">Regroupements</label>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach (['global', 'espece', 'categorie', 'race', 'lot'] as $gt): ?>
                    <div class="form-check form-check-inline small mb-0"><input class="form-check-input" type="checkbox" name="group_types[]" value="<?= $gt ?>" id="gt<?= $gt ?>"<?= in_array($gt, ['global', 'espece'], true) ? ' checked' : '' ?>><label class="form-check-label" for="gt<?= $gt ?>"><?= e(label('group_type', $gt)) ?></label></div>
                <?php endforeach; ?>
            </div></div>
        <div class="col-md-3" data-target-scope="troupeau"><label class="form-label small">Indices (vide = tous)</label>
            <select name="index_ids[]" class="form-select form-select-sm" multiple size="3">
                <?php foreach ($herdDefs as $d): ?><option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-md-7 d-none" data-target-scope="animal"><label class="form-label small">Indices par animal (vide = tous)</label>
            <select name="index_ids[]" class="form-select form-select-sm" multiple size="3">
                <?php foreach ($animalDefs as $d): ?><option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-md-1"><button class="btn btn-forest btn-sm w-100"><i class="bi bi-play-fill"></i> Calculer</button></div>
    </form>
    <div class="form-text">Les résultats sont enregistrés (remplacés s'ils existent) avec la formule et les variables utilisées. Formules modifiables dans <a href="<?= e(url('settings/indices')) ?>">Formules des indices</a>.</div>
</div></div>
<?php endif; ?>

<?php foreach ($grouped as $groupLabel => $rows): ?>
    <div class="card mb-3"><div class="card-header d-flex justify-content-between"><span><i class="bi bi-diagram-3 me-1"></i><?= e(label('group_type', $groupType)) ?> : <?= e($groupLabel) ?></span>
        <span class="small text-muted fw-normal">calculé le <?= fmt_date($rows[0]['computed_at'], true) ?></span></div>
        <div class="card-body"><div class="row g-2">
            <?php foreach ($rows as $r): ?>
                <div class="col-md-6 col-xl-4">
                    <div class="border rounded p-2 h-100 level-<?= e($r['level']) ?> bg-white">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="small fw-semibold" title="<?= e($r['description']) ?>"><?= e($r['name']) ?></div><?= level_badge($r['level']) ?>
                        </div>
                        <div class="fs-4 fw-bold text-forest"><?= $r['value'] !== null ? fmt_num($r['value'], (int) $r['decimals']) : 'N/D' ?> <small class="fs-6 text-muted"><?= e($r['unit']) ?></small></div>
                        <div class="small text-muted"><?= e($r['interpretation']) ?></div>
                        <a class="small d-print-none" href="<?= e(url('indices/show', ['id' => $r['id']])) ?>">Détail du calcul</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div></div>
    </div>
<?php endforeach; ?>
<?php if (!$grouped): ?>
    <div class="alert alert-info">Aucun indice troupeau enregistré pour cette période et ce regroupement. Lancez le calcul ci-dessus.</div>
<?php endif; ?>

<div class="card"><div class="card-header"><i class="bi bi-person-badge me-1"></i>Synthèse des indices individuels — <?= $year ?: 'vie entière' ?></div>
    <div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th>Indice</th><th class="text-end">Animaux</th><th class="text-end">Moyenne</th><th class="text-end">Min</th><th class="text-end">Max</th><th class="text-center">Bon</th><th class="text-center">Moyen</th><th class="text-center">Faible</th><th class="d-print-none"></th></tr></thead>
        <tbody>
        <?php foreach ($animalSummary as $s): ?>
            <tr><td><?= e($s['name']) ?></td><td class="text-end"><?= (int) $s['n'] ?></td>
                <td class="text-end fw-semibold"><?= $s['avg_v'] !== null ? fmt_num($s['avg_v'], (int) $s['decimals']) . ' ' . e($s['unit']) : '—' ?></td>
                <td class="text-end"><?= $s['min_v'] !== null ? fmt_num($s['min_v'], (int) $s['decimals']) : '—' ?></td>
                <td class="text-end"><?= $s['max_v'] !== null ? fmt_num($s['max_v'], (int) $s['decimals']) : '—' ?></td>
                <td class="text-center"><span class="badge text-bg-success"><?= (int) $s['bon'] ?></span></td>
                <td class="text-center"><span class="badge text-bg-warning"><?= (int) $s['moyen'] ?></span></td>
                <td class="text-center"><span class="badge text-bg-danger"><?= (int) $s['faible'] ?></span></td>
                <td class="d-print-none"><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('indices/results', ['year' => $year, 'group_type' => 'animal', 'index_id' => $s['id'], 'sort' => 'value', 'dir' => 'desc'])) ?>" title="Classement"><i class="bi bi-sort-down"></i></a></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div>
