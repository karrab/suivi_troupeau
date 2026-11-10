<?php
$title = 'Système & cache';
$mb = static fn($b) => fmt_num($b / 1048576, 1) . ' Mo';
$o = $opcache ?: null;
?>
<h1 class="h4 page-title mb-3"><i class="bi bi-cpu me-2"></i>Système, performances & cache</h1>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card h-100"><div class="card-header"><i class="bi bi-lightning-charge me-1"></i>OPcache (bytecode PHP)</div><div class="card-body small">
            <?php if ($o && !empty($o['opcache_enabled'])): $s = $o['opcache_statistics']; $m = $o['memory_usage']; ?>
                <span class="badge text-bg-success mb-2">Activé</span>
                <dl class="row mb-0">
                    <dt class="col-7">Scripts en cache</dt><dd class="col-5"><?= fmt_num($s['num_cached_scripts'], 0) ?></dd>
                    <dt class="col-7">Taux de succès</dt><dd class="col-5"><?= fmt_num($s['opcache_hit_rate'], 1) ?> %</dd>
                    <dt class="col-7">Mémoire utilisée</dt><dd class="col-5"><?= $mb($m['used_memory']) ?></dd>
                    <dt class="col-7">Mémoire libre</dt><dd class="col-5"><?= $mb($m['free_memory']) ?></dd>
                    <dt class="col-7">JIT</dt><dd class="col-5"><?= !empty($o['jit']['enabled']) ? 'Oui' : 'Non' ?></dd>
                </dl>
            <?php else: ?>
                <span class="badge text-bg-warning">Non actif</span>
                <p class="mt-2 mb-0">Activez OPcache dans php.ini (voir <code>config/php-performance.ini</code>).</p>
            <?php endif; ?>
        </div></div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100"><div class="card-header"><i class="bi bi-memory me-1"></i>APCu (cache de données)</div><div class="card-body small">
            <?php if ($apcu['enabled']): ?>
                <span class="badge text-bg-success mb-2">Activé</span>
                <dl class="row mb-0">
                    <dt class="col-7">Entrées</dt><dd class="col-5"><?= fmt_num($apcu['entries'], 0) ?></dd>
                    <dt class="col-7">Succès / échecs</dt><dd class="col-5"><?= fmt_num($apcu['hits'], 0) ?> / <?= fmt_num($apcu['misses'], 0) ?></dd>
                    <dt class="col-7">Mémoire totale</dt><dd class="col-5"><?= $mb($apcu['memory']) ?></dd>
                    <dt class="col-7">Mémoire libre</dt><dd class="col-5"><?= $mb($apcu['free']) ?></dd>
                </dl>
            <?php else: ?>
                <span class="badge text-bg-warning">Non disponible</span>
                <p class="mt-2 mb-0">Installez l'extension <code>apcu</code>. L'application fonctionne sans, avec un cache limité à la requête.</p>
            <?php endif; ?>
            <p class="mt-2 mb-0 text-muted">Mis en cache : paramètres, permissions des rôles, référentiels, statistiques du tableau de bord, totaux des listes, synthèses d'indices. Invalidation automatique par tags versionnés.</p>
        </div></div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100"><div class="card-header"><i class="bi bi-tools me-1"></i>Maintenance</div><div class="card-body small">
            <dl class="row">
                <dt class="col-5">PHP</dt><dd class="col-7"><?= PHP_VERSION ?></dd>
                <dt class="col-5">Base</dt><dd class="col-7"><?= e($dbVersion) ?></dd>
            </dl>
            <form method="post" action="<?= e(url('settings/cache-clear')) ?>" class="mb-2"><?= csrf_field() ?>
                <div class="form-check"><input class="form-check-input" type="checkbox" name="opcache" value="1" id="oc"><label class="form-check-label" for="oc">Réinitialiser aussi OPcache</label></div>
                <button class="btn btn-outline-danger btn-sm mt-1"><i class="bi bi-eraser me-1"></i>Vider le cache</button>
            </form>
            <form method="post" action="<?= e(url('settings/sync-perms')) ?>"><?= csrf_field() ?>
                <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-repeat me-1"></i>Synchroniser les permissions</button>
            </form>
        </div></div>
    </div>
    <div class="col-12">
        <div class="card"><div class="card-header"><i class="bi bi-database me-1"></i>Tables MySQL</div><div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>Table</th><th>Moteur</th><th class="text-end">Lignes (estim.)</th><th class="text-end">Taille</th><th class="text-end">dont index</th></tr></thead>
                <tbody><?php foreach ($tables as $t): ?>
                    <tr><td><code><?= e($t['name']) ?></code></td><td><?= $t['engine'] === 'InnoDB' ? '<span class="badge text-bg-success">InnoDB</span>' : e($t['engine']) ?></td>
                        <td class="text-end"><?= fmt_num($t['rows_est'], 0) ?></td><td class="text-end"><?= fmt_num($t['size_kb'], 0) ?> Ko</td><td class="text-end"><?= fmt_num($t['index_kb'], 0) ?> Ko</td></tr>
                <?php endforeach; ?></tbody>
            </table>
        </div></div>
    </div>
</div>
