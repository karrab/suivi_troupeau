<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Services\Tracking;

$u = Auth::user();
$title = $title ?? 'Suivi Troupeau';
$nav = static function (string $route, string $icon, string $label, array $params = [], ?string $active = null): string {
    $isActive = $active !== null ? $active : (route_is($route) && (!isset($params['type']) || ($_GET['type'] ?? '') === $params['type']));
    return '<li class="nav-item"><a class="nav-link' . ($isActive ? ' active' : '') . '" href="' . e(url($route, $params)) . '">'
        . '<i class="bi ' . $icon . ' me-2"></i>' . e($label) . '</a></li>';
};
?>
<!doctype html>
<html lang="fr" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
    <title><?= e($title) ?> · <?= e(setting('nom_exploitation', 'Suivi Troupeau')) ?></title>
    <link href="<?= e(asset('vendor/bootstrap/bootstrap.min.css')) ?>" rel="stylesheet">
    <link href="<?= e(asset('vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>" rel="stylesheet">
    <link href="<?= e(asset('css/app.css')) ?>" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-dark bg-forest sticky-top d-print-none px-3">
    <button class="btn btn-outline-light d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-label="Menu">
        <i class="bi bi-list"></i>
    </button>
    <a class="navbar-brand fw-semibold" href="<?= e(url('dashboard')) ?>"><i class="bi bi-flower3 me-1"></i><?= e(setting('nom_exploitation', 'Suivi Troupeau')) ?></a>
    <form class="d-none d-md-flex ms-auto me-3" action="<?= e(url()) ?>" method="get" role="search">
        <input type="hidden" name="r" value="animals">
        <input type="hidden" name="status" value="tous">
        <div class="input-group input-group-sm">
            <input class="form-control" type="search" name="q" placeholder="N° boucle ou nom..." aria-label="Rechercher un animal" data-animal-search data-target-show>
            <button class="btn btn-light" type="submit"><i class="bi bi-search"></i></button>
        </div>
    </form>
    <div class="dropdown">
        <button class="btn btn-outline-light btn-sm dropdown-toggle" data-bs-toggle="dropdown">
            <i class="bi bi-person-circle me-1"></i><?= e($u['full_name'] ?? '') ?>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><span class="dropdown-item-text small text-muted"><?= e($u['role_name'] ?? '') ?></span></li>
            <li><a class="dropdown-item" href="<?= e(url('profile')) ?>"><i class="bi bi-key me-2"></i>Mon profil</a></li>
            <li><hr class="dropdown-divider"></li>
            <li>
                <form method="post" action="<?= e(url('logout')) ?>"><?= csrf_field() ?>
                    <button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Déconnexion</button>
                </form>
            </li>
        </ul>
    </div>
</nav>

<div class="container-fluid">
    <div class="row">
        <aside class="col-lg-2 p-0 d-print-none">
            <div class="offcanvas-lg offcanvas-start sidebar" tabindex="-1" id="sidebar">
                <div class="offcanvas-header"><h5 class="offcanvas-title">Menu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#sidebar"></button></div>
                <div class="offcanvas-body d-block p-2">
                    <ul class="nav flex-column">
                        <?= $nav('dashboard', 'bi-speedometer', 'Tableau de bord') ?>
                        <?php if (can('animals.view')): ?>
                            <li class="sidebar-heading">Troupeau</li>
                            <?= $nav('animals', 'bi-collection', 'Animaux (fiches)', [], route_is('animals')) ?>
                            <?php if (can('animals.create')): ?><?= $nav('animals/create', 'bi-plus-circle', 'Nouvel animal', [], current_route() === 'animals/create') ?><?php endif; ?>
                        <?php endif; ?>
                        <li class="sidebar-heading">Suivis</li>
                        <?php foreach (Tracking::all() as $type => $cfg): if (!can("$type.view")) continue; ?>
                            <?= $nav('tracking', $cfg['icon'], $cfg['title'], ['type' => $type], route_is('tracking') && ($_GET['type'] ?? '') === $type) ?>
                        <?php endforeach; ?>
                        <?php if (can('indices.view')): ?>
                            <li class="sidebar-heading">Indices</li>
                            <?= $nav('indices', 'bi-graph-up-arrow', 'Indices du troupeau', [], current_route() === 'indices') ?>
                            <?= $nav('indices/results', 'bi-table', 'Résultats enregistrés', [], route_is('indices/results', 'indices/show')) ?>
                            <?= $nav('indices/evolution', 'bi-bar-chart-line', 'Évolution annuelle', [], route_is('indices/evolution')) ?>
                        <?php endif; ?>
                        <?php if (can('users.view') || can('roles.view') || can('audit.view') || can('settings.manage') || can('references.view') || can('indices.formulas')): ?>
                            <li class="sidebar-heading">Administration</li>
                            <?php if (can('users.view')): ?><?= $nav('users', 'bi-people', 'Utilisateurs') ?><?php endif; ?>
                            <?php if (can('roles.view')): ?><?= $nav('roles', 'bi-shield-lock', 'Rôles & permissions') ?><?php endif; ?>
                            <?php if (can('audit.view')): ?><?= $nav('audit', 'bi-journal-text', 'Traces (journal)') ?><?php endif; ?>
                            <?php if (can('references.view')): ?><?= $nav('references', 'bi-tags', 'Races & lots') ?><?php endif; ?>
                            <?php if (can('indices.view')): ?><?= $nav('settings/indices', 'bi-calculator', 'Formules des indices', [], route_is('settings/indices', 'settings/index-form')) ?><?php endif; ?>
                            <?php if (can('settings.manage')): ?><?= $nav('settings', 'bi-gear', 'Paramètres', [], current_route() === 'settings') ?><?php endif; ?>
                            <?php if (can('settings.system')): ?><?= $nav('settings/system', 'bi-cpu', 'Système & cache') ?><?php endif; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </aside>
        <main class="col-lg-10 py-3 px-lg-4">
            <?php foreach (flashes() as $f): ?>
                <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show d-print-none" role="alert">
                    <?= e($f['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
                </div>
            <?php endforeach; ?>
            <?= $content ?>
        </main>
    </div>
</div>

<!-- Modal de confirmation de suppression -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="bi bi-exclamation-triangle text-danger me-2"></i>Confirmation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body" id="confirmMessage">Confirmer la suppression ?</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-danger" id="confirmOk"><i class="bi bi-trash me-1"></i>Supprimer</button>
            </div>
        </div>
    </div>
</div>

<datalist id="animal-tags"></datalist>
<script src="<?= e(asset('vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('vendor/chartjs/chart.umd.min.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>" data-search-url="<?= e(url('animals/search')) ?>" data-test-url="<?= e(url('settings/index-test')) ?>" id="app-js"></script>
</body>
</html>
