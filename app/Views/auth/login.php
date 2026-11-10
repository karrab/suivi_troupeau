<div class="container">
    <div class="row justify-content-center align-items-center min-vh-100">
        <div class="col-sm-9 col-md-6 col-lg-4">
            <div class="card shadow-lg">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <i class="bi bi-flower3 display-5 text-forest"></i>
                        <h1 class="h4 mt-2 mb-0"><?= e(setting('nom_exploitation', 'Suivi Troupeau')) ?></h1>
                        <p class="text-muted small">Système de suivi du troupeau caprin & ovin</p>
                    </div>
                    <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
                    <form method="post" action="<?= e(url('login')) ?>" novalidate>
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label" for="username">Identifiant</label>
                            <div class="input-group"><span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input class="form-control" id="username" name="username" required autofocus autocomplete="username" value="<?= e($_POST['username'] ?? '') ?>"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="password">Mot de passe</label>
                            <div class="input-group"><span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input class="form-control" type="password" id="password" name="password" required autocomplete="current-password"></div>
                        </div>
                        <button class="btn btn-forest w-100" type="submit"><i class="bi bi-box-arrow-in-right me-1"></i>Se connecter</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
