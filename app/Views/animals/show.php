<?php
use App\Services\Tracking;

$title = 'Fiche ' . $a['tag'];
$lastWeight = $weights ? end($weights) : null;
$info = [
    'Espèce' => label('species', $a['species']), 'Catégorie' => $a['category_label'], 'Sexe' => label('sex', $a['sex']),
    'Race' => $a['breed_name'], 'Robe' => $a['color'], 'Lot' => $a['lot_name'],
    'Naissance' => fmt_date($a['birth_date']) . ((int) $a['birth_date_estimated'] ? ' (estimée)' : ''),
    'Âge' => fmt_age($a['birth_date'], $a['exit_date']),
    'Poids de naissance' => $a['birth_weight'] !== null ? fmt_num($a['birth_weight']) . ' kg' : null,
    'Type de naissance' => $a['birth_type'] ? label('birth_type', $a['birth_type']) : null,
    'Dernier poids' => $lastWeight ? fmt_num($lastWeight['weight']) . ' kg (' . fmt_date($lastWeight['event_date']) . ')' : null,
    'Origine' => label('origin', $a['origin']), 'Entrée' => fmt_date($a['entry_date']),
    'Prix d\'achat' => $a['purchase_price'] !== null ? fmt_money($a['purchase_price']) : null,
];
$exit = $a['status'] !== 'actif' ? [
    'Date de sortie' => fmt_date($a['exit_date']), 'Motif' => $a['exit_reason'],
    'Prix de vente' => $a['sale_price'] !== null ? fmt_money($a['sale_price']) : null,
] : [];
$typeIcons = ['creation' => 'bi-plus-circle', 'modification' => 'bi-pencil', 'suppression' => 'bi-trash', 'sortie' => 'bi-box-arrow-right',
    'pesee' => 'bi-speedometer2', 'sante' => 'bi-heart-pulse', 'saillie' => 'bi-gender-ambiguous', 'mise_bas' => 'bi-stars',
    'naissance' => 'bi-star', 'lait' => 'bi-droplet', 'mouvement' => 'bi-arrow-left-right', 'indice' => 'bi-graph-up'];
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h1 class="h4 page-title mb-1">
            <i class="bi <?= $a['sex'] === 'F' ? 'bi-gender-female text-danger' : 'bi-gender-male text-primary' ?> me-1"></i>
            <?= e($a['tag']) ?> <?= $a['name'] ? '<span class="text-muted">— ' . e($a['name']) . '</span>' : '' ?> <?= status_badge($a['status']) ?>
        </h1>
        <div class="text-muted small"><?= e($a['category_label']) ?> · <?= e(label('species', $a['species'])) ?> · fiche créée le <?= fmt_date($a['created_at'], true) ?><?= $a['created_by_name'] ? ' par ' . e($a['created_by_name']) : '' ?><?= $a['updated_at'] ? ' · modifiée le ' . fmt_date($a['updated_at'], true) : '' ?></div>
    </div>
    <div class="d-flex flex-wrap gap-1 d-print-none">
        <a href="<?= e(url('animals')) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
        <?php if (can('animals.edit')): ?><a href="<?= e(url('animals/edit', ['id' => $a['id']])) ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil me-1"></i>Modifier</a><?php endif; ?>
        <button class="btn btn-outline-secondary btn-sm" data-print><i class="bi bi-printer me-1"></i>Imprimer</button>
        <?php if (can('animals.export')): ?>
            <a href="<?= e(url('animals/pdf', ['id' => $a['id']])) ?>" target="_blank" class="btn btn-outline-danger btn-sm"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>
        <?php endif; ?>
        <?php if (can('indices.compute')): ?>
            <form method="post" action="<?= e(url('animals/compute')) ?>" class="d-inline-flex gap-1">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                <select name="year" class="form-select form-select-sm" style="width:auto" aria-label="Année">
                    <?php for ($y = (int) date('Y'); $y >= (int) substr($a['entry_date'], 0, 4); $y--): ?><option><?= $y ?></option><?php endfor; ?>
                </select>
                <button class="btn btn-outline-success btn-sm text-nowrap"><i class="bi bi-calculator me-1"></i>Calculer indices</button>
            </form>
        <?php endif; ?>
        <?php if (can('animals.delete')): ?>
            <?php $action = url('animals/delete'); $id = $a['id']; $message = 'Supprimer définitivement ' . $a['tag'] . ' et tous ses suivis ?'; $label = true; include __DIR__ . '/../partials/delete_button.php'; $label = false; ?>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-5">
        <div class="card h-100"><div class="card-header"><i class="bi bi-card-text me-1"></i>Identification</div><div class="card-body">
            <dl class="row mb-0 small">
                <?php foreach ($info as $k => $v): if ($v === null || $v === '') continue; ?>
                    <dt class="col-5 text-muted fw-normal"><?= e($k) ?></dt><dd class="col-7 mb-1 fw-semibold"><?= e($v) ?></dd>
                <?php endforeach; ?>
                <dt class="col-5 text-muted fw-normal">Mère</dt><dd class="col-7 mb-1"><?= $a['mother_id'] ? '<a href="' . e(url('animals/show', ['id' => $a['mother_id']])) . '">' . e($a['mother_tag']) . '</a> ' . e($a['mother_name']) : '—' ?></dd>
                <dt class="col-5 text-muted fw-normal">Père</dt><dd class="col-7 mb-1"><?= $a['father_id'] ? '<a href="' . e(url('animals/show', ['id' => $a['father_id']])) . '">' . e($a['father_tag']) . '</a> ' . e($a['father_name']) : '—' ?></dd>
                <?php foreach ($exit as $k => $v): if ($v === null || $v === '') continue; ?>
                    <dt class="col-5 text-muted fw-normal"><?= e($k) ?></dt><dd class="col-7 mb-1 fw-semibold text-danger"><?= e($v) ?></dd>
                <?php endforeach; ?>
            </dl>
            <?php if ($a['notes']): ?><hr><div class="small"><?= nl2br(e($a['notes'])) ?></div><?php endif; ?>
        </div></div>
    </div>
    <div class="col-lg-7">
        <div class="card h-100"><div class="card-header"><i class="bi bi-graph-up me-1"></i>Courbe de poids</div><div class="card-body">
            <?php if (count($weights) > 1): ?>
                <div style="height:240px"><canvas data-chart="<?= e(json_encode([
                    'type' => 'line', 'labels' => array_map(static fn($w) => fmt_date($w['event_date']), $weights),
                    'datasets' => [['label' => 'Poids (kg)', 'data' => array_map(static fn($w) => (float) $w['weight'], $weights),
                        'borderColor' => '#195c3e', 'backgroundColor' => 'rgba(25,92,62,.15)', 'fill' => true, 'tension' => .25]],
                ])) ?>"></canvas></div>
            <?php else: ?><p class="text-muted small mb-0">Au moins deux pesées sont nécessaires pour tracer la courbe.</p><?php endif; ?>
        </div></div>
    </div>
</div>

<ul class="nav nav-tabs d-print-none" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-history" type="button">Historique <span class="badge text-bg-light"><?= count($history) ?></span></button></li>
    <?php foreach ($tracking as $type => $t): ?>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-<?= $type ?>" type="button"><i class="bi <?= $t['cfg']['icon'] ?> me-1"></i><?= e($t['cfg']['title']) ?> <span class="badge text-bg-light"><?= count($t['rows']) ?></span></button></li>
    <?php endforeach; ?>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-offspring" type="button">Descendance <span class="badge text-bg-light"><?= count($offspring) ?></span></button></li>
    <?php if (can('indices.view')): ?><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-indices" type="button">Indices <span class="badge text-bg-light"><?= count($indices) ?></span></button></li><?php endif; ?>
</ul>
<div class="tab-content card border-top-0 rounded-top-0">
    <div class="tab-pane fade show active p-3" id="tab-history">
        <h2 class="h6 print-only">Historique</h2>
        <div class="timeline">
            <?php foreach ($history as $h): ?>
                <div class="timeline-item">
                    <div class="small text-muted"><?= fmt_date($h['event_date']) ?> · <i class="bi <?= $typeIcons[$h['event_type']] ?? 'bi-dot' ?>"></i> <?= e(label('history_type', $h['event_type'])) ?><?= $h['full_name'] ? ' · ' . e($h['full_name']) : '' ?></div>
                    <div><?= e($h['summary']) ?></div>
                </div>
            <?php endforeach; ?>
            <?php if (!$history): ?><p class="text-muted small">Aucun événement.</p><?php endif; ?>
        </div>
    </div>

    <?php foreach ($tracking as $type => $t): $cfg = $t['cfg']; ?>
        <div class="tab-pane fade p-3" id="tab-<?= $type ?>">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h6 mb-0"><i class="bi <?= $cfg['icon'] ?> me-1"></i><?= e($cfg['title']) ?></h2>
                <?php if (can("$type.create") && $a['status'] === 'actif' && (!$cfg['animal_sex'] || $cfg['animal_sex'] === $a['sex'])): ?>
                    <a class="btn btn-forest btn-sm d-print-none" href="<?= e(url('tracking/create', ['type' => $type, 'animal_id' => $a['id'], 'back' => 'animal'])) ?>"><i class="bi bi-plus-lg me-1"></i>Ajouter</a>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead><tr>
                        <?php foreach ($cfg['list'] as $f): ?><th><?= e($cfg['fields'][$f]['label']) ?></th><?php endforeach; ?>
                        <th class="table-actions d-print-none"></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($t['rows'] as $r): ?>
                        <tr>
                            <?php foreach ($cfg['list'] as $f): ?>
                                <td class="<?= $cfg['fields'][$f]['type'] === 'number' ? 'text-end' : '' ?>"><?= Tracking::format($cfg, $f, $r) ?></td>
                            <?php endforeach; ?>
                            <td class="table-actions d-print-none">
                                <?php if (can("$type.edit")): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('tracking/edit', ['type' => $type, 'id' => $r['id'], 'back' => 'animal'])) ?>"><i class="bi bi-pencil"></i></a><?php endif; ?>
                                <?php if (can("$type.export")): ?><a class="btn btn-sm btn-outline-danger" target="_blank" href="<?= e(url('tracking/pdf', ['type' => $type, 'id' => $r['id']])) ?>"><i class="bi bi-file-earmark-pdf"></i></a><?php endif; ?>
                                <?php if (can("$type.delete")): ?>
                                    <?php $action = url('tracking/delete'); $id = $r['id']; $message = 'Supprimer cet enregistrement ?'; $extra = ['type' => $type, 'back' => 'animal']; include __DIR__ . '/../partials/delete_button.php'; $extra = []; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$t['rows']): ?><tr><td colspan="<?= count($cfg['list']) + 1 ?>" class="text-muted small">Aucun enregistrement.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="tab-pane fade p-3" id="tab-offspring">
        <h2 class="h6 print-only">Descendance</h2>
        <table class="table table-sm mb-0">
            <thead><tr><th>N° boucle</th><th>Nom</th><th>Catégorie</th><th>Naissance</th><th>Statut</th></tr></thead>
            <tbody>
            <?php foreach ($offspring as $o): ?>
                <tr><td><a href="<?= e(url('animals/show', ['id' => $o['id']])) ?>"><?= e($o['tag']) ?></a></td><td><?= e($o['name']) ?></td>
                    <td><?= e($o['category_label']) ?></td><td><?= fmt_date($o['birth_date']) ?></td><td><?= status_badge($o['status']) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$offspring): ?><tr><td colspan="5" class="text-muted small">Aucun descendant enregistré.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if (can('indices.view')): ?>
        <div class="tab-pane fade p-3" id="tab-indices">
            <h2 class="h6 print-only">Indices</h2>
            <table class="table table-sm mb-0">
                <thead><tr><th>Année</th><th>Indice</th><th class="text-end">Valeur</th><th>Niveau</th><th>Interprétation</th><th class="d-print-none"></th></tr></thead>
                <tbody>
                <?php foreach ($indices as $r): ?>
                    <tr class="level-<?= e($r['level']) ?>">
                        <td><?= $r['year'] ?: 'Vie entière' ?></td><td><?= e($r['name']) ?></td>
                        <td class="text-end fw-semibold text-nowrap"><?= $r['value'] !== null ? fmt_num($r['value'], (int) $r['decimals']) . ' ' . e($r['unit']) : 'N/D' ?></td>
                        <td><?= level_badge($r['level']) ?></td><td class="small"><?= e($r['interpretation']) ?></td>
                        <td class="d-print-none"><a href="<?= e(url('indices/show', ['id' => $r['id']])) ?>" class="btn btn-sm btn-outline-secondary" title="Détail du calcul"><i class="bi bi-info-circle"></i></a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$indices): ?><tr><td colspan="6" class="text-muted small">Aucun indice calculé. Utilisez « Calculer indices ».</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
