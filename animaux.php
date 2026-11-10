<?php
/**
 * Liste des animaux : recherche avancée, tri, pagination keyset,
 * impression, export PDF (?pdf=1), suppression et reclassement (POST).
 */
require __DIR__ . '/inc/init.php';
require INC . '/pdf.php';
exiger_permission('animaux.voir');

// ------------------------------------------------------------ actions POST
if (est_post()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'supprimer') {
        exiger_permission('animaux.supprimer');
        $id = (int) ($_POST['id'] ?? 0);
        $ancien = bd_trouver('animaux', $id) ?? abandonner(404, 'Animal introuvable.');
        bd_supprimer('animaux', $id); // suivis, historique et résultats supprimés en cascade (InnoDB)
        tracer('suppression', 'animal', $id, 'Suppression de l\'animal ' . $ancien['boucle'], $ancien, null);
        cache_invalider('animaux', 'suivis', 'indices');
        flash('success', 'Animal ' . $ancien['boucle'] . ' supprimé.');
        rediriger('animaux.php');
    }
    if ($action === 'reclasser') {
        exiger_permission('animaux.modifier');
        $mois = max(1, (int) parametre('age_adulte_mois', 12));
        $cats = bd_paires('SELECT code, id FROM categories');
        $total = 0;
        bd_transaction(static function () use ($cats, $mois, &$total) {
            foreach (['chevreau' => 'bouc', 'chevrette' => 'chevre', 'agneau' => 'belier', 'agnelle' => 'brebis'] as $jeune => $adulte) {
                if (!isset($cats[$jeune], $cats[$adulte])) {
                    continue;
                }
                $cond = "categorie_id = :j AND statut = 'actif' AND date_naissance <= DATE_SUB(CURDATE(), INTERVAL $mois MONTH)";
                bd_requete("INSERT INTO historique_animaux (animal_id, date_evenement, type_evenement, resume, utilisateur_id)
                            SELECT id, CURDATE(), 'modification', :r, :u FROM animaux WHERE $cond",
                    ['r' => 'Reclassement automatique : ' . ref_libelle_categorie((int) $cats[$jeune]) . ' → ' . ref_libelle_categorie((int) $cats[$adulte]),
                     'u' => utilisateur_id(), 'j' => (int) $cats[$jeune]]);
                $total += bd_requete("UPDATE animaux SET categorie_id = :a WHERE $cond", ['a' => (int) $cats[$adulte], 'j' => (int) $cats[$jeune]])->rowCount();
            }
        });
        tracer('modification', 'animal', null, "Reclassement jeunes → adultes : $total animal(aux)");
        cache_invalider('animaux');
        flash('success', "$total animal(aux) reclassé(s) en catégorie adulte (âge ≥ $mois mois).");
        rediriger('animaux.php');
    }
    abandonner(400, 'Action inconnue.');
}

// --------------------------------------------- recherche avancée (WHERE indexés)
$g = $_GET;
$where = [];
$p = [];
if (($q = trim((string) ($g['q'] ?? ''))) !== '') {
    $where[] = '(a.boucle LIKE :q1 OR a.nom LIKE :q2)';
    $p['q1'] = $q . '%';
    $p['q2'] = '%' . $q . '%';
}
$statut = $g['statut'] ?? 'actif';
if ($statut !== 'tous' && isset(libelles('statut')[$statut])) {
    $where[] = 'a.statut = :statut';
    $p['statut'] = $statut;
}
foreach (['espece', 'sexe', 'origine'] as $k) {
    if (!empty($g[$k]) && isset(libelles($k)[$g[$k]])) {
        $where[] = "a.$k = :$k";
        $p[$k] = $g[$k];
    }
}
foreach (['categorie_id', 'race_id', 'lot_id'] as $k) {
    if (!empty($g[$k]) && ctype_digit((string) $g[$k])) {
        $where[] = "a.$k = :$k";
        $p[$k] = (int) $g[$k];
    }
}
foreach (['ne_du' => ['a.date_naissance', '>='], 'ne_au' => ['a.date_naissance', '<='], 'entre_du' => ['a.date_entree', '>='],
          'entre_au' => ['a.date_entree', '<='], 'sorti_du' => ['a.date_sortie', '>='], 'sorti_au' => ['a.date_sortie', '<=']] as $k => [$col, $op]) {
    if (!empty($g[$k]) && est_date((string) $g[$k])) {
        $where[] = "$col $op :$k";
        $p[$k] = $g[$k];
    }
}
if (isset($g['age_min']) && is_numeric($g['age_min'])) {
    $where[] = 'a.date_naissance <= DATE_SUB(CURDATE(), INTERVAL :age_min MONTH)';
    $p['age_min'] = (int) $g['age_min'];
}
if (isset($g['age_max']) && is_numeric($g['age_max'])) {
    $where[] = 'a.date_naissance >= DATE_SUB(CURDATE(), INTERVAL :age_max MONTH)';
    $p['age_max'] = (int) $g['age_max'];
}
foreach (['mere' => 'mere_id', 'pere' => 'pere_id'] as $k => $col) {
    if (!empty($g[$k])) {
        $where[] = "a.$col IN (SELECT x.id FROM animaux x WHERE x.boucle LIKE :$k)";
        $p[$k] = trim((string) $g[$k]) . '%';
    }
}
$tris = ['id' => 'a.id', 'boucle' => 'a.boucle', 'date_naissance' => 'a.date_naissance', 'date_entree' => 'a.date_entree'];
$tri = isset($tris[$g['tri'] ?? '']) ? $g['tri'] : 'id';

$requete = [
    'select'   => 'a.*, c.libelle AS categorie, r.nom AS race, l.nom AS lot,
                   (SELECT pe.poids FROM pesees pe WHERE pe.animal_id = a.id ORDER BY pe.date_evenement DESC, pe.id DESC LIMIT 1) AS dernier_poids',
    'from'     => 'FROM animaux a JOIN categories c ON c.id = a.categorie_id LEFT JOIN races r ON r.id = a.race_id LEFT JOIN lots l ON l.id = a.lot_id',
    'where'    => $where, 'params' => $p,
    'tri'      => $tris[$tri], 'cle_tri' => $tri, 'id' => 'a.id',
    'sens'     => strtolower((string) ($g['sens'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC',
    'par_page' => par_page(),
];

// ------------------------------------------------------------ export PDF
if (!empty($g['pdf'])) {
    exiger_permission('animaux.exporter');
    $lignes = tout_lire($requete);
    tracer('export', 'animal', null, 'Export PDF de la liste des animaux (' . count($lignes) . ' lignes)');
    $pdf = new PdfRapport('Liste des animaux', resume_filtres([
        'q' => 'Recherche', 'statut' => 'Statut', 'espece' => 'Espèce', 'sexe' => 'Sexe', 'categorie_id' => 'Catégorie n°',
        'race_id' => 'Race n°', 'lot_id' => 'Lot n°', 'ne_du' => 'Né après', 'ne_au' => 'Né avant', 'age_min' => 'Âge min',
        'age_max' => 'Âge max', 'mere' => 'Mère', 'pere' => 'Père',
    ]), 'L');
    $pdf->ajouterPage();
    $pdf->tableau([
        ['label' => 'N° boucle', 'key' => 'boucle', 'w' => 1.4], ['label' => 'Nom', 'key' => 'nom', 'w' => 1.3],
        ['label' => 'Catégorie', 'key' => 'categorie', 'w' => 1.3], ['label' => 'Sexe', 'key' => 'sexe', 'w' => 0.5, 'align' => 'C'],
        ['label' => 'Race', 'key' => 'race', 'w' => 1.1], ['label' => 'Lot', 'key' => 'lot', 'w' => 1.1],
        ['label' => 'Naissance', 'key' => 'naissance', 'w' => 0.9], ['label' => 'Âge', 'key' => 'age', 'w' => 0.9],
        ['label' => 'Origine', 'key' => 'origine', 'w' => 1.2], ['label' => 'Dern. poids', 'key' => 'poids', 'w' => 0.8, 'align' => 'R'],
        ['label' => 'Statut', 'key' => 'statut', 'w' => 0.7], ['label' => 'Sortie', 'key' => 'sortie', 'w' => 0.9],
    ], array_map(static fn($a) => [
        'boucle' => $a['boucle'], 'nom' => $a['nom'], 'categorie' => $a['categorie'], 'sexe' => $a['sexe'], 'race' => $a['race'],
        'lot' => $a['lot'], 'naissance' => fmt_date($a['date_naissance']), 'age' => fmt_age($a['date_naissance'], $a['date_sortie']),
        'origine' => libelle('origine', $a['origine']), 'poids' => $a['dernier_poids'] !== null ? fmt_nombre($a['dernier_poids']) . ' kg' : '',
        'statut' => libelle('statut', $a['statut']), 'sortie' => fmt_date($a['date_sortie']),
    ], $lignes));
    $pdf->envoyer('animaux_' . date('Ymd_His') . '.pdf');
}

$page = paginer($requete);
$total = compter($requete, 'animaux');
$avancee = (bool) array_filter(array_intersect_key($g, array_flip(['race_id', 'lot_id', 'origine', 'ne_du', 'ne_au', 'entre_du', 'entre_au', 'sorti_du', 'sorti_au', 'age_min', 'age_max', 'mere', 'pere'])), static fn($v) => $v !== '');

$titre = 'Animaux';
require INC . '/entete.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 titre-page mb-0"><i class="bi bi-collection me-2"></i>Animaux <span class="badge text-bg-light"><?= fmt_nombre($total, 0) ?></span></h1>
    <div class="d-flex gap-2 d-print-none">
        <?php if (peut('animaux.modifier')): ?>
            <form method="post" action="animaux.php" data-confirmer="Reclasser en adultes les jeunes de plus de <?= (int) parametre('age_adulte_mois', 12) ?> mois ?">
                <?= champ_csrf() ?><input type="hidden" name="action" value="reclasser">
                <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-up-circle me-1"></i>Reclasser les jeunes</button></form>
        <?php endif; ?>
        <?= boutons_liste('animaux.exporter') ?>
        <?php if (peut('animaux.ajouter')): ?><a href="animal_form.php" class="btn btn-foret btn-sm"><i class="bi bi-plus-lg me-1"></i>Nouvel animal</a><?php endif; ?>
    </div>
</div>

<div class="card carte-filtres mb-3 d-print-none"><div class="card-body">
    <form method="get" action="animaux.php">
        <div class="row g-2 align-items-end">
            <div class="col-md-3"><label class="form-label">N° boucle / nom</label>
                <input type="search" name="q" class="form-control form-control-sm" value="<?= e($g['q'] ?? '') ?>" data-recherche-animal data-tous="1"></div>
            <div class="col-6 col-md-2"><label class="form-label">Espèce</label>
                <select name="espece" class="form-select form-select-sm"><?= options(libelles('espece'), $g['espece'] ?? '', 'Toutes') ?></select></div>
            <div class="col-6 col-md-2"><label class="form-label">Catégorie</label>
                <select name="categorie_id" class="form-select form-select-sm"><?= options(array_column(ref_categories(), 'libelle', 'id'), $g['categorie_id'] ?? '', 'Toutes') ?></select></div>
            <div class="col-6 col-md-1"><label class="form-label">Sexe</label>
                <select name="sexe" class="form-select form-select-sm"><?= options(libelles('sexe'), $g['sexe'] ?? '', 'Tous') ?></select></div>
            <div class="col-6 col-md-2"><label class="form-label">Statut</label>
                <select name="statut" class="form-select form-select-sm"><?= options(['tous' => 'Tous'] + libelles('statut'), $statut) ?></select></div>
            <div class="col-md-2 d-flex gap-1">
                <button class="btn btn-foret btn-sm flex-fill"><i class="bi bi-search me-1"></i>Rechercher</button>
                <a href="animaux.php" class="btn btn-outline-secondary btn-sm" title="Réinitialiser"><i class="bi bi-x-lg"></i></a></div>
        </div>
        <a class="small d-inline-block mt-2" data-bs-toggle="collapse" href="#avancee" role="button"><i class="bi bi-sliders me-1"></i>Recherche avancée</a>
        <div class="collapse<?= $avancee ? ' show' : '' ?>" id="avancee"><div class="row g-2 mt-1">
            <div class="col-6 col-md-2"><label class="form-label">Race</label>
                <select name="race_id" class="form-select form-select-sm"><?= options(array_map(static fn($r) => $r['nom'] . ' (' . $r['espece'] . ')', ref_races()), $g['race_id'] ?? '', 'Toutes') ?></select></div>
            <div class="col-6 col-md-2"><label class="form-label">Lot</label>
                <select name="lot_id" class="form-select form-select-sm"><?= options(array_column(ref_lots(), 'nom', 'id'), $g['lot_id'] ?? '', 'Tous') ?></select></div>
            <div class="col-6 col-md-2"><label class="form-label">Origine</label>
                <select name="origine" class="form-select form-select-sm"><?= options(libelles('origine'), $g['origine'] ?? '', 'Toutes') ?></select></div>
            <div class="col-6 col-md-1"><label class="form-label">Âge min (mois)</label><input type="number" min="0" name="age_min" class="form-control form-control-sm" value="<?= e($g['age_min'] ?? '') ?>"></div>
            <div class="col-6 col-md-1"><label class="form-label">Âge max (mois)</label><input type="number" min="0" name="age_max" class="form-control form-control-sm" value="<?= e($g['age_max'] ?? '') ?>"></div>
            <div class="col-6 col-md-2"><label class="form-label">Mère (n° boucle)</label><input name="mere" class="form-control form-control-sm" value="<?= e($g['mere'] ?? '') ?>" data-recherche-animal data-sexe="F" data-tous="1"></div>
            <div class="col-6 col-md-2"><label class="form-label">Père (n° boucle)</label><input name="pere" class="form-control form-control-sm" value="<?= e($g['pere'] ?? '') ?>" data-recherche-animal data-sexe="M" data-tous="1"></div>
            <?php foreach (['ne_du' => 'Né(e) du', 'ne_au' => 'au', 'entre_du' => 'Entré(e) du', 'entre_au' => 'au', 'sorti_du' => 'Sorti(e) du', 'sorti_au' => 'au'] as $k => $l): ?>
                <div class="col-6 col-md-2"><label class="form-label"><?= e($l) ?></label><input type="date" name="<?= $k ?>" class="form-control form-control-sm" value="<?= e($g[$k] ?? '') ?>"></div>
            <?php endforeach; ?>
        </div></div>
        <?php foreach (['tri', 'sens', 'par_page'] as $k): if (!empty($g[$k])): ?><input type="hidden" name="<?= $k ?>" value="<?= e($g[$k]) ?>"><?php endif; endforeach; ?>
    </form>
</div></div>

<div class="impression-seule mb-2"><strong>Liste des animaux</strong> — imprimée le <?= date('d/m/Y H:i') ?></div>
<div class="card"><div class="table-responsive">
    <table class="table table-hover table-sm mb-0">
        <thead><tr>
            <th><?= lien_tri('boucle', 'N° boucle') ?></th><th>Nom</th><th>Catégorie</th><th>Race</th><th>Lot</th>
            <th><?= lien_tri('date_naissance', 'Naissance') ?></th><th>Âge</th><th class="text-end">Dern. poids</th>
            <th><?= lien_tri('date_entree', 'Entrée') ?></th><th>Statut</th><th class="actions d-print-none"><?= lien_tri('id', '#') ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ($page['lignes'] as $a): ?>
            <tr>
                <td class="fw-semibold"><a href="<?= e(url('animal.php', ['id' => $a['id']])) ?>"><?= e($a['boucle']) ?></a></td>
                <td><?= e($a['nom']) ?></td>
                <td><i class="bi <?= $a['sexe'] === 'F' ? 'bi-gender-female text-danger' : 'bi-gender-male text-primary' ?> me-1"></i><?= e($a['categorie']) ?></td>
                <td><?= e($a['race']) ?></td><td><?= e($a['lot']) ?></td>
                <td><?= fmt_date($a['date_naissance']) ?></td><td class="small"><?= e(fmt_age($a['date_naissance'], $a['date_sortie'])) ?></td>
                <td class="text-end"><?= $a['dernier_poids'] !== null ? fmt_nombre($a['dernier_poids']) . ' kg' : '' ?></td>
                <td><?= fmt_date($a['date_entree']) ?></td><td><?= badge_statut($a['statut']) ?></td>
                <td class="actions d-print-none">
                    <a class="btn btn-sm btn-outline-primary" href="<?= e(url('animal.php', ['id' => $a['id']])) ?>" title="Fiche"><i class="bi bi-eye"></i></a>
                    <?php if (peut('animaux.modifier')): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('animal_form.php', ['id' => $a['id']])) ?>" title="Modifier"><i class="bi bi-pencil"></i></a><?php endif; ?>
                    <?php if (peut('animaux.exporter')): ?><a class="btn btn-sm btn-outline-danger" target="_blank" href="<?= e(url('animal.php', ['id' => $a['id'], 'pdf' => 1])) ?>" title="Fiche PDF"><i class="bi bi-file-earmark-pdf"></i></a><?php endif; ?>
                    <?php if (peut('animaux.supprimer')): ?><?= bouton_supprimer('animaux.php', (int) $a['id'], 'Supprimer définitivement ' . $a['boucle'] . ' et tous ses suivis ?') ?><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$page['lignes']): ?><tr><td colspan="11" class="text-center text-muted py-4">Aucun animal ne correspond aux critères.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div></div>
<?= barre_pagination($page, $total) ?>
<?php require INC . '/pied.php';
