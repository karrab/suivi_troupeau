<?php $title = 'Formules des indices'; ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 page-title mb-0"><i class="bi bi-calculator me-2"></i>Définition et formules des indices</h1>
    <div class="d-flex gap-2 d-print-none">
        <button class="btn btn-outline-secondary btn-sm" data-print><i class="bi bi-printer me-1"></i>Imprimer</button>
        <?php if (can('indices.formulas')): ?>
            <a class="btn btn-forest btn-sm" href="<?= e(url('settings/index-form', ['scope' => 'troupeau'])) ?>"><i class="bi bi-plus-lg me-1"></i>Indice troupeau</a>
            <a class="btn btn-forest btn-sm" href="<?= e(url('settings/index-form', ['scope' => 'animal'])) ?>"><i class="bi bi-plus-lg me-1"></i>Indice animal</a>
        <?php endif; ?>
    </div>
</div>
<?php foreach (['troupeau' => 'Indices du troupeau (par année et par groupe)', 'animal' => 'Indices par animal'] as $scope => $scopeLabel): ?>
    <div class="card mb-3"><div class="card-header"><?= e($scopeLabel) ?></div>
        <div class="table-responsive"><table class="table table-sm table-hover mb-0">
            <thead><tr><th>Code</th><th>Nom</th><th>Formule</th><th>Unité</th><th>Seuils (bas / haut)</th><th>Sens</th><th>Actif</th><th class="text-end">Résultats</th><th class="table-actions d-print-none"></th></tr></thead>
            <tbody>
            <?php foreach ($defs as $d): if ($d['scope'] !== $scope) continue; ?>
                <tr class="<?= $d['is_active'] ? '' : 'text-muted' ?>">
                    <td><code><?= e($d['code']) ?></code></td><td><?= e($d['name']) ?></td>
                    <td><code class="formula small"><?= e($d['formula']) ?></code></td><td><?= e($d['unit']) ?></td>
                    <td class="text-nowrap"><?= $d['threshold_low'] !== null ? fmt_num($d['threshold_low'], (int) $d['decimals']) : '—' ?> / <?= $d['threshold_high'] !== null ? fmt_num($d['threshold_high'], (int) $d['decimals']) : '—' ?></td>
                    <td><i class="bi <?= $d['better'] === 'haut' ? 'bi-arrow-up text-success' : 'bi-arrow-down text-success' ?>" title="<?= e(label('better', $d['better'])) ?>"></i></td>
                    <td><?= $d['is_active'] ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle text-muted"></i>' ?></td>
                    <td class="text-end"><?= (int) $d['nb_results'] ?></td>
                    <td class="table-actions d-print-none">
                        <?php if (can('indices.formulas')): ?>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('settings/index-form', ['id' => $d['id']])) ?>" title="Modifier la formule"><i class="bi bi-pencil"></i></a>
                            <?php $action = url('settings/index-delete'); $id = $d['id']; $message = 'Supprimer l\'indice « ' . $d['name'] . ' » et ses ' . $d['nb_results'] . ' résultat(s) ?'; include __DIR__ . '/../partials/delete_button.php'; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
<?php endforeach; ?>
