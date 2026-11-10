<?php
/** Liste des rôles ; suppression (POST) si aucun utilisateur ne l'utilise. */
require __DIR__ . '/inc/init.php';
exiger_permission('roles.voir');

if (est_post() && ($_POST['action'] ?? '') === 'supprimer') {
    exiger_permission('roles.gerer');
    $id = (int) ($_POST['id'] ?? 0);
    $ancien = bd_trouver('roles', $id) ?? abandonner(404);
    if ((int) bd_valeur('SELECT COUNT(*) FROM utilisateurs WHERE role_id = :r', ['r' => $id]) > 0) {
        flash('danger', 'Impossible de supprimer un rôle attribué à des utilisateurs.');
    } else {
        bd_supprimer('roles', $id);
        tracer('suppression', 'role', $id, 'Suppression du rôle ' . $ancien['nom'], $ancien, null);
        cache_invalider('permissions');
        flash('success', 'Rôle supprimé.');
    }
    rediriger('roles.php');
}

$roles = bd_tous('SELECT r.*, (SELECT COUNT(*) FROM utilisateurs u WHERE u.role_id = r.id) AS nb_utilisateurs,
                         (SELECT COUNT(*) FROM roles_permissions rp WHERE rp.role_id = r.id) AS nb_permissions
                  FROM roles r ORDER BY r.acces_complet DESC, r.nom');
$titre = 'Rôles';
require INC . '/entete.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 titre-page mb-0"><i class="bi bi-shield-lock me-2"></i>Rôles & permissions</h1>
    <div class="d-print-none"><button class="btn btn-outline-secondary btn-sm" data-imprimer><i class="bi bi-printer me-1"></i>Imprimer</button>
        <?php if (peut('roles.gerer')): ?><a href="role_form.php" class="btn btn-foret btn-sm"><i class="bi bi-plus-lg me-1"></i>Nouveau rôle</a><?php endif; ?></div>
</div>
<div class="card"><table class="table table-sm table-hover mb-0">
    <thead><tr><th>Rôle</th><th>Description</th><th>Accès</th><th class="text-end">Utilisateurs</th><th class="actions d-print-none"></th></tr></thead>
    <tbody><?php foreach ($roles as $r): ?>
        <tr><td class="fw-semibold"><?= e($r['nom']) ?></td><td><?= e($r['description']) ?></td>
            <td><?= $r['acces_complet'] ? '<span class="badge text-bg-danger">Accès complet</span>' : '<span class="badge text-bg-secondary">' . (int) $r['nb_permissions'] . ' permission(s)</span>' ?></td>
            <td class="text-end"><?= (int) $r['nb_utilisateurs'] ?></td>
            <td class="actions d-print-none"><?php if (peut('roles.gerer')): ?>
                <a class="btn btn-sm btn-outline-primary" href="<?= e(url('role_form.php', ['id' => $r['id']])) ?>"><i class="bi bi-pencil"></i></a>
                <?= bouton_supprimer('roles.php', (int) $r['id'], 'Supprimer le rôle ' . $r['nom'] . ' ?') ?>
            <?php endif; ?></td></tr>
    <?php endforeach; ?></tbody>
</table></div>
<?php require INC . '/pied.php';
