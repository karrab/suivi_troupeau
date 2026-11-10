<?php $title = 'Utilisateurs'; $g = $_GET; ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 page-title mb-0"><i class="bi bi-people me-2"></i>Utilisateurs <span class="badge text-bg-light"><?= (int) $total ?></span></h1>
    <div class="d-flex gap-2 d-print-none">
        <?php $exportRoute = 'users/export'; $exportPerm = 'users.export'; include __DIR__ . '/../partials/list_actions.php'; ?>
        <?php if (can('users.create')): ?><a href="<?= e(url('users/create')) ?>" class="btn btn-forest btn-sm"><i class="bi bi-person-plus me-1"></i>Nouvel utilisateur</a><?php endif; ?>
    </div>
</div>
<div class="card filters-card mb-3 d-print-none"><div class="card-body">
    <form method="get" action="<?= e(url()) ?>" class="row g-2 align-items-end">
        <input type="hidden" name="r" value="users">
        <div class="col-md-4"><label class="form-label">Recherche</label><input type="search" name="q" class="form-control form-control-sm" value="<?= e($g['q'] ?? '') ?>"></div>
        <div class="col-md-3"><label class="form-label">Rôle</label><select name="role_id" class="form-select form-select-sm"><option value="">Tous</option>
            <?php foreach ($roles as $id => $n): ?><option value="<?= $id ?>"<?= selected($g['role_id'] ?? '', $id) ?>><?= e($n) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><label class="form-label">Actif</label><select name="active" class="form-select form-select-sm"><option value="">Tous</option>
            <option value="1"<?= selected($g['active'] ?? '', '1') ?>>Oui</option><option value="0"<?= selected($g['active'] ?? '', '0') ?>>Non</option></select></div>
        <div class="col-md-auto"><button class="btn btn-forest btn-sm"><i class="bi bi-search"></i></button></div>
    </form>
</div></div>
<div class="card"><div class="table-responsive"><table class="table table-sm table-hover mb-0">
    <thead><tr><th>Identifiant</th><th>Nom complet</th><th>E-mail</th><th>Rôle</th><th>Actif</th><th>Dernière connexion</th><th class="table-actions d-print-none"></th></tr></thead>
    <tbody>
    <?php foreach ($page['rows'] as $u): ?>
        <tr>
            <td class="fw-semibold"><?= e($u['username']) ?><?= $u['locked_until'] && strtotime($u['locked_until']) > time() ? ' <span class="badge text-bg-danger">verrouillé</span>' : '' ?></td>
            <td><?= e($u['full_name']) ?></td><td><?= e($u['email']) ?></td><td><span class="badge text-bg-secondary"><?= e($u['role_name']) ?></span></td>
            <td><?= $u['is_active'] ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle text-danger"></i>' ?></td>
            <td><?= fmt_date($u['last_login_at'], true) ?></td>
            <td class="table-actions d-print-none">
                <?php if (can('audit.view')): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('audit', ['user_id' => $u['id']])) ?>" title="Traces"><i class="bi bi-journal-text"></i></a><?php endif; ?>
                <?php if (can('users.edit')): ?><a class="btn btn-sm btn-outline-primary" href="<?= e(url('users/edit', ['id' => $u['id']])) ?>" title="Modifier"><i class="bi bi-pencil"></i></a><?php endif; ?>
                <?php if (can('users.delete')): $action = url('users/delete'); $id = $u['id']; $message = 'Supprimer l\'utilisateur ' . $u['username'] . ' ?'; include __DIR__ . '/../partials/delete_button.php'; endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div></div>
<?php include __DIR__ . '/../partials/pager.php'; ?>
