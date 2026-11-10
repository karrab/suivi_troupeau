<?php
/**
 * Ajout (sans id) ou modification (?id=) d'une fiche animal.
 * GET : affiche le formulaire ; POST : valide, enregistre, historise, trace.
 */
require __DIR__ . '/inc/init.php';

$id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
exiger_permission($id ? 'animaux.modifier' : 'animaux.ajouter');
$ancien = $id ? (bd_trouver('animaux', $id) ?? abandonner(404, 'Animal introuvable.')) : null;

$LIBELLES = [
    'boucle' => 'N° boucle', 'nom' => 'Nom', 'categorie_id' => 'Catégorie', 'race_id' => 'Race', 'lot_id' => 'Lot',
    'date_naissance' => 'Date de naissance', 'naissance_estimee' => 'Date estimée', 'poids_naissance' => 'Poids de naissance',
    'type_naissance' => 'Type de naissance', 'mere_id' => 'Mère', 'pere_id' => 'Père', 'origine' => 'Origine',
    'date_entree' => 'Date d\'entrée', 'prix_achat' => 'Prix d\'achat', 'robe' => 'Robe', 'statut' => 'Statut',
    'date_sortie' => 'Date de sortie', 'motif_sortie' => 'Motif de sortie', 'prix_vente' => 'Prix de vente', 'remarques' => 'Remarques',
    'espece' => 'Espèce', 'sexe' => 'Sexe', 'mere_boucle' => 'Mère', 'pere_boucle' => 'Père',
];

/** Valeur lisible d'un champ (pour l'historique). */
function lisible(string $champ, mixed $v): string
{
    if ($v === null || $v === '') {
        return '—';
    }
    return match ($champ) {
        'categorie_id' => ref_libelle_categorie((int) $v),
        'race_id' => ref_nom_race((int) $v),
        'lot_id' => ref_nom_lot((int) $v),
        'mere_id', 'pere_id' => (string) bd_valeur('SELECT boucle FROM animaux WHERE id = :id', ['id' => (int) $v]),
        'statut' => libelle('statut', (string) $v),
        'origine' => libelle('origine', (string) $v),
        'date_naissance', 'date_entree', 'date_sortie' => fmt_date((string) $v),
        default => (string) $v,
    };
}

// ===================================================================== POST
if (est_post()) {
    [$d, $err] = valider($_POST, [
        'boucle' => 'obligatoire|maxcar:30|motif:/^[\w\-\/. ]+$/u', 'nom' => 'maxcar:80', 'categorie_id' => 'obligatoire|entier',
        'race_id' => 'entier', 'lot_id' => 'entier', 'date_naissance' => 'obligatoire|date', 'naissance_estimee' => 'case',
        'poids_naissance' => 'nombre|min:0|max:20', 'type_naissance' => 'dans:simple,double,triple,quadruple+',
        'origine' => 'obligatoire|dans:naissance,achat,don,autre', 'date_entree' => 'obligatoire|date', 'prix_achat' => 'nombre|min:0',
        'robe' => 'maxcar:60', 'statut' => 'obligatoire|dans:actif,vendu,mort,reforme,perdu', 'date_sortie' => 'date',
        'motif_sortie' => 'maxcar:160', 'prix_vente' => 'nombre|min:0', 'remarques' => 'maxcar:5000',
        'mere_boucle' => 'maxcar:30', 'pere_boucle' => 'maxcar:30',
    ], $LIBELLES);

    $cat = ref_categories()[(int) $d['categorie_id']] ?? null;
    if (!$cat) {
        $err['categorie_id'] = 'Catégorie invalide.';
    } else {
        $d['espece'] = $cat['espece'];   // espèce et sexe déduits de la catégorie
        $d['sexe'] = $cat['sexe'];
    }
    $proprietaire = bd_valeur('SELECT id FROM animaux WHERE boucle = :b', ['b' => (string) $d['boucle']]);
    if ($proprietaire && (int) $proprietaire !== $id) {
        $err['boucle'] = 'Ce numéro de boucle existe déjà.';
    }
    if ($d['race_id'] && $cat && (ref_races()[(int) $d['race_id']]['espece'] ?? null) !== $cat['espece']) {
        $err['race_id'] = 'La race ne correspond pas à l\'espèce.';
    }
    if ($d['date_naissance'] && $d['date_naissance'] > date('Y-m-d')) {
        $err['date_naissance'] = 'La date de naissance ne peut pas être dans le futur.';
    }
    if ($d['date_entree'] && $d['date_naissance'] && $d['date_entree'] < $d['date_naissance']) {
        $err['date_entree'] = 'La date d\'entrée doit être postérieure à la naissance.';
    }
    if ($d['statut'] !== 'actif' && !$d['date_sortie']) {
        $err['date_sortie'] = 'La date de sortie est obligatoire pour un animal sorti.';
    }
    if ($d['statut'] === 'actif') {
        $d['date_sortie'] = $d['motif_sortie'] = $d['prix_vente'] = null;
    } elseif ($d['date_sortie'] && $d['date_entree'] && $d['date_sortie'] < $d['date_entree']) {
        $err['date_sortie'] = 'La date de sortie doit être postérieure à l\'entrée.';
    }
    foreach (['mere_boucle' => ['mere_id', 'F'], 'pere_boucle' => ['pere_id', 'M']] as $k => [$col, $sexe]) {
        $d[$col] = null;
        if ($d[$k]) {
            $parent = bd_un('SELECT id, sexe, espece, date_naissance FROM animaux WHERE boucle = :b', ['b' => $d[$k]]);
            if (!$parent) {
                $err[$k] = 'Parent introuvable : ' . $d[$k];
            } elseif ($parent['sexe'] !== $sexe) {
                $err[$k] = $sexe === 'F' ? 'La mère doit être une femelle.' : 'Le père doit être un mâle.';
            } elseif ($cat && $parent['espece'] !== $cat['espece']) {
                $err[$k] = 'Le parent doit être de la même espèce.';
            } elseif ((int) $parent['id'] === $id) {
                $err[$k] = 'Un animal ne peut pas être son propre parent.';
            } elseif ($d['date_naissance'] && $parent['date_naissance'] >= $d['date_naissance']) {
                $err[$k] = 'Le parent doit être né avant l\'animal.';
            } else {
                $d[$col] = (int) $parent['id'];
            }
        }
        unset($d[$k]);
    }
    foreach (['race_id', 'lot_id', 'categorie_id'] as $k) {
        $d[$k] = $d[$k] ? (int) $d[$k] : null;
    }
    foreach (['nom', 'robe', 'motif_sortie', 'remarques', 'type_naissance'] as $k) {
        $d[$k] = ($d[$k] ?? '') === '' ? null : $d[$k];
    }

    if ($err) {
        garder_saisie($_POST, $err);
        rediriger(url('animal_form.php', ['id' => $id ?: null]));
    }

    if (!$ancien) {
        // ---------------- ajout
        $d['cree_par'] = utilisateur_id();
        $id = bd_transaction(static function () use ($d) {
            $nid = bd_inserer('animaux', $d);
            historique_ajouter($nid, $d['date_entree'], 'creation', $d['origine'] === 'naissance'
                ? 'Naissance sur l\'exploitation — fiche créée' : 'Entrée dans le troupeau (' . libelle('origine', $d['origine']) . ')');
            if ($d['statut'] !== 'actif') {
                historique_ajouter($nid, $d['date_sortie'], 'sortie', 'Sortie : ' . libelle('statut', $d['statut']) . ($d['motif_sortie'] ? ' — ' . $d['motif_sortie'] : ''));
            }
            return $nid;
        });
        tracer('ajout', 'animal', $id, 'Ajout de l\'animal ' . $d['boucle'], null, $d);
        cache_invalider('animaux');
        flash('success', 'Animal ' . $d['boucle'] . ' enregistré.');
        rediriger(isset($_POST['enregistrer_nouveau']) ? 'animal_form.php' : url('animal.php', ['id' => $id]));
    }

    // ---------------- modification
    $d['modifie_par'] = utilisateur_id();
    [$avant, $apres] = differences($ancien, $d);
    unset($avant['modifie_par'], $apres['modifie_par']);
    bd_transaction(static function () use ($id, $d, $ancien, $avant, $apres, $LIBELLES) {
        bd_modifier('animaux', $d, $id);
        if ($apres) {
            $parts = [];
            foreach ($apres as $k => $v) {
                $parts[] = ($LIBELLES[$k] ?? $k) . ' : ' . lisible($k, $avant[$k]) . ' → ' . lisible($k, $v);
            }
            historique_ajouter($id, date('Y-m-d'), 'modification', 'Modification — ' . implode(' ; ', $parts), 'animaux', $id, ['avant' => $avant, 'apres' => $apres]);
        }
        if ($ancien['statut'] === 'actif' && $d['statut'] !== 'actif') {
            historique_ajouter($id, $d['date_sortie'], 'sortie', 'Sortie : ' . libelle('statut', $d['statut']) . ($d['motif_sortie'] ? ' — ' . $d['motif_sortie'] : ''));
        }
    });
    tracer('modification', 'animal', $id, 'Modification de l\'animal ' . $d['boucle'], $ancien, $d);
    cache_invalider('animaux');
    flash('success', 'Fiche mise à jour.');
    rediriger(url('animal.php', ['id' => $id]));
}

// ====================================================================== GET
$a = $ancien ?? ['statut' => 'actif', 'origine' => 'naissance', 'date_entree' => date('Y-m-d')];
if ($ancien) {
    $a['mere_boucle'] = $ancien['mere_id'] ? bd_valeur('SELECT boucle FROM animaux WHERE id = :id', ['id' => $ancien['mere_id']]) : '';
    $a['pere_boucle'] = $ancien['pere_id'] ? bd_valeur('SELECT boucle FROM animaux WHERE id = :id', ['id' => $ancien['pere_id']]) : '';
}
$err = erreurs_form();
$titre = $ancien ? 'Modifier ' . $ancien['boucle'] : 'Nouvel animal';
require INC . '/entete.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 titre-page mb-0"><i class="bi bi-<?= $ancien ? 'pencil' : 'plus-circle' ?> me-2"></i><?= e($titre) ?></h1>
    <a href="<?= e($ancien ? url('animal.php', ['id' => $id]) : 'animaux.php') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Retour</a>
</div>
<?php if ($err): ?><div class="alert alert-danger">Veuillez corriger les erreurs signalées.</div><?php endif; ?>

<form method="post" action="animal_form.php" novalidate>
    <?= champ_csrf() ?>
    <?php if ($ancien): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
    <div class="row g-3">
        <div class="col-lg-6"><div class="card h-100"><div class="card-header">Identification</div><div class="card-body row g-2">
            <div class="col-md-6"><label class="form-label">N° boucle *</label>
                <input name="boucle" class="form-control<?= invalide($err, 'boucle') ?>" value="<?= e(fv($a, 'boucle')) ?>" required maxlength="30"><?= erreur_champ($err, 'boucle') ?></div>
            <div class="col-md-6"><label class="form-label">Nom</label>
                <input name="nom" class="form-control" value="<?= e(fv($a, 'nom')) ?>" maxlength="80"></div>
            <div class="col-md-6"><label class="form-label">Catégorie * <span class="small text-muted">(fixe l'espèce et le sexe)</span></label>
                <select name="categorie_id" class="form-select<?= invalide($err, 'categorie_id') ?>"><option value="">—</option>
                    <?php foreach (['caprin' => 'Caprins', 'ovin' => 'Ovins'] as $esp => $lib): ?>
                        <optgroup label="<?= $lib ?>">
                            <?php foreach (ref_categories() as $c): if ($c['espece'] !== $esp) continue; ?>
                                <option value="<?= $c['id'] ?>"<?= selectionne(fv($a, 'categorie_id'), $c['id']) ?>><?= e($c['libelle']) ?> (<?= $c['sexe'] ?>)</option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select><?= erreur_champ($err, 'categorie_id') ?></div>
            <div class="col-md-6"><label class="form-label">Race</label>
                <select name="race_id" class="form-select<?= invalide($err, 'race_id') ?>"><?= options(array_map(static fn($r) => $r['nom'] . ' (' . libelle('espece', $r['espece']) . ')', ref_races()), fv($a, 'race_id'), '—') ?></select><?= erreur_champ($err, 'race_id') ?></div>
            <div class="col-md-6"><label class="form-label">Robe / couleur</label><input name="robe" class="form-control" value="<?= e(fv($a, 'robe')) ?>" maxlength="60"></div>
            <div class="col-md-6"><label class="form-label">Lot</label>
                <select name="lot_id" class="form-select"><?= options(array_column(ref_lots(), 'nom', 'id'), fv($a, 'lot_id'), '—') ?></select></div>
        </div></div></div>
        <div class="col-lg-6"><div class="card h-100"><div class="card-header">Naissance & généalogie</div><div class="card-body row g-2">
            <div class="col-md-6"><label class="form-label">Date de naissance *</label>
                <input type="date" name="date_naissance" class="form-control<?= invalide($err, 'date_naissance') ?>" value="<?= e(fv($a, 'date_naissance')) ?>" max="<?= date('Y-m-d') ?>"><?= erreur_champ($err, 'date_naissance') ?>
                <div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="naissance_estimee" value="1" id="estimee"<?= coche(fv($a, 'naissance_estimee')) ?>><label class="form-check-label small" for="estimee">Date estimée</label></div></div>
            <div class="col-md-3"><label class="form-label">Poids naiss. (kg)</label>
                <input type="number" step="0.01" min="0" name="poids_naissance" class="form-control<?= invalide($err, 'poids_naissance') ?>" value="<?= e(fv($a, 'poids_naissance')) ?>"><?= erreur_champ($err, 'poids_naissance') ?></div>
            <div class="col-md-3"><label class="form-label">Type</label>
                <select name="type_naissance" class="form-select"><?= options(libelles('type_naissance'), fv($a, 'type_naissance'), '—') ?></select></div>
            <div class="col-md-6"><label class="form-label">Mère (n° boucle)</label>
                <input name="mere_boucle" class="form-control<?= invalide($err, 'mere_boucle') ?>" value="<?= e(fv($a, 'mere_boucle')) ?>" data-recherche-animal data-sexe="F" data-tous="1"><?= erreur_champ($err, 'mere_boucle') ?></div>
            <div class="col-md-6"><label class="form-label">Père (n° boucle)</label>
                <input name="pere_boucle" class="form-control<?= invalide($err, 'pere_boucle') ?>" value="<?= e(fv($a, 'pere_boucle')) ?>" data-recherche-animal data-sexe="M" data-tous="1"><?= erreur_champ($err, 'pere_boucle') ?></div>
        </div></div></div>
        <div class="col-lg-6"><div class="card h-100"><div class="card-header">Entrée</div><div class="card-body row g-2">
            <div class="col-md-6"><label class="form-label">Origine *</label>
                <select name="origine" id="origine" class="form-select"><?= options(libelles('origine'), fv($a, 'origine')) ?></select></div>
            <div class="col-md-6"><label class="form-label">Date d'entrée *</label>
                <input type="date" name="date_entree" class="form-control<?= invalide($err, 'date_entree') ?>" value="<?= e(fv($a, 'date_entree')) ?>"><?= erreur_champ($err, 'date_entree') ?></div>
            <div class="col-md-6 champs-achat"><label class="form-label">Prix d'achat (<?= e(parametre('devise', 'DH')) ?>)</label>
                <input type="number" step="0.01" min="0" name="prix_achat" class="form-control" value="<?= e(fv($a, 'prix_achat')) ?>"></div>
        </div></div></div>
        <div class="col-lg-6"><div class="card h-100"><div class="card-header">Statut & sortie</div><div class="card-body row g-2">
            <div class="col-md-6"><label class="form-label">Statut *</label>
                <select name="statut" id="statut" class="form-select"><?= options(libelles('statut'), fv($a, 'statut')) ?></select></div>
            <div class="col-md-6 champs-sortie"><label class="form-label">Date de sortie *</label>
                <input type="date" name="date_sortie" class="form-control<?= invalide($err, 'date_sortie') ?>" value="<?= e(fv($a, 'date_sortie')) ?>"><?= erreur_champ($err, 'date_sortie') ?></div>
            <div class="col-md-8 champs-sortie"><label class="form-label">Motif de sortie</label><input name="motif_sortie" class="form-control" value="<?= e(fv($a, 'motif_sortie')) ?>" maxlength="160"></div>
            <div class="col-md-4 champs-sortie"><label class="form-label">Prix de vente</label><input type="number" step="0.01" min="0" name="prix_vente" class="form-control" value="<?= e(fv($a, 'prix_vente')) ?>"></div>
        </div></div></div>
        <div class="col-12"><div class="card"><div class="card-body">
            <label class="form-label">Remarques</label><textarea name="remarques" rows="3" class="form-control"><?= e(fv($a, 'remarques')) ?></textarea>
        </div></div></div>
    </div>
    <div class="mt-3 d-flex gap-2">
        <button class="btn btn-foret"><i class="bi bi-check2 me-1"></i>Enregistrer</button>
        <?php if (!$ancien): ?><button class="btn btn-outline-success" name="enregistrer_nouveau" value="1"><i class="bi bi-plus me-1"></i>Enregistrer et nouveau</button><?php endif; ?>
        <a href="animaux.php" class="btn btn-outline-secondary">Annuler</a>
    </div>
</form>
<?php require INC . '/pied.php';
