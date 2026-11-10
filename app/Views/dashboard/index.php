<?php
$t = $stats['totals'] ?? [];
$months = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'];
$births = $milk = [];
for ($m = 1; $m <= 12; $m++) {
    $births[] = (int) ($stats['births_by_month'][$m] ?? 0);
    $milk[] = isset($stats['milk_by_month'][$m]) ? (float) $stats['milk_by_month'][$m] : null;
}
$cats = array_filter($stats['by_category'], static fn($c) => (int) $c['n'] > 0);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 page-title mb-0"><i class="bi bi-speedometer me-2"></i>Tableau de bord</h1>
    <div class="d-print-none">
        <?php if (can('animals.create')): ?><a href="<?= e(url('animals/create')) ?>" class="btn btn-forest btn-sm"><i class="bi bi-plus-lg me-1"></i>Nouvel animal</a><?php endif; ?>
        <button class="btn btn-outline-secondary btn-sm" data-print><i class="bi bi-printer"></i></button>
    </div>
</div>

<div class="row g-3 mb-3">
    <?php foreach ([
        ['Animaux actifs', $t['actifs'] ?? 0, 'bi-collection', ['status' => 'actif']],
        ['Caprins', $t['caprins'] ?? 0, 'bi-circle-fill', ['species' => 'caprin']],
        ['Ovins', $t['ovins'] ?? 0, 'bi-cloud-fill', ['species' => 'ovin']],
        ["Naissances $year", $t['naissances'] ?? 0, 'bi-stars', ['status' => 'tous', 'birth_from' => "$year-01-01"]],
        ["Morts $year", $t['morts'] ?? 0, 'bi-heartbreak', ['status' => 'mort', 'exit_from' => "$year-01-01"]],
        ["Ventes $year", $t['ventes'] ?? 0, 'bi-cash-coin', ['status' => 'vendu', 'exit_from' => "$year-01-01"]],
    ] as [$label, $value, $icon, $params]): ?>
        <div class="col-6 col-md-4 col-xl-2">
            <a class="text-decoration-none" href="<?= e(url('animals', $params)) ?>">
                <div class="card stat-card h-100"><div class="card-body d-flex justify-content-between">
                    <div><div class="small text-muted"><?= e($label) ?></div><div class="stat-value"><?= fmt_num($value, 0) ?></div></div>
                    <i class="bi <?= $icon ?> stat-icon text-forest"></i>
                </div></div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-4">
        <div class="card h-100"><div class="card-header">Effectif actif par catégorie</div>
            <div class="card-body">
                <div style="height:220px"><canvas data-chart="<?= e(json_encode([
                    'type' => 'doughnut', 'labels' => array_column($cats, 'label'),
                    'datasets' => [['data' => array_map('intval', array_column($cats, 'n')),
                        'backgroundColor' => ['#195c3e', '#2f7d55', '#4a9c6d', '#79bd8f', '#a3d4b0', '#c9e6d0', '#e0b354', '#c97b3d', '#8a5a2b']]],
                    'options' => ['plugins' => ['legend' => ['display' => true, 'position' => 'right']]],
                ])) ?>"></canvas></div>
                <table class="table table-sm mt-2 mb-0">
                    <?php foreach ($stats['by_category'] as $c): ?>
                        <tr><td><?= e($c['label']) ?> <span class="text-muted small">(<?= e(label('species', $c['species'])) ?>)</span></td><td class="text-end fw-semibold"><?= (int) $c['n'] ?></td></tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card h-100"><div class="card-header">Naissances (nés vivants) et production laitière moyenne — <?= $year ?></div>
            <div class="card-body"><div style="height:330px"><canvas data-chart="<?= e(json_encode([
                'type' => 'bar', 'labels' => $months, 'beginAtZero' => true,
                'datasets' => [
                    ['type' => 'bar', 'label' => 'Nés vivants', 'data' => $births, 'backgroundColor' => '#4a9c6d', 'yAxisID' => 'y'],
                    ['type' => 'line', 'label' => 'Lait moyen (L/j)', 'data' => $milk, 'borderColor' => '#c97b3d', 'backgroundColor' => '#c97b3d', 'yAxisID' => 'y1', 'spanGaps' => true],
                ],
                'options' => ['plugins' => ['legend' => ['display' => true]], 'scales' => ['y' => ['beginAtZero' => true, 'position' => 'left'], 'y1' => ['beginAtZero' => true, 'position' => 'right', 'grid' => ['drawOnChartArea' => false]]]],
            ])) ?>"></canvas></div></div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card h-100"><div class="card-header"><i class="bi bi-calendar-heart me-1 text-forest"></i>Mises bas prévues (<?= (int) setting('alerte_mise_bas_jours', 15) ?> j)</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($upcomingBirths as $b): ?>
                    <li class="list-group-item d-flex justify-content-between"><a href="<?= e(url('animals/show', ['id' => $b['animal_id']])) ?>"><?= e($b['tag']) ?> <?= e($b['name']) ?></a><span class="badge text-bg-info"><?= fmt_date($b['expected_birth']) ?></span></li>
                <?php endforeach; ?>
                <?php if (!$upcomingBirths): ?><li class="list-group-item text-muted small">Aucune mise bas prévue.</li><?php endif; ?>
            </ul>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100"><div class="card-header"><i class="bi bi-bell me-1 text-warning"></i>Rappels sanitaires & délais d'attente</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($reminders as $r): ?>
                    <li class="list-group-item d-flex justify-content-between"><span><a href="<?= e(url('animals/show', ['id' => $r['animal_id'], 'tab' => 'health'])) ?>"><?= e($r['tag']) ?></a> — <?= e(label('health_type', $r['event_type'])) ?> <?= e($r['product']) ?></span><span class="badge text-bg-warning"><?= fmt_date($r['next_date']) ?></span></li>
                <?php endforeach; ?>
                <?php foreach ($withdrawal as $w): ?>
                    <li class="list-group-item d-flex justify-content-between"><span><a href="<?= e(url('animals/show', ['id' => $w['animal_id'], 'tab' => 'health'])) ?>"><?= e($w['tag']) ?></a> — délai d'attente <?= e($w['product']) ?></span><span class="badge text-bg-danger">jusqu'au <?= fmt_date($w['until_date']) ?></span></li>
                <?php endforeach; ?>
                <?php if (!$reminders && !$withdrawal): ?><li class="list-group-item text-muted small">Aucune alerte.</li><?php endif; ?>
            </ul>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100"><div class="card-header"><i class="bi bi-clock-history me-1"></i>Derniers événements</div>
            <ul class="list-group list-group-flush small">
                <?php foreach ($recent as $h): ?>
                    <li class="list-group-item"><span class="text-muted"><?= fmt_date($h['event_date']) ?></span>
                        <a href="<?= e(url('animals/show', ['id' => $h['animal_id'], 'tab' => 'history'])) ?>"><?= e($h['tag']) ?></a> — <?= e(mb_strimwidth($h['summary'], 0, 90, '…')) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
