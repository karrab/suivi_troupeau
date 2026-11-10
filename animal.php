<?php
/**
 * Fiche animal : identification, généalogie, courbe de poids, tous les
 * suivis, descendance, indices, historique.
 *   ?id=5          affichage
 *   ?id=5&pdf=1    fiche PDF
 *   POST action=calculer   calcul des indices de l'animal
 */
require __DIR__ . '/inc/init.php';
require INC . '/pdf.php';
require INC . '/indices.php';
exiger_permission('animaux.voir');

$id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
$a = bd_un(
    'SELECT a.*, c.libelle AS categorie, r.nom AS race, l.nom AS lot,
            m.boucle AS mere_boucle, m.nom AS mere_nom, p.boucle AS pere_boucle, p.nom AS pere_nom,
            uc.nom_complet AS cree_par_nom
     FROM animaux a JOIN categories c ON c.id = a.categorie_id
     LEFT JOIN races r ON r.id = a.race_id LEFT JOIN lots l ON l.id = a.lot_id
     LEFT JOIN animaux m ON m.id = a.mere_id LEFT JOIN animaux p ON p.id = a.pere_id
     LEFT JOIN utilisateurs uc ON uc.id = a.cree_par
     WHERE a.id = :id', ['id' => $id]
) ?? abandonner(404, 'Animal introuvable.');

// ----------------------------------------------- POST : calcul des indices
if (est_post() && ($_POST['action'] ?? '') === 'calculer') {
    exiger_permission('indices.calculer');
    $annee = (int) ($_POST['annee'] ?? date('Y'));
    $n = calculer_indices_animaux($annee, $id)['enregistres'] + calculer_indices_animaux(0, $id)['enregistres'];
    historique_ajouter($id, date('Y-m-d'), 'indice', "Calcul des indices ($annee et vie entière)");
    tracer('calcul', 'animal', $id, "Calcul des indices de {$a['boucle']} ($annee + vie entière) : $n résultat(s)");
    flash('success', "$n indice(s) calculé(s) et enregistré(s).");
    rediriger(url('animal.php', ['id' => $id, 'onglet' => 'indices']));
}

// ------------------------------------------------------- données de la fiche
$suivisFiche = [];
foreach (suivis_config() as $type => $_) {
    if (!peut("$type.voir")) {
        continue;
    }
    $s = suivi($type);
    [$select, $from] = suivi_sql($s);
    $cond = match ($type) {
        'saillies'  => '(t.animal_id = :id OR t.male_id = :id)',   // femelle ou mâle
        'mises_bas' => '(t.animal_id = :id OR t.pere_id = :id)',   // mère ou père
        default     => 't.animal_id = :id',
    };
    $suivisFiche[$type] = ['s' => $s, 'lignes' => bd_tous("SELECT $select $from WHERE $cond ORDER BY t.date_evenement DESC, t.id DESC LIMIT 200", ['id' => $id])];
}
$descendants = bd_tous('SELECT a.id, a.boucle, a.nom, a.sexe, a.date_naissance, a.statut, c.libelle AS categorie FROM animaux a
                        JOIN categories c ON c.id = a.categorie_id WHERE a.mere_id = :id OR a.pere_id = :id ORDER BY a.date_naissance DESC LIMIT 200', ['id' => $id]);
$historique = bd_tous('SELECT h.*, u.nom_complet FROM historique_animaux h LEFT JOIN utilisateurs u ON u.id = h.utilisateur_id
                       WHERE h.animal_id = :id ORDER BY h.date_evenement DESC, h.id DESC LIMIT 300', ['id' => $id]);
$resultats = peut('indices.voir') ? bd_tous('SELECT r.*, i.nom, i.unite, i.decimales FROM resultats_indices r JOIN indices i ON i.id = r.indice_id
                                            WHERE r.animal_id = :id ORDER BY r.annee DESC, i.ordre', ['id' => $id]) : [];
$poids = bd_tous('SELECT date_evenement, poids FROM pesees WHERE animal_id = :id ORDER BY date_evenement, id', ['id' => $id]);
$dernierPoids = $poids ? end($poids) : null;

$identite = [
    'N° boucle' => $a['boucle'], 'Nom' => $a['nom'] ?: '—', 'Espèce' => libelle('espece', $a['espece']), 'Catégorie' => $a['categorie'],
    'Sexe' => libelle('sexe', $a['sexe']), 'Race' => $a['race'] ?: '—', 'Robe' => $a['robe'] ?: '—', 'Lot' => $a['lot'] ?: '—',
    'Naissance' => fmt_date($a['date_naissance']) . ((int) $a['naissance_estimee'] ? ' (estimée)' : ''),
    'Âge' => fmt_age($a['date_naissance'], $a['date_sortie']),
    'Poids de naissance' => $a['poids_naissance'] !== null ? fmt_nombre($a['poids_naissance']) . ' kg' : '—',
    'Type de naissance' => $a['type_naissance'] ? libelle('type_naissance', $a['type_naissance']) : '—',
    'Dernier poids' => $dernierPoids ? fmt_nombre($dernierPoids['poids']) . ' kg (' . fmt_date($dernierPoids['date_evenement']) . ')' : '—',
    'Origine' => libelle('origine', $a['origine']), 'Entrée' => fmt_date($a['date_entree']),
    'Prix d\'achat' => $a['prix_achat'] !== null ? fmt_argent($a['prix_achat']) : '—',
    'Statut' => libelle('statut', $a['statut']),
    'Sortie' => $a['date_sortie'] ? fmt_date($a['date_sortie']) . ($a['motif_sortie'] ? ' — ' . $a['motif_sortie'] : '') : '—',
    'Prix de vente' => $a['prix_vente'] !== null ? fmt_argent($a['prix_vente']) : '—',
];

// ------------------------------------------------------------ fiche PDF
if (!empty($_GET['pdf'])) {
    exiger_permission('animaux.exporter');
    tracer('export', 'animal', $id, 'Fiche PDF de l\'animal ' . $a['boucle']);
    $pdf = new PdfRapport('Fiche animal : ' . $a['boucle'] . ($a['nom'] ? ' — ' . $a['nom'] : ''),
        $a['categorie'] . ' · ' . libelle('espece', $a['espece']) . ' · ' . libelle('statut', $a['statut']));
    $pdf->ajouterPage();
    $pdf->titreSection('Identification');
    $pdf->clesValeurs($identite + ['Mère' => $a['mere_boucle'] ?: '—', 'Père' => $a['pere_boucle'] ?: '—']);
    if ($a['remarques']) {
        $pdf->setFont(false, 8.5);
        $pdf->paragraph('Remarques : ' . $a['remarques'], 4.5);
    }
    foreach ($suivisFiche as $sf) {
        if (!$sf['lignes']) {
            continue;
        }
        $s = $sf['s'];
        $pdf->titreSection($s['titre'] . ' (' . count($sf['lignes']) . ')');
        $cols = array_map(static fn($c) => ['label' => $s['champs'][$c]['libelle'], 'key' => $c, 'w' => $c === 'remarques' ? 2 : 1,
            'align' => $s['champs'][$c]['type'] === 'nombre' ? 'R' : 'L'], $s['colonnes']);
        $pdf->tableau($cols, array_map(static function ($l) use ($s) {
            $o = [];
            foreach ($s['colonnes'] as $c) {
                $o[$c] = suivi_valeur($s, $c, $l, false);
            }
            return $o;
        }, $sf['lignes']), 7.5);
    }
    if ($descendants) {
        $pdf->titreSection('Descendance (' . count($descendants) . ')');
        $pdf->tableau([['label' => 'N° boucle', 'key' => 'boucle'], ['label' => 'Nom', 'key' => 'nom'], ['label' => 'Catégorie', 'key' => 'categorie'],
            ['label' => 'Naissance', 'key' => 'naissance'], ['label' => 'Statut', 'key' => 'statut']],
            array_map(static fn($x) => ['boucle' => $x['boucle'], 'nom' => $x['nom'], 'categorie' => $x['categorie'],
                'naissance' => fmt_date($x['date_naissance']), 'statut' => libelle('statut', $x['statut'])], $descendants), 7.5);
    }
    if ($resultats) {
        $pdf->titreSection('Indices calculés');
        $pdf->tableau([['label' => 'Année', 'key' => 'annee', 'w' => 0.6], ['label' => 'Indice', 'key' => 'nom', 'w' => 1.8],
            ['label' => 'Valeur', 'key' => 'valeur', 'w' => 0.9, 'align' => 'R'], ['label' => 'Niveau', 'key' => 'niveau', 'w' => 0.7],
            ['label' => 'Interprétation', 'key' => 'interpretation', 'w' => 4]],
            array_map(static fn($r) => ['annee' => $r['annee'] ?: 'Vie', 'nom' => $r['nom'],
                'valeur' => $r['valeur'] !== null ? fmt_nombre($r['valeur'], (int) $r['decimales']) . ' ' . $r['unite'] : 'N/D',
                'niveau' => libelle('niveau', $r['niveau']), 'interpretation' => $r['interpretation']], $resultats), 7.5);
    }
    if ($historique) {
        $pdf->titreSection('Historique');
        $pdf->tableau([['label' => 'Date', 'key' => 'date', 'w' => 0.8], ['label' => 'Type', 'key' => 'type', 'w' => 1],
            ['label' => 'Description', 'key' => 'resume', 'w' => 4.5], ['label' => 'Par', 'key' => 'par', 'w' => 1.2]],
            array_map(static fn($h) => ['date' => fmt_date($h['date_evenement']), 'type' => libelle('type_evenement', $h['type_evenement']),
                'resume' => $h['resume'], 'par' => $h['nom_complet']], array_slice($historique, 0, 100)), 7.5);
    }
    $pdf->envoyer('fiche_' . $a['boucle'] . '.pdf');
}

// -------------------------------------------------------------- affichage
$icones = ['creation' => 'bi-plus-circle', 'modification' => 'bi-pencil', 'suppression' => 'bi-trash', 'sortie' => 'bi-box-arrow-right',
    'pesee' => 'bi-speedometer2', 'soin' => 'bi-heart-pulse', 'saillie' => 'bi-gender-ambiguous', 'mise_bas' => 'bi-stars',
    'naissance' => 'bi-star', 'lait' => 'bi-droplet', 'mouvement' => 'bi-arrow-left-right', 'indice' => 'bi-graph-up'];
$titre = 'Fiche ' . $a['boucle'];
require INC . '/entete.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h1 class="h4 titre-page mb-1"><i class="bi <?= $a['sexe'] === 'F' ? 'bi-gender-female text-danger' : 'bi-gender-male text-primary' ?> me-1"></i>
            <?= e($a['boucle']) ?> <?= $a['nom'] ? '<span class="text-muted">— ' . e($a['nom']) . '</span>' : '' ?> <?= badge_statut($a['statut']) ?></h1>
        <div class="text-muted small"><?= e($a['categorie']) ?> · <?= e(libelle('espece', $a['espece'])) ?> · fiche créée le <?= fmt_date($a['cree_le'], true) ?><?= $a['cree_par_nom'] ? ' par ' . e($a['cree_par_nom']) : '' ?><?= $a['modifie_le'] ? ' · modifiée le ' . fmt_date($a['modifie_le'], true) : '' ?></div>
    </div>
    <div class="d-flex flex-wrap gap-1 d-print-none">
        <a href="animaux.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
        <?php if (peut('animaux.modifier')): ?><a href="<?= e(url('animal_form.php', ['id' => $id])) ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil me-1"></i>Modifier</a><?php endif; ?>
        <button class="btn btn-outline-secondary btn-sm" data-imprimer><i class="bi bi-printer me-1"></i>Imprimer</button>
        <?php if (peut('animaux.exporter')): ?><a href="<?= e(url('animal.php', ['id' => $id, 'pdf' => 1])) ?>" target="_blank" class="btn btn-outline-danger btn-sm"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a><?php endif; ?>
        <?php if (peut('indices.calculer')): ?>
            <form method="post" action="animal.php" class="d-inline-flex gap-1"><?= champ_csrf() ?>
                <input type="hidden" name="action" value="calculer"><input type="hidden" name="id" value="<?= $id ?>">
                <select name="annee" class="form-select form-select-sm w-auto" aria-label="Année">
                    <?php for ($y = (int) date('Y'); $y >= (int) substr($a['date_entree'], 0, 4); $y--): ?><option><?= $y ?></option><?php endfor; ?>
                </select>
                <button class="btn btn-outline-success btn-sm text-nowrap"><i class="bi bi-calculator me-1"></i>Calculer indices</button>
            </form>
        <?php endif; ?>
        <?php if (peut('animaux.supprimer')): ?><?= bouton_supprimer('animaux.php', $id, 'Supprimer définitivement ' . $a['boucle'] . ' et tous ses suivis ?', [], true) ?><?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-5"><div class="card h-100"><div class="card-header"><i class="bi bi-card-text me-1"></i>Identification</div><div class="card-body">
        <dl class="row mb-0 small">
            <?php foreach ($identite as $k => $v): if ($v === '—' || $v === '' || in_array($k, ['N° boucle', 'Nom'], true)) continue; ?>
                <dt class="col-5 text-muted fw-normal"><?= e($k) ?></dt><dd class="col-7 mb-1 fw-semibold<?= in_array($k, ['Sortie', 'Prix de vente'], true) ? ' text-danger' : '' ?>"><?= e($v) ?></dd>
            <?php endforeach; ?>
            <dt class="col-5 text-muted fw-normal">Mère</dt><dd class="col-7 mb-1"><?= $a['mere_id'] ? '<a href="' . e(url('animal.php', ['id' => $a['mere_id']])) . '">' . e($a['mere_boucle']) . '</a> ' . e($a['mere_nom']) : '—' ?></dd>
            <dt class="col-5 text-muted fw-normal">Père</dt><dd class="col-7 mb-1"><?= $a['pere_id'] ? '<a href="' . e(url('animal.php', ['id' => $a['pere_id']])) . '">' . e($a['pere_boucle']) . '</a> ' . e($a['pere_nom']) : '—' ?></dd>
        </dl>
        <?php if ($a['remarques']): ?><hr><div class="small"><?= nl2br(e($a['remarques'])) ?></div><?php endif; ?>
    </div></div></div>
    <div class="col-lg-7"><div class="card h-100"><div class="card-header"><i class="bi bi-graph-up me-1"></i>Courbe de poids</div><div class="card-body">
        <?php if (count($poids) > 1): ?>
            <?= graphique(['type' => 'line', 'labels' => array_map(static fn($p) => fmt_date($p['date_evenement']), $poids),
                'datasets' => [['label' => 'Poids (kg)', 'data' => array_map(static fn($p) => (float) $p['poids'], $poids),
                    'borderColor' => '#195c3e', 'backgroundColor' => 'rgba(25,92,62,.15)', 'fill' => true, 'tension' => .25]]]) ?>
        <?php else: ?><p class="text-muted small mb-0">Au moins deux pesées sont nécessaires pour tracer la courbe.</p><?php endif; ?>
    </div></div></div>
</div>

<ul class="nav nav-tabs d-print-none" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#onglet-historique" type="button">Historique <span class="badge text-bg-light"><?= count($historique) ?></span></button></li>
    <?php foreach ($suivisFiche as $type => $sf): ?>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#onglet-<?= $type ?>" type="button"><i class="bi <?= $sf['s']['icone'] ?> me-1"></i><?= e($sf['s']['titre']) ?> <span class="badge text-bg-light"><?= count($sf['lignes']) ?></span></button></li>
    <?php endforeach; ?>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#onglet-descendance" type="button">Descendance <span class="badge text-bg-light"><?= count($descendants) ?></span></button></li>
    <?php if (peut('indices.voir')): ?><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#onglet-indices" type="button">Indices <span class="badge text-bg-light"><?= count($resultats) ?></span></button></li><?php endif; ?>
</ul>
<div class="tab-content card border-top-0 rounded-top-0">
    <div class="tab-pane fade show active p-3" id="onglet-historique">
        <h2 class="h6 impression-seule">Historique</h2>
        <div class="chrono">
            <?php foreach ($historique as $h): ?>
                <div class="chrono-item"><div class="small text-muted"><?= fmt_date($h['date_evenement']) ?> · <i class="bi <?= $icones[$h['type_evenement']] ?? 'bi-dot' ?>"></i> <?= e(libelle('type_evenement', $h['type_evenement'])) ?><?= $h['nom_complet'] ? ' · ' . e($h['nom_complet']) : '' ?></div>
                    <div><?= e($h['resume']) ?></div></div>
            <?php endforeach; ?>
            <?php if (!$historique): ?><p class="text-muted small">Aucun événement.</p><?php endif; ?>
        </div>
    </div>
    <?php foreach ($suivisFiche as $type => $sf): $s = $sf['s']; ?>
        <div class="tab-pane fade p-3" id="onglet-<?= $type ?>">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h6 mb-0"><i class="bi <?= $s['icone'] ?> me-1"></i><?= e($s['titre']) ?></h2>
                <?php if (peut("$type.ajouter") && $a['statut'] === 'actif' && (!$s['sexe'] || $s['sexe'] === $a['sexe'])): ?>
                    <a class="btn btn-foret btn-sm d-print-none" href="<?= e(url('suivi_form.php', ['type' => $type, 'animal' => $a['boucle'], 'retour' => 'animal'])) ?>"><i class="bi bi-plus-lg me-1"></i>Ajouter</a>
                <?php endif; ?>
            </div>
            <div class="table-responsive"><table class="table table-sm table-hover mb-0">
                <thead><tr><?php foreach ($s['colonnes'] as $c): ?><th><?= e($s['champs'][$c]['libelle']) ?></th><?php endforeach; ?><th class="actions d-print-none"></th></tr></thead>
                <tbody>
                <?php foreach ($sf['lignes'] as $l): ?>
                    <tr><?php foreach ($s['colonnes'] as $c): ?><td class="<?= $s['champs'][$c]['type'] === 'nombre' ? 'text-end' : '' ?>"><?= suivi_valeur($s, $c, $l) ?></td><?php endforeach; ?>
                        <td class="actions d-print-none">
                            <?php if (peut("$type.modifier")): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('suivi_form.php', ['type' => $type, 'id' => $l['id'], 'retour' => 'animal'])) ?>"><i class="bi bi-pencil"></i></a><?php endif; ?>
                            <?php if (peut("$type.exporter")): ?><a class="btn btn-sm btn-outline-danger" target="_blank" href="<?= e(url('suivi_pdf.php', ['type' => $type, 'id' => $l['id']])) ?>"><i class="bi bi-file-earmark-pdf"></i></a><?php endif; ?>
                            <?php if (peut("$type.supprimer")): ?><?= bouton_supprimer(url('suivis.php', ['type' => $type]), (int) $l['id'], 'Supprimer cet enregistrement ?', ['retour' => 'animal', 'animal_id' => $id]) ?><?php endif; ?>
                        </td></tr>
                <?php endforeach; ?>
                <?php if (!$sf['lignes']): ?><tr><td colspan="<?= count($s['colonnes']) + 1 ?>" class="text-muted small">Aucun enregistrement.</td></tr><?php endif; ?>
                </tbody>
            </table></div>
        </div>
    <?php endforeach; ?>
    <div class="tab-pane fade p-3" id="onglet-descendance">
        <h2 class="h6 impression-seule">Descendance</h2>
        <table class="table table-sm mb-0"><thead><tr><th>N° boucle</th><th>Nom</th><th>Catégorie</th><th>Naissance</th><th>Statut</th></tr></thead><tbody>
            <?php foreach ($descendants as $x): ?>
                <tr><td><a href="<?= e(url('animal.php', ['id' => $x['id']])) ?>"><?= e($x['boucle']) ?></a></td><td><?= e($x['nom']) ?></td><td><?= e($x['categorie']) ?></td><td><?= fmt_date($x['date_naissance']) ?></td><td><?= badge_statut($x['statut']) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$descendants): ?><tr><td colspan="5" class="text-muted small">Aucun descendant enregistré.</td></tr><?php endif; ?>
        </tbody></table>
    </div>
    <?php if (peut('indices.voir')): ?>
        <div class="tab-pane fade p-3" id="onglet-indices">
            <h2 class="h6 impression-seule">Indices</h2>
            <table class="table table-sm mb-0"><thead><tr><th>Année</th><th>Indice</th><th class="text-end">Valeur</th><th>Niveau</th><th>Interprétation</th><th class="d-print-none"></th></tr></thead><tbody>
                <?php foreach ($resultats as $r): ?>
                    <tr class="niveau-<?= e($r['niveau']) ?>"><td><?= $r['annee'] ?: 'Vie entière' ?></td><td><?= e($r['nom']) ?></td>
                        <td class="text-end fw-semibold text-nowrap"><?= $r['valeur'] !== null ? fmt_nombre($r['valeur'], (int) $r['decimales']) . ' ' . e($r['unite']) : 'N/D' ?></td>
                        <td><?= badge_niveau($r['niveau']) ?></td><td class="small"><?= e($r['interpretation']) ?></td>
                        <td class="d-print-none"><a href="<?= e(url('indice_detail.php', ['id' => $r['id']])) ?>" class="btn btn-sm btn-outline-secondary" title="Détail du calcul"><i class="bi bi-info-circle"></i></a></td></tr>
                <?php endforeach; ?>
                <?php if (!$resultats): ?><tr><td colspan="6" class="text-muted small">Aucun indice calculé : utilisez « Calculer indices ».</td></tr><?php endif; ?>
            </tbody></table>
        </div>
    <?php endif; ?>
</div>
<?php require INC . '/pied.php';
