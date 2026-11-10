<?php
/** Profil de l'utilisateur connecté : changement de mot de passe. */
require __DIR__ . '/inc/init.php';
exiger_connexion();
$u = utilisateur();

if (est_post()) {
    [$d, $err] = valider($_POST, ['actuel' => 'obligatoire', 'nouveau' => 'obligatoire|mincar:8|maxcar:100', 'confirmation' => 'obligatoire'],
        ['actuel' => 'Mot de passe actuel', 'nouveau' => 'Nouveau mot de passe', 'confirmation' => 'Confirmation']);
    $hash = (string) bd_valeur('SELECT mot_de_passe FROM utilisateurs WHERE id = :id', ['id' => $u['id']]);
    if (!$err && !password_verify((string) $d['actuel'], $hash)) {
        $err['actuel'] = 'Mot de passe actuel incorrect.';
    }
    if (!$err && $d['nouveau'] !== $d['confirmation']) {
        $err['confirmation'] = 'La confirmation ne correspond pas.';
    }
    if ($err) {
        garder_saisie([], $err);
    } else {
        bd_requete('UPDATE utilisateurs SET mot_de_passe = :h WHERE id = :id', ['h' => password_hash((string) $d['nouveau'], PASSWORD_DEFAULT), 'id' => $u['id']]);
        tracer('modification', 'utilisateur', (int) $u['id'], 'Changement de son mot de passe');
        flash('success', 'Mot de passe modifié.');
    }
    rediriger('profil.php');
}

$erreurs = erreurs_form();
$titre = 'Mon profil';
require INC . '/entete.php';
?>
<h1 class="h4 titre-page mb-3"><i class="bi bi-person-circle me-2"></i>Mon profil</h1>
<div class="row g-3">
    <div class="col-md-5"><div class="card"><div class="card-body"><dl class="row mb-0">
        <dt class="col-5">Identifiant</dt><dd class="col-7"><?= e($u['identifiant']) ?></dd>
        <dt class="col-5">Nom</dt><dd class="col-7"><?= e($u['nom_complet']) ?></dd>
        <dt class="col-5">E-mail</dt><dd class="col-7"><?= e($u['email']) ?></dd>
        <dt class="col-5">Rôle</dt><dd class="col-7"><?= e($u['role']) ?></dd>
    </dl></div></div></div>
    <div class="col-md-7"><div class="card"><div class="card-header">Changer mon mot de passe</div><div class="card-body">
        <form method="post" action="profil.php"><?= champ_csrf() ?>
            <?php foreach (['actuel' => 'Mot de passe actuel', 'nouveau' => 'Nouveau mot de passe (8 caractères min.)', 'confirmation' => 'Confirmation'] as $c => $l): ?>
                <div class="mb-2"><label class="form-label" for="<?= $c ?>"><?= e($l) ?></label>
                    <input type="password" class="form-control<?= invalide($erreurs, $c) ?>" id="<?= $c ?>" name="<?= $c ?>" required><?= erreur_champ($erreurs, $c) ?></div>
            <?php endforeach; ?>
            <button class="btn btn-foret mt-2"><i class="bi bi-check2 me-1"></i>Enregistrer</button>
        </form>
    </div></div></div>
</div>
<?php require INC . '/pied.php';
