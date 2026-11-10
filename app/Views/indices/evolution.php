<?php
$title = 'Évolution des indices';
$palette = ['#195c3e', '#c97b3d', '#2f6fb0', '#a83c5a', '#6a4c93', '#3c9d9b', '#d4a017', '#7a7a7a'];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 page-title mb-0"><i class="bi bi-bar-chart-line me-2"></i>Évolution annuelle des indices</h1>
    <div class="d-flex gap-2 d-print-none">
        <form method="get" action="<?= e(url()) ?>" class="d-flex gap-1">
            <input type="hidden" name="r" value="indices/evolution">
            <select name="group_type" class="form-select form-select-sm" data-autosubmit>
                <?php foreach (['global', 'espece', 'categorie', 'race', 'lot'] as $gt): ?><option value="<?= $gt ?>"<?= selected($groupType, $gt) ?>><?= e(label('group_type', $gt)) ?></option><?php endforeach; ?>
            </select>
            <?php if ($groupType !== 'global'): ?>
                <select name="group_value" class="form-select form-select-sm" data-autosubmit>
                    <?php foreach ($groups as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($groupValue, $k) ?>><?= e($l) ?></option><?php endforeach; ?>
                </select>
            <?php endif; ?>
        </form>
        <button class="btn btn-outline-secondary btn-sm" data-print><i class="bi bi-printer me-1"></i>Imprimer</button>
        <?php if (can('indices.export')): ?><a class="btn btn-outline-danger btn-sm" target="_blank" href="<?= e(url('indices/export', ['mode' => 'evolution', 'group_type' => $groupType, 'group_value' => $groupValue])) ?>"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a><?php endif; ?>
    </div>
</div>

<?php if (!$years): ?>
    <div class="alert alert-info">Aucun résultat annuel enregistré pour ce groupe. Calculez les indices pour plusieurs années depuis la page <a href="<?= e(url('indices')) ?>">Indices du troupeau</a>.</div>
<?php else: ?>
    <div class="card mb-3"><div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead><tr><th>Indice</th><?php foreach ($years as $y): ?><th class="text-end"><?= $y ?></th><?php endforeach; ?><th class="text-end">Tendance</th></tr></thead>
            <tbody>
            <?php foreach ($matrix as $m): $vals = array_values(array_filter(array_map(static fn($c) => $c['value'], $m['values']), static fn($v) => $v !== null)); ?>
                <tr><td><?= e($m['def']['name']) ?> <span class="text-muted small"><?= e($m['def']['unit']) ?></span></td>
                    <?php foreach ($years as $y): $c = $m['values'][$y] ?? null; ?>
                        <td class="text-end"><?php if ($c): ?><span class="me-1"><?= $c['value'] !== null ? fmt_num($c['value'], (int) $m['def']['decimals']) : 'N/D' ?></span><?= level_badge($c['level']) ?><?php else: ?>—<?php endif; ?></td>
                    <?php endforeach; ?>
                    <td class="text-end"><?php if (count($vals) > 1): $d = end($vals) - $vals[0]; ?>
                        <i class="bi <?= $d > 0 ? 'bi-arrow-up-right text-success' : ($d < 0 ? 'bi-arrow-down-right text-danger' : 'bi-arrow-right') ?>"></i> <?= fmt_num($d, (int) $m['def']['decimals']) ?>
                    <?php endif; ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div>
    <div class="row g-3">
        <?php $i = 0; foreach ($matrix as $m): $col = $palette[$i++ % count($palette)]; ?>
            <div class="col-md-6 col-xl-4"><div class="card h-100"><div class="card-header small"><?= e($m['def']['name']) ?> (<?= e($m['def']['unit']) ?>)</div><div class="card-body">
                <div style="height:160px"><canvas data-chart="<?= e(json_encode([
                    'type' => 'line', 'labels' => array_map('strval', $years), 'beginAtZero' => true,
                    'datasets' => [['label' => $m['def']['name'], 'data' => array_map(static fn($y) => isset($m['values'][$y]['value']) ? (float) $m['values'][$y]['value'] : null, $years),
                        'borderColor' => $col, 'backgroundColor' => $col, 'tension' => .2, 'spanGaps' => true]],
                ])) ?>"></canvas></div>
            </div></div></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
