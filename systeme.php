<?php
/** État d'OPcache, d'APCu et des tables MySQL ; vidage du cache ; synchronisation des permissions. */
require __DIR__ . '/inc/init.php';
exiger_permission('parametres.systeme');

if (est_post()) {
    if (($_POST['action'] ?? '') === 'vider_cache') {
        cache_vider();
        if (!empty($_POST['opcache']) && function_exists('opcache_reset')) {
            opcache_reset();
        }
        tracer('cache', 'systeme', null, 'Vidage du cache' . (!empty($_POST['opcache']) ? ' (APCu + OPcache)' : ' (APCu)'));
        flash('success', 'Cache vidé.');
    } elseif (($_POST['action'] ?? '') === 'permissions') {
        $n = synchroniser_permissions();
        tracer('parametres', 'permission', null, "Synchronisation des permissions ($n nouvelle(s))");
        flash('success', "Permissions synchronisées ($n nouvelle(s)).");
    }
    rediriger('systeme.php');
}

$opcache = function_exists('opcache_get_status') ? @opcache_get_status(false) : false;
$apcu = cache_stats();
$tables = bd_tous('SELECT table_name AS nom, engine AS moteur, table_rows AS lignes, ROUND((data_length + index_length) / 1024) AS taille_ko, ROUND(index_length / 1024) AS index_ko
                   FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY table_name');
$mo = static fn($o) => fmt_nombre($o / 1048576, 1) . ' Mo';
$titre = 'Système & cache';
require INC . '/entete.php';
?>
<h1 class="h4 titre-page mb-3"><i class="bi bi-cpu me-2"></i>Système, performances & cache</h1>
<div class="row g-3">
    <div class="col-lg-4"><div class="card h-100"><div class="card-header"><i class="bi bi-lightning-charge me-1"></i>OPcache (code PHP compilé)</div><div class="card-body small">
        <?php if ($opcache && !empty($opcache['opcache_enabled'])): $st = $opcache['opcache_statistics']; $m = $opcache['memory_usage']; ?>
            <span class="badge text-bg-success mb-2">Activé</span><dl class="row mb-0">
                <dt class="col-7">Scripts en cache</dt><dd class="col-5"><?= fmt_nombre($st['num_cached_scripts'], 0) ?></dd>
                <dt class="col-7">Taux de succès</dt><dd class="col-5"><?= fmt_nombre($st['opcache_hit_rate'], 1) ?> %</dd>
                <dt class="col-7">Mémoire utilisée</dt><dd class="col-5"><?= $mo($m['used_memory']) ?></dd>
                <dt class="col-7">Mémoire libre</dt><dd class="col-5"><?= $mo($m['free_memory']) ?></dd>
                <dt class="col-7">JIT</dt><dd class="col-5"><?= !empty($opcache['jit']['enabled']) ? 'Oui' : 'Non' ?></dd></dl>
        <?php else: ?><span class="badge text-bg-warning">Non actif</span><p class="mt-2 mb-0">Activez OPcache dans php.ini (voir <code>config/php-performance.ini</code>).</p><?php endif; ?>
    </div></div></div>
    <div class="col-lg-4"><div class="card h-100"><div class="card-header"><i class="bi bi-memory me-1"></i>APCu (cache de données)</div><div class="card-body small">
        <?php if ($apcu['actif']): ?>
            <span class="badge text-bg-success mb-2">Activé</span><dl class="row mb-0">
                <dt class="col-7">Entrées</dt><dd class="col-5"><?= fmt_nombre($apcu['entrees'], 0) ?></dd>
                <dt class="col-7">Succès / échecs</dt><dd class="col-5"><?= fmt_nombre($apcu['succes'], 0) ?> / <?= fmt_nombre($apcu['echecs'], 0) ?></dd>
                <dt class="col-7">Mémoire totale</dt><dd class="col-5"><?= $mo($apcu['memoire']) ?></dd>
                <dt class="col-7">Mémoire libre</dt><dd class="col-5"><?= $mo($apcu['libre']) ?></dd></dl>
        <?php else: ?><span class="badge text-bg-warning">Non disponible</span><p class="mt-2 mb-0">Installez l'extension <code>apcu</code>. Sans elle, l'application fonctionne avec un cache limité à la requête.</p><?php endif; ?>
        <p class="mt-2 mb-0 text-muted">Mis en cache : paramètres, permissions des rôles, référentiels, statistiques du tableau de bord, totaux des listes et synthèses d'indices. L'invalidation est automatique (étiquettes versionnées).</p>
    </div></div></div>
    <div class="col-lg-4"><div class="card h-100"><div class="card-header"><i class="bi bi-tools me-1"></i>Maintenance</div><div class="card-body small">
        <dl class="row"><dt class="col-5">PHP</dt><dd class="col-7"><?= PHP_VERSION ?></dd><dt class="col-5">Base</dt><dd class="col-7"><?= e(bd_valeur('SELECT VERSION()')) ?></dd></dl>
        <form method="post" action="systeme.php" class="mb-2"><?= champ_csrf() ?><input type="hidden" name="action" value="vider_cache">
            <div class="form-check"><input class="form-check-input" type="checkbox" name="opcache" value="1" id="oc"><label class="form-check-label" for="oc">Réinitialiser aussi OPcache</label></div>
            <button class="btn btn-outline-danger btn-sm mt-1"><i class="bi bi-eraser me-1"></i>Vider le cache</button></form>
        <form method="post" action="systeme.php"><?= champ_csrf() ?><input type="hidden" name="action" value="permissions">
            <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-repeat me-1"></i>Synchroniser les permissions</button></form>
    </div></div></div>
    <div class="col-12"><div class="card"><div class="card-header"><i class="bi bi-database me-1"></i>Tables MySQL</div><div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th>Table</th><th>Moteur</th><th class="text-end">Lignes (estimation)</th><th class="text-end">Taille</th><th class="text-end">dont index</th></tr></thead>
        <tbody><?php foreach ($tables as $t): ?>
            <tr><td><code><?= e($t['nom']) ?></code></td><td><?= $t['moteur'] === 'InnoDB' ? '<span class="badge text-bg-success">InnoDB</span>' : e($t['moteur']) ?></td>
                <td class="text-end"><?= fmt_nombre($t['lignes'], 0) ?></td><td class="text-end"><?= fmt_nombre($t['taille_ko'], 0) ?> Ko</td><td class="text-end"><?= fmt_nombre($t['index_ko'], 0) ?> Ko</td></tr>
        <?php endforeach; ?></tbody>
    </table></div></div></div>
</div>
<?php require INC . '/pied.php';
