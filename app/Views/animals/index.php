<?php
$g = $_GET;
$status = $g['status'] ?? 'actif';
$advanced = array_intersect_key($g, array_flip(['breed_id', 'lot_id', 'origin', 'birth_from', 'birth_to', 'entry_from', 'entry_to', 'exit_from', 'exit_to', 'age_min', 'age_max', 'mother', 'father']));
$advancedOpen = (bool) array_filter($advanced, static fn($v) => $v !== '');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 page-title mb-0"><i class="bi bi-collection me-2"></i>Animaux <span class="badge text-bg-light"><?= fmt_num($total, 0) ?></span></h1>
    <div class="d-flex gap-2 d-print-none">
        <?php if (can('animals.edit')): ?>
            <form method="post" action="<?= e(url('animals/reclassify')) ?>" data-confirm="Reclasser en adultes les jeunes âgés de plus de <?= (int) setting('age_adulte_mois', 12) ?> mois ?">
                <?= csrf_field() ?><button class="btn btn-outline-secondary btn-sm" title="Chevreaux/agneaux → adultes"><i class="bi bi-arrow-up-circle me-1"></i>Reclasser les jeunes</button>
            </form>
        <?php endif; ?>
        <?php $exportRoute = 'animals/export'; $exportPerm = 'animals.export'; include __DIR__ . '/../partials/list_actions.php'; ?>
        <?php if (can('animals.create')): ?><a href="<?= e(url('animals/create')) ?>" class="btn btn-forest btn-sm"><i class="bi bi-plus-lg me-1"></i>Nouvel animal</a><?php endif; ?>
    </div>
</div>

<div class="card filters-card mb-3 d-print-none">
    <div class="card-body">
        <form method="get" action="<?= e(url()) ?>">
            <input type="hidden" name="r" value="animals">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">N° boucle / nom</label>
                    <input type="search" name="q" class="form-control form-control-sm" value="<?= e($g['q'] ?? '') ?>" data-animal-search data-all="1">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label">Espèce</label>
                    <select name="species" class="form-select form-select-sm"><option value="">Toutes</option>
                        <?php foreach (labels('species') as $k => $l): ?><option value="<?= $k ?>"<?= selected($g['species'] ?? '', $k) ?>><?= e($l) ?></option><?php endforeach; ?></select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label">Catégorie</label>
                    <select name="category_id" class="form-select form-select-sm"><option value="">Toutes</option>
                        <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>"<?= selected($g['category_id'] ?? '', $c['id']) ?>><?= e($c['label']) ?></option><?php endforeach; ?></select>
                </div>
                <div class="col-6 col-md-1">
                    <label class="form-label">Sexe</label>
                    <select name="sex" class="form-select form-select-sm"><option value="">Tous</option>
                        <?php foreach (labels('sex') as $k => $l): ?><option value="<?= $k ?>"<?= selected($g['sex'] ?? '', $k) ?>><?= e($l) ?></option><?php endforeach; ?></select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label">Statut</label>
                    <select name="status" class="form-select form-select-sm"><option value="tous"<?= selected($status, 'tous') ?>>Tous</option>
                        <?php foreach (labels('status') as $k => $l): ?><option value="<?= $k ?>"<?= selected($status, $k) ?>><?= e($l) ?></option><?php endforeach; ?></select>
                </div>
                <div class="col-md-2 d-flex gap-1">
                    <button class="btn btn-forest btn-sm flex-fill"><i class="bi bi-search me-1"></i>Rechercher</button>
                    <a href="<?= e(url('animals')) ?>" class="btn btn-outline-secondary btn-sm" title="Réinitialiser"><i class="bi bi-x-lg"></i></a>
                </div>
            </div>
            <a class="small d-inline-block mt-2" data-bs-toggle="collapse" href="#advanced" role="button"><i class="bi bi-sliders me-1"></i>Recherche avancée</a>
            <div class="collapse<?= $advancedOpen ? ' show' : '' ?>" id="advanced">
                <div class="row g-2 mt-1">
                    <div class="col-6 col-md-2"><label class="form-label">Race</label>
                        <select name="breed_id" class="form-select form-select-sm"><option value="">Toutes</option>
                            <?php foreach ($breeds as $b): ?><option value="<?= $b['id'] ?>"<?= selected($g['breed_id'] ?? '', $b['id']) ?>><?= e($b['name']) ?> (<?= e($b['species']) ?>)</option><?php endforeach; ?></select></div>
                    <div class="col-6 col-md-2"><label class="form-label">Lot</label>
                        <select name="lot_id" class="form-select form-select-sm"><option value="">Tous</option>
                            <?php foreach ($lots as $l): ?><option value="<?= $l['id'] ?>"<?= selected($g['lot_id'] ?? '', $l['id']) ?>><?= e($l['name']) ?></option><?php endforeach; ?></select></div>
                    <div class="col-6 col-md-2"><label class="form-label">Origine</label>
                        <select name="origin" class="form-select form-select-sm"><option value="">Toutes</option>
                            <?php foreach (labels('origin') as $k => $l): ?><option value="<?= $k ?>"<?= selected($g['origin'] ?? '', $k) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
                    <div class="col-6 col-md-1"><label class="form-label">Âge min (mois)</label><input type="number" min="0" name="age_min" class="form-control form-control-sm" value="<?= e($g['age_min'] ?? '') ?>"></div>
                    <div class="col-6 col-md-1"><label class="form-label">Âge max (mois)</label><input type="number" min="0" name="age_max" class="form-control form-control-sm" value="<?= e($g['age_max'] ?? '') ?>"></div>
                    <div class="col-6 col-md-2"><label class="form-label">Mère (n° boucle)</label><input name="mother" class="form-control form-control-sm" value="<?= e($g['mother'] ?? '') ?>" data-animal-search data-sex="F" data-all="1"></div>
                    <div class="col-6 col-md-2"><label class="form-label">Père (n° boucle)</label><input name="father" class="form-control form-control-sm" value="<?= e($g['father'] ?? '') ?>" data-animal-search data-sex="M" data-all="1"></div>
                    <div class="col-6 col-md-2"><label class="form-label">Né(e) du</label><input type="date" name="birth_from" class="form-control form-control-sm" value="<?= e($g['birth_from'] ?? '') ?>"></div>
                    <div class="col-6 col-md-2"><label class="form-label">au</label><input type="date" name="birth_to" class="form-control form-control-sm" value="<?= e($g['birth_to'] ?? '') ?>"></div>
                    <div class="col-6 col-md-2"><label class="form-label">Entré(e) du</label><input type="date" name="entry_from" class="form-control form-control-sm" value="<?= e($g['entry_from'] ?? '') ?>"></div>
                    <div class="col-6 col-md-2"><label class="form-label">au</label><input type="date" name="entry_to" class="form-control form-control-sm" value="<?= e($g['entry_to'] ?? '') ?>"></div>
                    <div class="col-6 col-md-2"><label class="form-label">Sorti(e) du</label><input type="date" name="exit_from" class="form-control form-control-sm" value="<?= e($g['exit_from'] ?? '') ?>"></div>
                    <div class="col-6 col-md-2"><label class="form-label">au</label><input type="date" name="exit_to" class="form-control form-control-sm" value="<?= e($g['exit_to'] ?? '') ?>"></div>
                </div>
            </div>
            <?php foreach (['sort', 'dir', 'per_page'] as $k): if (!empty($g[$k])): ?><input type="hidden" name="<?= $k ?>" value="<?= e($g[$k]) ?>"><?php endif; endforeach; ?>
        </form>
    </div>
</div>

<div class="print-only mb-2"><strong>Liste des animaux</strong> — imprimé le <?= date('d/m/Y H:i') ?></div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover table-sm mb-0">
            <thead><tr>
                <th><?= sort_link('tag', 'N° boucle') ?></th><th>Nom</th><th>Catégorie</th><th>Race</th><th>Lot</th>
                <th><?= sort_link('birth_date', 'Naissance') ?></th><th>Âge</th><th class="text-end">Dern. poids</th>
                <th><?= sort_link('entry_date', 'Entrée') ?></th><th>Statut</th><th class="table-actions d-print-none"><?= sort_link('id', '#') ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($page['rows'] as $a): ?>
                <tr>
                    <td class="fw-semibold"><a href="<?= e(url('animals/show', ['id' => $a['id']])) ?>"><?= e($a['tag']) ?></a></td>
                    <td><?= e($a['name']) ?></td>
                    <td><i class="bi <?= $a['sex'] === 'F' ? 'bi-gender-female text-danger' : 'bi-gender-male text-primary' ?> me-1"></i><?= e($a['category_label']) ?></td>
                    <td><?= e($a['breed_name']) ?></td>
                    <td><?= e($a['lot_name']) ?></td>
                    <td><?= fmt_date($a['birth_date']) ?></td>
                    <td class="small"><?= e(fmt_age($a['birth_date'], $a['exit_date'])) ?></td>
                    <td class="text-end"><?= $a['last_weight'] !== null ? fmt_num($a['last_weight']) . ' kg' : '' ?></td>
                    <td><?= fmt_date($a['entry_date']) ?></td>
                    <td><?= status_badge($a['status']) ?></td>
                    <td class="table-actions d-print-none">
                        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('animals/show', ['id' => $a['id']])) ?>" title="Fiche"><i class="bi bi-eye"></i></a>
                        <?php if (can('animals.edit')): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('animals/edit', ['id' => $a['id']])) ?>" title="Modifier"><i class="bi bi-pencil"></i></a><?php endif; ?>
                        <?php if (can('animals.export')): ?><a class="btn btn-sm btn-outline-danger" target="_blank" href="<?= e(url('animals/pdf', ['id' => $a['id']])) ?>" title="Fiche PDF"><i class="bi bi-file-earmark-pdf"></i></a><?php endif; ?>
                        <?php if (can('animals.delete')): ?>
                            <?php $action = url('animals/delete'); $id = $a['id']; $message = 'Supprimer définitivement ' . $a['tag'] . ' et tous ses suivis ?'; include __DIR__ . '/../partials/delete_button.php'; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$page['rows']): ?><tr><td colspan="11" class="text-center text-muted py-4">Aucun animal ne correspond aux critères.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/../partials/pager.php'; ?>
