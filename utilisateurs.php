<?php
/** Gestion des utilisateurs : liste, filtres, keyset, PDF (?pdf=1), suppression (POST). */
require __DIR__ . '/inc/init.php';
require INC . '/pdf.php';
exiger_permission('utilisateurs.voir');

if (est_post() && ($_POST['action'] ?? '') === 'supprimer') {
    exiger_permission('utilisateurs.supprimer');
    $id = (int) ($_POST['id'] ?? 0);
    if ($id === utilisateur_id()) {
        flash('danger', 'Vous ne pouvez pas supprimer votre propre compte.');
        rediriger('utilisateurs.php');
    }
    $ancien = bd_trouver('utilisateurs', $id) ?? abandonner(404);
    bd_supprimer('utilisateurs', $id);
    tracer('suppression', 'utilisateur', $id, 'Suppression de l\'utilisateur ' . $ancien['identifiant'], $ancien, null);
    flash('success', 'Utilisateur supprimé.');
    rediriger('utilisateurs.php');
}

$where = [];
$p = [];
if (($q = trim((string) ($_GET['q'] ?? ''))) !== '') {
    $where[] = '(u.identifiant LIKE :q OR u.nom_complet LIKE :q OR u.email LIKE :q)';
    $p['q'] = '%' . $q . '%';
}
if (!empty($_GET['role_id']) && ctype_digit((string) $_GET['role_id'])) {
    $where[] = 'u.role_id = :r';
    $p['r'] = (int) $_GET['role_id'];
}
if (isset($_GET['actif']) && in_array($_GET['actif'], ['0', '1'], true)) {
    $where[] = 'u.actif = :a';
    $p['a'] = (int) $_GET['actif'];
}
$requete = [
    'select' => 'u.id, u.identifiant, u.nom_complet, u.email, u.actif, u.derniere_connexion, u.bloque_jusqua, r.nom AS role',
    'from' => 'FROM utilisateurs u JOIN roles r ON r.id = u.role_id', 'where' => $where, 'params' => $p,
    'tri' => 'u.id', 'cle_tri' => 'id', 'id' => 'u.id', 'sens' => 'ASC', 'par_page' => par_page(),
];

if (!empty($_GET['pdf'])) {
    exiger_permission('utilisateurs.exporter');
    tracer('export', 'utilisateur', null, 'Export PDF des utilisateurs');
    $pdf = new PdfRapport('Utilisateurs', resume_filtres(['q' => 'Recherche', 'role_id' => 'Rôle n°', 'actif' => 'Actif']));
    $pdf->ajouterPage();
    $pdf->tableau([['label' => 'Identifiant', 'key' => 'identifiant'], ['label' => 'Nom complet', 'key' => 'nom_complet', 'w' => 1.5],
        ['label' => 'E-mail', 'key' => 'email', 'w' => 1.6], ['label' => 'Rôle', 'key' => 'role'], ['label' => 'Actif', 'key' => 'a', 'w' => 0.5],
        ['label' => 'Dernière connexion', 'key' => 'c', 'w' => 1.1]],
        array_map(static fn($u) => $u + ['a' => $u['actif'] ? 'Oui' : 'Non', 'c' => fmt_date($u['derniere_connexion'], true)], tout_lire($requete)));
    $pdf->envoyer('utilisateurs.pdf');
}

$page = paginer($requete);
$total = compter($requete, 'utilisateurs');
$roles = bd_paires('SELECT id, nom FROM roles ORDER BY nom');
$titre = 'Utilisateurs';
require INC . '/entete.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 titre-page mb-0"><i class="bi bi-people me-2"></i>Utilisateurs <span class="badge text-bg-light"><?= $total ?></span></h1>
    <div class="d-flex gap-2 d-print-none"><?= boutons_liste('utilisateurs.exporter') ?>
        <?php if (peut('utilisateurs.ajouter')): ?><a href="utilisateur_form.php" class="btn btn-foret btn-sm"><i class="bi bi-person-plus me-1"></i>Nouvel utilisateur</a><?php endif; ?></div>
</div>
<div class="card carte-filtres mb-3 d-print-none"><div class="card-body">
    <form method="get" action="utilisateurs.php" class="row g-2 align-items-end">
        <div class="col-md-4"><label class="form-label">Recherche</label><input type="search" name="q" class="form-control form-control-sm" value="<?= e($_GET['q'] ?? '') ?>"></div>
        <div class="col-md-3"><label class="form-label">Rôle</label><select name="role_id" class="form-select form-select-sm"><?= options($roles, $_GET['role_id'] ?? '', 'Tous') ?></select></div>
        <div class="col-md-2"><label class="form-label">Actif</label><select name="actif" class="form-select form-select-sm"><?= options(['1' => 'Oui', '0' => 'Non'], $_GET['actif'] ?? '', 'Tous') ?></select></div>
        <div class="col-md-auto"><button class="btn btn-foret btn-sm"><i class="bi bi-search"></i></button></div>
    </form>
</div></div>
<div class="card"><div class="table-responsive"><table class="table table-sm table-hover mb-0">
    <thead><tr><th>Identifiant</th><th>Nom complet</th><th>E-mail</th><th>Rôle</th><th>Actif</th><th>Dernière connexion</th><th class="actions d-print-none"></th></tr></thead>
    <tbody><?php foreach ($page['lignes'] as $u): ?>
        <tr><td class="fw-semibold"><?= e($u['identifiant']) ?><?= $u['bloque_jusqua'] && strtotime($u['bloque_jusqua']) > time() ? ' <span class="badge text-bg-danger">bloqué</span>' : '' ?></td>
            <td><?= e($u['nom_complet']) ?></td><td><?= e($u['email']) ?></td><td><span class="badge text-bg-secondary"><?= e($u['role']) ?></span></td>
            <td><?= $u['actif'] ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle text-danger"></i>' ?></td>
            <td><?= fmt_date($u['derniere_connexion'], true) ?></td>
            <td class="actions d-print-none">
                <?php if (peut('traces.voir')): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('traces.php', ['utilisateur_id' => $u['id']])) ?>" title="Traces"><i class="bi bi-journal-text"></i></a><?php endif; ?>
                <?php if (peut('utilisateurs.modifier')): ?><a class="btn btn-sm btn-outline-primary" href="<?= e(url('utilisateur_form.php', ['id' => $u['id']])) ?>" title="Modifier"><i class="bi bi-pencil"></i></a><?php endif; ?>
                <?php if (peut('utilisateurs.supprimer')): ?><?= bouton_supprimer('utilisateurs.php', (int) $u['id'], 'Supprimer l\'utilisateur ' . $u['identifiant'] . ' ?') ?><?php endif; ?>
            </td></tr>
    <?php endforeach; ?></tbody>
</table></div></div>
<?= barre_pagination($page, $total) ?>
<?php require INC . '/pied.php';
