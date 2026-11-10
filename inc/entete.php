<?php
defined('INC') || exit; // fichier inclus uniquement
/**
 * En-tête HTML commun (menu latéral). Variable attendue : $titre.
 * Fermé par inc/pied.php.
 */
$_u = utilisateur();
$_page = page_courante();
$_type = $_GET['type'] ?? '';
$_menu = static function (string $href, string $icone, string $texte, bool $actif): string {
    return '<li class="nav-item"><a class="nav-link' . ($actif ? ' active' : '') . '" href="' . e($href) . '"><i class="bi ' . $icone . ' me-2"></i>' . e($texte) . '</a></li>';
};
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(jeton_csrf()) ?>">
    <title><?= e($titre ?? 'Suivi Troupeau') ?> · <?= e(parametre('nom_exploitation', 'Suivi Troupeau')) ?></title>
    <link href="<?= e(asset('vendor/bootstrap/bootstrap.min.css')) ?>" rel="stylesheet">
    <link href="<?= e(asset('vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>" rel="stylesheet">
    <link href="<?= e(asset('css/app.css')) ?>" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-dark bg-foret sticky-top d-print-none px-3">
    <button class="btn btn-outline-light d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#menu" aria-label="Menu"><i class="bi bi-list"></i></button>
    <a class="navbar-brand fw-semibold" href="index.php"><i class="bi bi-flower3 me-1"></i><?= e(parametre('nom_exploitation', 'Suivi Troupeau')) ?></a>
    <form class="d-none d-md-flex ms-auto me-3" action="animaux.php" method="get" role="search">
        <input type="hidden" name="statut" value="tous">
        <div class="input-group input-group-sm">
            <input class="form-control" type="search" name="q" placeholder="N° boucle ou nom..." aria-label="Rechercher un animal" data-recherche-animal data-tous="1">
            <button class="btn btn-light" type="submit"><i class="bi bi-search"></i></button>
        </div>
    </form>
    <div class="dropdown">
        <button class="btn btn-outline-light btn-sm dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-person-circle me-1"></i><?= e($_u['nom_complet'] ?? '') ?></button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><span class="dropdown-item-text small text-muted"><?= e($_u['role'] ?? '') ?></span></li>
            <li><a class="dropdown-item" href="profil.php"><i class="bi bi-key me-2"></i>Mon profil</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><form method="post" action="deconnexion.php"><?= champ_csrf() ?><button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Déconnexion</button></form></li>
        </ul>
    </div>
</nav>
<div class="container-fluid"><div class="row">
<aside class="col-lg-2 p-0 d-print-none">
    <div class="offcanvas-lg offcanvas-start menu-lateral" tabindex="-1" id="menu">
        <div class="offcanvas-header"><h5 class="offcanvas-title">Menu</h5><button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#menu"></button></div>
        <div class="offcanvas-body d-block p-2"><ul class="nav flex-column">
            <?= $_menu('index.php', 'bi-speedometer', 'Tableau de bord', $_page === 'index.php') ?>
            <?php if (peut('animaux.voir')): ?>
                <li class="titre-menu">Troupeau</li>
                <?= $_menu('animaux.php', 'bi-collection', 'Animaux (fiches)', in_array($_page, ['animaux.php', 'animal.php'], true) || ($_page === 'animal_form.php' && !empty($_GET['id']))) ?>
                <?php if (peut('animaux.ajouter')): ?><?= $_menu('animal_form.php', 'bi-plus-circle', 'Nouvel animal', $_page === 'animal_form.php' && empty($_GET['id'])) ?><?php endif; ?>
            <?php endif; ?>
            <li class="titre-menu">Suivis</li>
            <?php foreach (suivis_config() as $_t => $_s): if (!peut("$_t.voir")) continue; ?>
                <?= $_menu(url('suivis.php', ['type' => $_t]), $_s['icone'], $_s['titre'], in_array($_page, ['suivis.php', 'suivi_form.php'], true) && $_type === $_t) ?>
            <?php endforeach; ?>
            <?php if (peut('indices.voir')): ?>
                <li class="titre-menu">Indices</li>
                <?= $_menu('indices.php', 'bi-graph-up-arrow', 'Indices du troupeau', $_page === 'indices.php') ?>
                <?= $_menu('indices_resultats.php', 'bi-table', 'Résultats enregistrés', in_array($_page, ['indices_resultats.php', 'indice_detail.php'], true)) ?>
                <?= $_menu('indices_evolution.php', 'bi-bar-chart-line', 'Évolution annuelle', $_page === 'indices_evolution.php') ?>
            <?php endif; ?>
            <li class="titre-menu">Administration</li>
            <?php if (peut('utilisateurs.voir')): ?><?= $_menu('utilisateurs.php', 'bi-people', 'Utilisateurs', in_array($_page, ['utilisateurs.php', 'utilisateur_form.php'], true)) ?><?php endif; ?>
            <?php if (peut('roles.voir')): ?><?= $_menu('roles.php', 'bi-shield-lock', 'Rôles & permissions', in_array($_page, ['roles.php', 'role_form.php'], true)) ?><?php endif; ?>
            <?php if (peut('traces.voir')): ?><?= $_menu('traces.php', 'bi-journal-text', 'Traces (journal)', in_array($_page, ['traces.php', 'trace.php'], true)) ?><?php endif; ?>
            <?php if (peut('referentiels.voir')): ?><?= $_menu('referentiels.php', 'bi-tags', 'Races & lots', $_page === 'referentiels.php') ?><?php endif; ?>
            <?php if (peut('indices.voir')): ?><?= $_menu('formules.php', 'bi-calculator', 'Formules des indices', in_array($_page, ['formules.php', 'formule_form.php'], true)) ?><?php endif; ?>
            <?php if (peut('parametres.gerer')): ?><?= $_menu('parametres.php', 'bi-gear', 'Paramètres', $_page === 'parametres.php') ?><?php endif; ?>
            <?php if (peut('parametres.systeme')): ?><?= $_menu('systeme.php', 'bi-cpu', 'Système & cache', $_page === 'systeme.php') ?><?php endif; ?>
        </ul></div>
    </div>
</aside>
<main class="col-lg-10 py-3 px-lg-4">
<?php unset($_u, $_page, $_type, $_menu, $_t, $_s); // aucune variable de l'en-tête ne fuit vers la page ?>
<?php foreach (flashes() as $_f): ?>
    <div class="alert alert-<?= e($_f['type']) ?> alert-dismissible fade show d-print-none" role="alert"><?= e($_f['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button></div>
<?php endforeach; ?>
