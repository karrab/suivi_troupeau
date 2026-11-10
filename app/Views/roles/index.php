<?php $title = 'Rôles'; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 page-title mb-0"><i class="bi bi-shield-lock me-2"></i>Rôles & permissions</h1>
    <div class="d-print-none"><button class="btn btn-outline-secondary btn-sm" data-print><i class="bi bi-printer me-1"></i>Imprimer</button>
    <?php if (can('roles.manage')): ?><a href="<?= e(url('roles/form')) ?>" class="btn btn-forest btn-sm"><i class="bi bi-plus-lg me-1"></i>Nouveau rôle</a><?php endif; ?></div>
</div>
<div class="card"><table class="table table-sm table-hover mb-0">
    <thead><tr><th>Rôle</th><th>Description</th><th>Accès</th><th class="text-end">Utilisateurs</th><th class="table-actions d-print-none"></th></tr></thead>
    <tbody><?php foreach ($roles as $r): ?>
        <tr><td class="fw-semibold"><?= e($r['name']) ?></td><td><?= e($r['description']) ?></td>
            <td><?= $r['is_super'] ? '<span class="badge text-bg-danger">Accès complet</span>' : '<span class="badge text-bg-secondary">' . (int) $r['nb_perms'] . ' permission(s)</span>' ?></td>
            <td class="text-end"><?= (int) $r['nb_users'] ?></td>
            <td class="table-actions d-print-none"><?php if (can('roles.manage')): ?>
                <a class="btn btn-sm btn-outline-primary" href="<?= e(url('roles/form', ['id' => $r['id']])) ?>"><i class="bi bi-pencil"></i></a>
                <?php $action = url('roles/delete'); $id = $r['id']; $message = 'Supprimer le rôle ' . $r['name'] . ' ?'; include __DIR__ . '/../partials/delete_button.php'; ?>
            <?php endif; ?></td></tr>
    <?php endforeach; ?></tbody>
</table></div>
