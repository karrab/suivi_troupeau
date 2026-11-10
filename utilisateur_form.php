<?php
/** Ajout / modification d'un utilisateur. */
require __DIR__ . '/inc/init.php';

$id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
exiger_permission($id ? 'utilisateurs.modifier' : 'utilisateurs.ajouter');
$ancien = $id ? (bd_trouver('utilisateurs', $id) ?? abandonner(404, 'Utilisateur introuvable.')) : null;

if (est_post()) {
    [$d, $err] = valider($_POST, [
        'identifiant' => 'obligatoire|maxcar:60|motif:/^[A-Za-z0-9_.-]+$/', 'nom_complet' => 'obligatoire|maxcar:120',
        'email' => 'email|maxcar:160', 'role_id' => 'obligatoire|entier', 'actif' => 'case',
        'mot_de_passe' => ($ancien ? '' : 'obligatoire|') . 'mincar:8|maxcar:100',
    ], ['identifiant' => 'Identifiant', 'nom_complet' => 'Nom complet', 'email' => 'E-mail', 'role_id' => 'Rôle', 'mot_de_passe' => 'Mot de passe']);
    foreach (['identifiant' => 'Cet identifiant', 'email' => 'Cet e-mail'] as $k => $l) {
        if ($d[$k] && ($doublon = bd_valeur("SELECT id FROM utilisateurs WHERE $k = :v", ['v' => $d[$k]])) && (int) $doublon !== $id) {
            $err[$k] = "$l est déjà utilisé.";
        }
    }
    if (!bd_valeur('SELECT id FROM roles WHERE id = :id', ['id' => (int) $d['role_id']])) {
        $err['role_id'] = 'Rôle invalide.';
    }
    if ($id && $id === utilisateur_id() && !$d['actif']) {
        $err['actif'] = 'Vous ne pouvez pas désactiver votre propre compte.';
    }
    if (!empty($d['mot_de_passe']) && $d['mot_de_passe'] !== ($_POST['confirmation'] ?? '')) {
        $err['mot_de_passe'] = 'La confirmation du mot de passe ne correspond pas.';
    }
    if ($err) {
        unset($_POST['mot_de_passe'], $_POST['confirmation']);
        garder_saisie($_POST, $err);
        rediriger(url('utilisateur_form.php', ['id' => $id ?: null]));
    }
    $donnees = ['identifiant' => $d['identifiant'], 'nom_complet' => $d['nom_complet'], 'email' => $d['email'] ?: null,
                'role_id' => (int) $d['role_id'], 'actif' => (int) $d['actif']];
    if (!empty($d['mot_de_passe'])) {
        $donnees['mot_de_passe'] = password_hash((string) $d['mot_de_passe'], PASSWORD_DEFAULT);
    }
    if ($ancien) {
        if (!empty($_POST['debloquer'])) {
            $donnees['bloque_jusqua'] = null;
            $donnees['tentatives_echouees'] = 0;
        }
        bd_modifier('utilisateurs', $donnees, $id);
        tracer('modification', 'utilisateur', $id, 'Modification de l\'utilisateur ' . $donnees['identifiant'], $ancien, $donnees);
        flash('success', 'Utilisateur modifié.');
    } else {
        $id = bd_inserer('utilisateurs', $donnees);
        tracer('ajout', 'utilisateur', $id, 'Ajout de l\'utilisateur ' . $donnees['identifiant'], null, $donnees);
        flash('success', 'Utilisateur créé.');
    }
    rediriger('utilisateurs.php');
}

$u = $ancien ?? ['actif' => 1];
$err = erreurs_form();
$titre = $ancien ? 'Modifier l\'utilisateur' : 'Nouvel utilisateur';
require INC . '/entete.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 titre-page mb-0"><i class="bi bi-person-gear me-2"></i><?= e($titre) ?></h1>
    <a href="utilisateurs.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Retour</a>
</div>
<form method="post" action="utilisateur_form.php" novalidate><?= champ_csrf() ?>
    <?php if ($ancien): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
    <div class="card"><div class="card-body row g-3">
        <div class="col-md-4"><label class="form-label">Identifiant *</label><input name="identifiant" class="form-control<?= invalide($err, 'identifiant') ?>" value="<?= e(fv($u, 'identifiant')) ?>" autocomplete="off"><?= erreur_champ($err, 'identifiant') ?></div>
        <div class="col-md-4"><label class="form-label">Nom complet *</label><input name="nom_complet" class="form-control<?= invalide($err, 'nom_complet') ?>" value="<?= e(fv($u, 'nom_complet')) ?>"><?= erreur_champ($err, 'nom_complet') ?></div>
        <div class="col-md-4"><label class="form-label">E-mail</label><input type="email" name="email" class="form-control<?= invalide($err, 'email') ?>" value="<?= e(fv($u, 'email')) ?>"><?= erreur_champ($err, 'email') ?></div>
        <div class="col-md-4"><label class="form-label">Rôle *</label><select name="role_id" class="form-select<?= invalide($err, 'role_id') ?>"><?= options(bd_paires('SELECT id, nom FROM roles ORDER BY nom'), fv($u, 'role_id')) ?></select><?= erreur_champ($err, 'role_id') ?></div>
        <div class="col-md-4"><label class="form-label">Mot de passe <?= $ancien ? '(vide = inchangé)' : '*' ?></label><input type="password" name="mot_de_passe" class="form-control<?= invalide($err, 'mot_de_passe') ?>" autocomplete="new-password"><?= erreur_champ($err, 'mot_de_passe') ?></div>
        <div class="col-md-4"><label class="form-label">Confirmation</label><input type="password" name="confirmation" class="form-control" autocomplete="new-password"></div>
        <div class="col-md-4"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="actif" value="1" id="actif"<?= coche(fv($u, 'actif', 1)) ?>><label class="form-check-label" for="actif">Compte actif</label></div><?= erreur_champ($err, 'actif') ?></div>
        <?php if ($ancien && $ancien['bloque_jusqua']): ?>
            <div class="col-md-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="debloquer" value="1" id="debloquer"><label class="form-check-label" for="debloquer">Débloquer le compte</label></div></div>
        <?php endif; ?>
    </div></div>
    <button class="btn btn-foret mt-3"><i class="bi bi-check2 me-1"></i>Enregistrer</button>
</form>
<?php require INC . '/pied.php';
