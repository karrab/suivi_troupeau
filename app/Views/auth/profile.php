<h1 class="h4 page-title mb-3"><i class="bi bi-person-circle me-2"></i>Mon profil</h1>
<div class="row g-3">
    <div class="col-md-5">
        <div class="card"><div class="card-body">
            <dl class="row mb-0">
                <dt class="col-5">Identifiant</dt><dd class="col-7"><?= e($user['username']) ?></dd>
                <dt class="col-5">Nom</dt><dd class="col-7"><?= e($user['full_name']) ?></dd>
                <dt class="col-5">E-mail</dt><dd class="col-7"><?= e($user['email']) ?></dd>
                <dt class="col-5">Rôle</dt><dd class="col-7"><?= e($user['role_name']) ?></dd>
            </dl>
        </div></div>
    </div>
    <div class="col-md-7">
        <div class="card"><div class="card-header">Changer mon mot de passe</div><div class="card-body">
            <form method="post" action="<?= e(url('profile/password')) ?>">
                <?= csrf_field() ?>
                <?php foreach (['current' => 'Mot de passe actuel', 'password' => 'Nouveau mot de passe (8 car. min.)', 'confirm' => 'Confirmation'] as $f => $l): ?>
                    <div class="mb-2">
                        <label class="form-label" for="<?= $f ?>"><?= e($l) ?></label>
                        <input type="password" class="form-control<?= invalid($errors, $f) ?>" id="<?= $f ?>" name="<?= $f ?>" required>
                        <?= field_error($errors, $f) ?>
                    </div>
                <?php endforeach; ?>
                <button class="btn btn-forest mt-2"><i class="bi bi-check2 me-1"></i>Enregistrer</button>
            </form>
        </div></div>
    </div>
</div>
