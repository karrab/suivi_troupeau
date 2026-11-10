<?php
/** Page de connexion. */
require __DIR__ . '/inc/init.php';

if (est_connecte()) {
    rediriger('index.php');
}
$erreur = null;
if (est_post()) {
    $identifiant = trim((string) ($_POST['identifiant'] ?? ''));
    $resultat = $identifiant !== '' ? connecter($identifiant, (string) ($_POST['mot_de_passe'] ?? '')) : 'Identifiant obligatoire.';
    if ($resultat === true) {
        $vers = $_SESSION['page_demandee'] ?? '';
        unset($_SESSION['page_demandee']);
        // uniquement une page locale de l'application (évite les redirections ouvertes)
        rediriger(preg_match('/^[a-z_]+\.php(\?[^\s]*)?$/', $vers) && !str_starts_with($vers, 'connexion.php') ? $vers : 'index.php');
    }
    $erreur = $resultat;
}
$titre = 'Connexion';
require INC . '/entete_simple.php';
?>
<div class="row justify-content-center align-items-center min-vh-100">
    <div class="col-sm-9 col-md-6 col-lg-4">
        <div class="card shadow-lg"><div class="card-body p-4">
            <div class="text-center mb-4">
                <i class="bi bi-flower3 display-5 text-foret"></i>
                <h1 class="h4 mt-2 mb-0"><?= e(parametre('nom_exploitation', 'Suivi Troupeau')) ?></h1>
                <p class="text-muted small">Suivi du troupeau caprin & ovin</p>
            </div>
            <?php if ($erreur): ?><div class="alert alert-danger py-2"><?= e($erreur) ?></div><?php endif; ?>
            <form method="post" action="connexion.php" novalidate>
                <?= champ_csrf() ?>
                <div class="mb-3"><label class="form-label" for="identifiant">Identifiant</label>
                    <div class="input-group"><span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input class="form-control" id="identifiant" name="identifiant" required autofocus autocomplete="username" value="<?= e($_POST['identifiant'] ?? '') ?>"></div></div>
                <div class="mb-3"><label class="form-label" for="mot_de_passe">Mot de passe</label>
                    <div class="input-group"><span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input class="form-control" type="password" id="mot_de_passe" name="mot_de_passe" required autocomplete="current-password"></div></div>
                <button class="btn btn-foret w-100" type="submit"><i class="bi bi-box-arrow-in-right me-1"></i>Se connecter</button>
            </form>
        </div></div>
    </div>
</div>
<?php require INC . '/pied_simple.php';
