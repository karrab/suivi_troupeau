<?php $title = $isEdit ? 'Modifier l\'utilisateur' : 'Nouvel utilisateur'; $err = $errors; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 page-title mb-0"><i class="bi bi-person-gear me-2"></i><?= e($title) ?></h1>
    <a href="<?= e(url('users')) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Retour</a>
</div>
<form method="post" action="<?= e(url($isEdit ? 'users/update' : 'users/store')) ?>" novalidate>
    <?= csrf_field() ?>
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $user['id'] ?>"><?php endif; ?>
    <div class="card"><div class="card-body row g-3">
        <div class="col-md-4"><label class="form-label">Identifiant *</label><input name="username" class="form-control<?= invalid($err, 'username') ?>" value="<?= e(fv($user, 'username')) ?>" required autocomplete="off"><?= field_error($err, 'username') ?></div>
        <div class="col-md-4"><label class="form-label">Nom complet *</label><input name="full_name" class="form-control<?= invalid($err, 'full_name') ?>" value="<?= e(fv($user, 'full_name')) ?>" required><?= field_error($err, 'full_name') ?></div>
        <div class="col-md-4"><label class="form-label">E-mail</label><input type="email" name="email" class="form-control<?= invalid($err, 'email') ?>" value="<?= e(fv($user, 'email')) ?>"><?= field_error($err, 'email') ?></div>
        <div class="col-md-4"><label class="form-label">Rôle *</label><select name="role_id" class="form-select<?= invalid($err, 'role_id') ?>">
            <?php foreach ($roles as $id => $n): ?><option value="<?= $id ?>"<?= selected(fv($user, 'role_id'), $id) ?>><?= e($n) ?></option><?php endforeach; ?></select><?= field_error($err, 'role_id') ?></div>
        <div class="col-md-4"><label class="form-label">Mot de passe <?= $isEdit ? '(laisser vide pour conserver)' : '*' ?></label><input type="password" name="password" class="form-control<?= invalid($err, 'password') ?>" autocomplete="new-password"><?= field_error($err, 'password') ?></div>
        <div class="col-md-4"><label class="form-label">Confirmation</label><input type="password" name="password_confirm" class="form-control" autocomplete="new-password"></div>
        <div class="col-md-4"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="act"<?= checked(fv($user, 'is_active', 1)) ?>><label class="form-check-label" for="act">Compte actif</label></div><?= field_error($err, 'is_active') ?></div>
        <?php if ($isEdit && !empty($user['locked_until'])): ?>
            <div class="col-md-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="unlock" value="1" id="unl"><label class="form-check-label" for="unl">Déverrouiller le compte</label></div></div>
        <?php endif; ?>
    </div></div>
    <button class="btn btn-forest mt-3"><i class="bi bi-check2 me-1"></i>Enregistrer</button>
</form>
