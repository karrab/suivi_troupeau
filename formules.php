<?php
/** Liste des indices et de leurs formules (Paramètres > Formules des indices). */
require __DIR__ . '/inc/init.php';
exiger_permission('indices.voir');

$indices = bd_tous('SELECT i.*, (SELECT COUNT(*) FROM resultats_indices r WHERE r.indice_id = i.id) AS nb_resultats
                    FROM indices i ORDER BY i.portee DESC, i.ordre, i.id');
$titre = 'Formules des indices';
require INC . '/entete.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 titre-page mb-0"><i class="bi bi-calculator me-2"></i>Définition et formules des indices</h1>
    <div class="d-flex gap-2 d-print-none">
        <button class="btn btn-outline-secondary btn-sm" data-imprimer><i class="bi bi-printer me-1"></i>Imprimer</button>
        <?php if (peut('indices.formules')): ?>
            <a class="btn btn-foret btn-sm" href="formule_form.php?portee=troupeau"><i class="bi bi-plus-lg me-1"></i>Indice troupeau</a>
            <a class="btn btn-foret btn-sm" href="formule_form.php?portee=animal"><i class="bi bi-plus-lg me-1"></i>Indice animal</a>
        <?php endif; ?>
    </div>
</div>
<?php foreach (['troupeau' => 'Indices du troupeau (par année et par groupe)', 'animal' => 'Indices par animal'] as $portee => $lib): ?>
    <div class="card mb-3"><div class="card-header"><?= e($lib) ?></div><div class="table-responsive"><table class="table table-sm table-hover mb-0">
        <thead><tr><th>Code</th><th>Nom</th><th>Formule</th><th>Unité</th><th>Seuils (bas / haut)</th><th>Sens</th><th>Actif</th><th class="text-end">Résultats</th><th class="actions d-print-none"></th></tr></thead>
        <tbody><?php foreach ($indices as $i): if ($i['portee'] !== $portee) continue; ?>
            <tr class="<?= $i['actif'] ? '' : 'text-muted' ?>"><td><code><?= e($i['code']) ?></code></td><td><?= e($i['nom']) ?></td>
                <td><code class="formule small"><?= e($i['formule']) ?></code></td><td><?= e($i['unite']) ?></td>
                <td class="text-nowrap"><?= $i['seuil_bas'] !== null ? fmt_nombre($i['seuil_bas'], (int) $i['decimales']) : '—' ?> / <?= $i['seuil_haut'] !== null ? fmt_nombre($i['seuil_haut'], (int) $i['decimales']) : '—' ?></td>
                <td><i class="bi <?= $i['sens'] === 'haut' ? 'bi-arrow-up' : 'bi-arrow-down' ?> text-success" title="<?= e(libelle('sens', $i['sens'])) ?>"></i></td>
                <td><?= $i['actif'] ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle text-muted"></i>' ?></td>
                <td class="text-end"><?= (int) $i['nb_resultats'] ?></td>
                <td class="actions d-print-none"><?php if (peut('indices.formules')): ?>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('formule_form.php', ['id' => $i['id']])) ?>" title="Modifier la formule"><i class="bi bi-pencil"></i></a>
                    <?= bouton_supprimer('formule_form.php', (int) $i['id'], 'Supprimer l\'indice « ' . $i['nom'] . ' » et ses ' . $i['nb_resultats'] . ' résultat(s) ?') ?>
                <?php endif; ?></td></tr>
        <?php endforeach; ?></tbody>
    </table></div></div>
<?php endforeach; ?>
<?php require INC . '/pied.php';
