<?php
/** Tableau de bord. */
require __DIR__ . '/inc/init.php';
exiger_connexion();

$annee = (int) date('Y');
// Statistiques mises en cache APCu 60 s (invalidées à chaque modification d'animal ou de suivi)
$stats = cache_memoriser(cache_cle(['animaux', 'suivis'], 'tableau_bord:' . $annee), 60, static function () use ($annee) {
    $d = "$annee-01-01";
    return [
        'categories' => bd_tous("SELECT c.libelle, c.espece, COUNT(a.id) AS n FROM categories c
                                 LEFT JOIN animaux a ON a.categorie_id = c.id AND a.statut = 'actif'
                                 GROUP BY c.id, c.libelle, c.espece, c.ordre ORDER BY c.ordre"),
        'totaux' => bd_un("SELECT SUM(statut = 'actif') AS actifs, SUM(statut = 'actif' AND espece = 'caprin') AS caprins,
                                  SUM(statut = 'actif' AND espece = 'ovin') AS ovins, SUM(date_naissance >= :d AND origine = 'naissance') AS naissances,
                                  SUM(statut = 'mort' AND date_sortie >= :d) AS morts, SUM(statut = 'vendu' AND date_sortie >= :d) AS ventes
                           FROM animaux", ['d' => $d]),
        'naissances_mois' => bd_paires('SELECT MONTH(date_evenement) m, SUM(nes_vivants) FROM mises_bas WHERE date_evenement >= :d GROUP BY m', ['d' => $d]),
        'lait_mois' => bd_paires('SELECT MONTH(date_evenement) m, ROUND(AVG(quantite), 2) FROM controles_laitiers WHERE date_evenement >= :d GROUP BY m', ['d' => $d]),
    ];
});

$jMb = (int) parametre('alerte_mise_bas_jours', 15);
$jRappel = (int) parametre('alerte_rappel_jours', 15);
$misesBasPrevues = bd_tous("SELECT s.mise_bas_prevue, a.id, a.boucle, a.nom FROM saillies s JOIN animaux a ON a.id = s.animal_id
    WHERE s.mise_bas_prevue BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL $jMb DAY) AND s.diagnostic <> 'vide' AND a.statut = 'actif'
      AND NOT EXISTS (SELECT 1 FROM mises_bas mb WHERE mb.animal_id = s.animal_id AND mb.date_evenement >= s.date_evenement)
    ORDER BY s.mise_bas_prevue LIMIT 15");
$rappels = bd_tous("SELECT s.date_rappel, s.type_soin, s.produit, a.id, a.boucle FROM soins s JOIN animaux a ON a.id = s.animal_id
    WHERE s.date_rappel BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL $jRappel DAY) AND a.statut = 'actif' ORDER BY s.date_rappel LIMIT 15");
$delais = bd_tous("SELECT s.produit, a.id, a.boucle, DATE_ADD(s.date_evenement, INTERVAL s.delai_attente DAY) AS fin
    FROM soins s JOIN animaux a ON a.id = s.animal_id
    WHERE s.delai_attente > 0 AND s.date_evenement >= DATE_SUB(CURDATE(), INTERVAL 365 DAY)
      AND DATE_ADD(s.date_evenement, INTERVAL s.delai_attente DAY) >= CURDATE() AND a.statut = 'actif' ORDER BY fin LIMIT 15");
$recents = bd_tous('SELECT h.*, a.boucle FROM historique_animaux h JOIN animaux a ON a.id = h.animal_id ORDER BY h.id DESC LIMIT 10');

$t = $stats['totaux'] ?? [];
$mois = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'];
$naiss = $lait = [];
for ($m = 1; $m <= 12; $m++) {
    $naiss[] = (int) ($stats['naissances_mois'][$m] ?? 0);
    $lait[] = isset($stats['lait_mois'][$m]) ? (float) $stats['lait_mois'][$m] : null;
}
$cats = array_values(array_filter($stats['categories'], static fn($c) => (int) $c['n'] > 0));

$titre = 'Tableau de bord';
require INC . '/entete.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 titre-page mb-0"><i class="bi bi-speedometer me-2"></i>Tableau de bord</h1>
    <div class="d-print-none">
        <?php if (peut('animaux.ajouter')): ?><a href="animal_form.php" class="btn btn-foret btn-sm"><i class="bi bi-plus-lg me-1"></i>Nouvel animal</a><?php endif; ?>
        <button class="btn btn-outline-secondary btn-sm" data-imprimer><i class="bi bi-printer"></i></button>
    </div>
</div>

<div class="row g-3 mb-3">
    <?php foreach ([
        ['Animaux actifs', $t['actifs'] ?? 0, 'bi-collection', ['statut' => 'actif']],
        ['Caprins', $t['caprins'] ?? 0, 'bi-circle-fill', ['espece' => 'caprin']],
        ['Ovins', $t['ovins'] ?? 0, 'bi-cloud-fill', ['espece' => 'ovin']],
        ["Naissances $annee", $t['naissances'] ?? 0, 'bi-stars', ['statut' => 'tous', 'ne_du' => "$annee-01-01"]],
        ["Morts $annee", $t['morts'] ?? 0, 'bi-heartbreak', ['statut' => 'mort', 'sorti_du' => "$annee-01-01"]],
        ["Ventes $annee", $t['ventes'] ?? 0, 'bi-cash-coin', ['statut' => 'vendu', 'sorti_du' => "$annee-01-01"]],
    ] as [$lib, $val, $icone, $params]): ?>
        <div class="col-6 col-md-4 col-xl-2"><a class="text-decoration-none" href="<?= e(url('animaux.php', $params)) ?>">
            <div class="card carte-stat h-100"><div class="card-body d-flex justify-content-between">
                <div><div class="small text-muted"><?= e($lib) ?></div><div class="valeur-stat"><?= fmt_nombre($val, 0) ?></div></div>
                <i class="bi <?= $icone ?> icone-stat text-foret"></i>
            </div></div></a></div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-4"><div class="card h-100"><div class="card-header">Effectif actif par catégorie</div><div class="card-body">
        <?= graphique(['type' => 'doughnut', 'labels' => array_column($cats, 'libelle'),
            'datasets' => [['data' => array_map('intval', array_column($cats, 'n')),
                'backgroundColor' => ['#195c3e', '#2f7d55', '#4a9c6d', '#79bd8f', '#a3d4b0', '#c9e6d0', '#e0b354', '#c97b3d', '#8a5a2b']]],
            'options' => ['plugins' => ['legend' => ['display' => true, 'position' => 'right']]]], 220) ?>
        <table class="table table-sm mt-2 mb-0">
            <?php foreach ($stats['categories'] as $c): ?>
                <tr><td><?= e($c['libelle']) ?> <span class="text-muted small">(<?= e(libelle('espece', $c['espece'])) ?>)</span></td><td class="text-end fw-semibold"><?= (int) $c['n'] ?></td></tr>
            <?php endforeach; ?>
        </table>
    </div></div></div>
    <div class="col-lg-8"><div class="card h-100"><div class="card-header">Naissances (nés vivants) et production laitière moyenne — <?= $annee ?></div><div class="card-body">
        <?= graphique(['type' => 'bar', 'labels' => $mois, 'zero' => true,
            'datasets' => [
                ['type' => 'bar', 'label' => 'Nés vivants', 'data' => $naiss, 'backgroundColor' => '#4a9c6d', 'yAxisID' => 'y'],
                ['type' => 'line', 'label' => 'Lait moyen (L/j)', 'data' => $lait, 'borderColor' => '#c97b3d', 'backgroundColor' => '#c97b3d', 'yAxisID' => 'y1', 'spanGaps' => true],
            ],
            'options' => ['plugins' => ['legend' => ['display' => true]], 'scales' => ['y' => ['beginAtZero' => true], 'y1' => ['beginAtZero' => true, 'position' => 'right', 'grid' => ['drawOnChartArea' => false]]]]], 330) ?>
    </div></div></div>
</div>

<div class="row g-3">
    <div class="col-lg-4"><div class="card h-100"><div class="card-header"><i class="bi bi-calendar-heart me-1 text-foret"></i>Mises bas prévues (<?= $jMb ?> j)</div>
        <ul class="list-group list-group-flush">
            <?php foreach ($misesBasPrevues as $m): ?>
                <li class="list-group-item d-flex justify-content-between"><a href="<?= e(url('animal.php', ['id' => $m['id']])) ?>"><?= e($m['boucle']) ?> <?= e($m['nom']) ?></a><span class="badge text-bg-info"><?= fmt_date($m['mise_bas_prevue']) ?></span></li>
            <?php endforeach; ?>
            <?php if (!$misesBasPrevues): ?><li class="list-group-item text-muted small">Aucune mise bas prévue.</li><?php endif; ?>
        </ul></div></div>
    <div class="col-lg-4"><div class="card h-100"><div class="card-header"><i class="bi bi-bell me-1 text-warning"></i>Rappels sanitaires et délais d'attente</div>
        <ul class="list-group list-group-flush">
            <?php foreach ($rappels as $r): ?>
                <li class="list-group-item d-flex justify-content-between"><span><a href="<?= e(url('animal.php', ['id' => $r['id'], 'onglet' => 'soins'])) ?>"><?= e($r['boucle']) ?></a> — <?= e(libelle('type_soin', $r['type_soin'])) ?> <?= e($r['produit']) ?></span><span class="badge text-bg-warning"><?= fmt_date($r['date_rappel']) ?></span></li>
            <?php endforeach; ?>
            <?php foreach ($delais as $d): ?>
                <li class="list-group-item d-flex justify-content-between"><span><a href="<?= e(url('animal.php', ['id' => $d['id'], 'onglet' => 'soins'])) ?>"><?= e($d['boucle']) ?></a> — délai d'attente <?= e($d['produit']) ?></span><span class="badge text-bg-danger">jusqu'au <?= fmt_date($d['fin']) ?></span></li>
            <?php endforeach; ?>
            <?php if (!$rappels && !$delais): ?><li class="list-group-item text-muted small">Aucune alerte.</li><?php endif; ?>
        </ul></div></div>
    <div class="col-lg-4"><div class="card h-100"><div class="card-header"><i class="bi bi-clock-history me-1"></i>Derniers événements</div>
        <ul class="list-group list-group-flush small">
            <?php foreach ($recents as $h): ?>
                <li class="list-group-item"><span class="text-muted"><?= fmt_date($h['date_evenement']) ?></span>
                    <a href="<?= e(url('animal.php', ['id' => $h['animal_id']])) ?>"><?= e($h['boucle']) ?></a> — <?= e(mb_strimwidth($h['resume'], 0, 90, '…')) ?></li>
            <?php endforeach; ?>
        </ul></div></div>
</div>
<?php require INC . '/pied.php';
