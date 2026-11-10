<?php
/** Ajout / modification d'un rôle et de sa matrice de permissions. */
require __DIR__ . '/inc/init.php';
exiger_permission('roles.gerer');

$id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
$ancien = $id ? (bd_trouver('roles', $id) ?? abandonner(404)) : null;
$codesRole = static fn(int $rid) => bd_colonne('SELECT p.code FROM roles_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = :r ORDER BY p.code', ['r' => $rid]);

if (est_post()) {
    [$d, $err] = valider($_POST, ['nom' => 'obligatoire|maxcar:60', 'description' => 'maxcar:255', 'acces_complet' => 'case'], ['nom' => 'Nom', 'description' => 'Description']);
    $doublon = bd_valeur('SELECT id FROM roles WHERE nom = :n', ['n' => (string) $d['nom']]);
    if ($doublon && (int) $doublon !== $id) {
        $err['nom'] = 'Ce nom de rôle existe déjà.';
    }
    // On ne retire pas l'accès complet à son propre rôle (risque de blocage)
    if ($ancien && (int) $ancien['acces_complet'] && !$d['acces_complet'] && (int) utilisateur()['role_id'] === $id) {
        $err['acces_complet'] = 'Vous ne pouvez pas retirer l\'accès complet à votre propre rôle.';
    }
    if ($err) {
        garder_saisie($_POST, $err);
        rediriger(url('role_form.php', ['id' => $id ?: null]));
    }
    $perms = array_values(array_filter((array) ($_POST['permissions'] ?? []), 'is_string'));
    sort($perms);
    $avant = $id ? $codesRole($id) : [];
    bd_transaction(static function () use (&$id, $d, $perms) {
        $donnees = ['nom' => $d['nom'], 'description' => $d['description'] ?: null, 'acces_complet' => (int) $d['acces_complet']];
        if ($id) {
            bd_modifier('roles', $donnees, $id);
        } else {
            $id = bd_inserer('roles', $donnees);
        }
        bd_requete('DELETE FROM roles_permissions WHERE role_id = :r', ['r' => $id]);
        if ($perms) {
            foreach (bd_colonne('SELECT id FROM permissions WHERE code IN (' . implode(',', array_fill(0, count($perms), '?')) . ')', $perms) as $pid) {
                bd_requete('INSERT INTO roles_permissions (role_id, permission_id) VALUES (:r, :p)', ['r' => $id, 'p' => (int) $pid]);
            }
        }
    });
    tracer($ancien ? 'modification' : 'ajout', 'role', $id, 'Rôle ' . $d['nom'],
        ($ancien ?? []) + ['permissions' => implode(', ', $avant)],
        ['nom' => $d['nom'], 'description' => $d['description'], 'acces_complet' => (int) $d['acces_complet'], 'permissions' => implode(', ', $perms)]);
    cache_invalider('permissions');
    flash('success', 'Rôle enregistré.');
    rediriger('roles.php');
}

$role = $ancien ?? ['acces_complet' => 0];
$accordees = $id ? array_flip($codesRole($id)) : [];
$err = erreurs_form();
$titre = $ancien ? 'Rôle ' . $ancien['nom'] : 'Nouveau rôle';
require INC . '/entete.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 titre-page mb-0"><i class="bi bi-shield-lock me-2"></i><?= e($titre) ?></h1>
    <a href="roles.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Retour</a>
</div>
<form method="post" action="role_form.php"><?= champ_csrf() ?>
    <?php if ($ancien): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
    <div class="card mb-3"><div class="card-body row g-3">
        <div class="col-md-4"><label class="form-label">Nom *</label><input name="nom" class="form-control<?= invalide($err, 'nom') ?>" value="<?= e(fv($role, 'nom')) ?>"><?= erreur_champ($err, 'nom') ?></div>
        <div class="col-md-6"><label class="form-label">Description</label><input name="description" class="form-control" value="<?= e(fv($role, 'description')) ?>"></div>
        <div class="col-md-2 d-flex align-items-end"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="acces_complet" value="1" id="complet"<?= coche(fv($role, 'acces_complet')) ?>>
            <label class="form-check-label" for="complet">Accès complet</label></div></div>
        <?= erreur_champ($err, 'acces_complet') ?>
    </div></div>
    <div class="card"><div class="card-header">Matrice des permissions <span class="small text-muted fw-normal">(ignorée si « accès complet »)</span></div>
        <div class="table-responsive"><table class="table table-sm table-hover mb-0 align-middle">
            <thead><tr><th>Module</th><th class="text-center">Tout</th><th>Actions</th></tr></thead>
            <tbody><?php foreach (catalogue_permissions() as $module => $m): ?>
                <tr><td class="fw-semibold"><?= e($m['libelle']) ?></td>
                    <td class="text-center"><input type="checkbox" class="form-check-input" data-cocher-ligne="<?= e($module) ?>" aria-label="Tout cocher"></td>
                    <td><?php foreach ($m['actions'] as $action => $lib): $code = "$module.$action"; ?>
                        <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="permissions[]" value="<?= e($code) ?>" id="p_<?= e($code) ?>" data-module="<?= e($module) ?>"<?= coche(isset($accordees[$code])) ?>>
                            <label class="form-check-label small" for="p_<?= e($code) ?>"><?= e($lib) ?></label></div>
                    <?php endforeach; ?></td></tr>
            <?php endforeach; ?></tbody>
        </table></div></div>
    <button class="btn btn-foret mt-3"><i class="bi bi-check2 me-1"></i>Enregistrer</button>
</form>
<?php require INC . '/pied.php';
