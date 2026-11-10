<?php
/**
 * Ajout / modification d'un enregistrement de suivi (?type=...&id=...).
 * Règles métier : sexe imposé, même espèce pour le mâle/père, date de mise bas
 * prévue calculée, lot de l'animal mis à jour par un mouvement, création des
 * fiches des nouveau-nés, sortie automatique si un soin se termine par « mort ».
 */
require __DIR__ . '/inc/init.php';

$type = (string) ($_GET['type'] ?? $_POST['type'] ?? '');
$s = suivi($type);
$id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
exiger_permission($id ? "$type.modifier" : "$type.ajouter");
$ancien = $id ? suivi_charger($s, $id) : null;
$retour = (string) ($_POST['retour'] ?? $_GET['retour'] ?? '');

// ===================================================================== POST
if (est_post()) {
    $regles = [];
    $libs = [];
    foreach ($s['champs'] as $c => $d) {
        $libs[$c] = $d['libelle'];
        if (in_array($d['type'], ['animal', 'virtuel'], true)) {
            continue;
        }
        $r = !empty($d['obligatoire']) ? ['obligatoire'] : [];
        $r = array_merge($r, match ($d['type']) {
            'date'   => ['date'],
            'nombre' => array_values(array_filter(['nombre', isset($d['min']) ? 'min:' . $d['min'] : null, isset($d['max']) ? 'max:' . $d['max'] : null])),
            'liste'  => ['dans:' . implode(',', array_keys(libelles($d['options'])))],
            'lot'    => ['entier'],
            'case'   => ['case'],
            default  => isset($d['maxcar']) ? ['maxcar:' . $d['maxcar']] : [],
        });
        $regles[$c] = $r;
    }
    [$donnees, $err] = valider($_POST, $regles, $libs);

    // Champs « animal » saisis par numéro de boucle
    $principal = null;
    foreach ($s['champs'] as $c => $d) {
        if ($d['type'] !== 'animal') {
            continue;
        }
        $boucle = trim((string) ($_POST[$c . '_boucle'] ?? ''));
        $donnees[$c] = null;
        if ($boucle === '') {
            if (!empty($d['obligatoire'])) {
                $err[$c] = "Le champ « {$d['libelle']} » est obligatoire.";
            }
            continue;
        }
        $an = bd_un('SELECT id, boucle, sexe, espece, date_naissance, lot_id, race_id FROM animaux WHERE boucle = :b', ['b' => $boucle]);
        if (!$an) {
            $err[$c] = "Animal introuvable : $boucle";
        } elseif (!empty($d['sexe']) && $an['sexe'] !== $d['sexe']) {
            $err[$c] = "« {$d['libelle']} » : l'animal $boucle doit être " . ($d['sexe'] === 'F' ? 'une femelle.' : 'un mâle.');
        } elseif ($c !== 'animal_id' && $principal && $an['espece'] !== $principal['espece']) {
            $err[$c] = "« {$d['libelle']} » doit être de la même espèce que l'animal.";
        } else {
            if ($c === 'animal_id') {
                $principal = $an;
            }
            $donnees[$c] = (int) $an['id'];
        }
    }
    if ($principal && !empty($donnees['date_evenement'])) {
        if ($donnees['date_evenement'] < $principal['date_naissance']) {
            $err['date_evenement'] = 'La date est antérieure à la naissance de l\'animal.';
        } elseif ($donnees['date_evenement'] > date('Y-m-d', strtotime('+1 day'))) {
            $err['date_evenement'] = 'La date ne peut pas être dans le futur.';
        }
    }
    foreach ($s['champs'] as $c => $d) {
        if ($d['type'] === 'lot') {
            $donnees[$c] = $donnees[$c] ? (int) $donnees[$c] : null;
        }
        if (in_array($d['type'], ['texte', 'zone'], true) && ($donnees[$c] ?? '') === '') {
            $donnees[$c] = null;
        }
    }

    // Règles propres à chaque suivi
    if ($type === 'mises_bas') {
        if ($donnees['avortement']) {
            $donnees['nes_total'] = $donnees['nes_vivants'] = $donnees['nes_males'] = $donnees['nes_femelles'] = 0;
        } else {
            if ((int) $donnees['nes_vivants'] > (int) $donnees['nes_total']) {
                $err['nes_vivants'] = 'Les nés vivants ne peuvent pas dépasser le total.';
            }
            if ((int) $donnees['nes_males'] + (int) $donnees['nes_femelles'] > (int) $donnees['nes_total']) {
                $err['nes_males'] = 'Mâles + femelles ne peuvent pas dépasser le total.';
            }
        }
    }
    if ($type === 'saillies' && $principal && empty($donnees['mise_bas_prevue']) && !empty($donnees['date_evenement']) && $donnees['diagnostic'] !== 'vide') {
        $jours = (int) parametre($principal['espece'] === 'caprin' ? 'gestation_caprin_jours' : 'gestation_ovin_jours', 150);
        $donnees['mise_bas_prevue'] = date('Y-m-d', strtotime($donnees['date_evenement'] . " +$jours days"));
    }
    if ($type === 'mouvements' && $principal && empty($donnees['lot_depart_id'])) {
        $donnees['lot_depart_id'] = $principal['lot_id'] ? (int) $principal['lot_id'] : null;
    }

    if ($err) {
        garder_saisie($_POST, $err);
        rediriger(url('suivi_form.php', ['type' => $type, 'id' => $id ?: null, 'retour' => $retour]));
    }

    $creesJeunes = 0;
    if (!$ancien) {
        // ---------------------------------------------------------- ajout
        $donnees['cree_par'] = utilisateur_id();
        $id = bd_transaction(static function () use ($s, $type, $donnees, $principal, &$creesJeunes) {
            $nid = bd_inserer($s['table'], $donnees);
            $ligne = suivi_charger($s, $nid);
            historique_ajouter((int) $donnees['animal_id'], $donnees['date_evenement'], $s['evenement'], suivi_resume($s, $ligne), $s['table'], $nid);
            foreach (['male_id', 'pere_id'] as $c) {   // l'événement apparaît aussi chez le mâle
                if (!empty($donnees[$c])) {
                    historique_ajouter((int) $donnees[$c], $donnees['date_evenement'], $s['evenement'],
                        suivi_resume($s, $ligne) . ' — femelle ' . $ligne['animal_boucle'], $s['table'], $nid);
                }
            }
            // Effets de bord métier
            if ($type === 'mouvements' && !empty($donnees['lot_arrivee_id'])) {
                bd_requete('UPDATE animaux SET lot_id = :l, modifie_par = :u WHERE id = :id', ['l' => $donnees['lot_arrivee_id'], 'u' => utilisateur_id(), 'id' => $donnees['animal_id']]);
            }
            if ($type === 'pesees' && $donnees['contexte'] === 'naissance') {
                bd_requete('UPDATE animaux SET poids_naissance = :p WHERE id = :id AND poids_naissance IS NULL', ['p' => $donnees['poids'], 'id' => $donnees['animal_id']]);
            }
            if ($type === 'soins' && $donnees['evolution'] === 'mort') {
                $n = bd_requete("UPDATE animaux SET statut = 'mort', date_sortie = :d, motif_sortie = :m WHERE id = :id AND statut = 'actif'",
                    ['d' => $donnees['date_evenement'], 'm' => mb_substr('Maladie : ' . ($donnees['diagnostic'] ?? ''), 0, 160), 'id' => $donnees['animal_id']])->rowCount();
                if ($n) {
                    historique_ajouter((int) $donnees['animal_id'], $donnees['date_evenement'], 'sortie', 'Sortie : mort (suivi sanitaire)');
                }
            }
            if ($type === 'mises_bas' && !empty($_POST['creer_petits']) && !$donnees['avortement'] && (int) $donnees['nes_vivants'] > 0) {
                $creesJeunes = creer_nouveau_nes($donnees, $nid, $principal);
            }
            return $nid;
        });
        tracer('ajout', $type, $id, ucfirst($s['singulier']) . ' pour ' . $principal['boucle'], null, $donnees);
        cache_invalider('suivis', 'animaux');
        flash('success', ucfirst($s['singulier']) . ' enregistré(e).' . ($creesJeunes ? " $creesJeunes fiche(s) de nouveau-né créée(s)." : ''));
        if (isset($_POST['enregistrer_nouveau'])) {
            rediriger(url('suivi_form.php', ['type' => $type]));
        }
    } else {
        // --------------------------------------------------- modification
        bd_transaction(static function () use ($s, $id, $donnees, $ancien) {
            bd_modifier($s['table'], $donnees, $id);
            historique_synchroniser($s['table'], $id, (int) $ancien['animal_id'], (int) $donnees['animal_id'], $donnees['date_evenement'],
                suivi_resume($s, suivi_charger($s, $id)) . ' (modifié)');
        });
        $brut = array_intersect_key($ancien, $donnees);
        tracer('modification', $type, $id, ucfirst($s['singulier']) . ' n° ' . $id, $brut, $donnees);
        cache_invalider('suivis');
        flash('success', 'Modification enregistrée.');
    }
    rediriger($retour === 'animal' ? url('animal.php', ['id' => $donnees['animal_id'], 'onglet' => $type]) : url('suivis.php', ['type' => $type]));
}

/** Crée les fiches des nouveau-nés vivants d'une mise bas. Retourne leur nombre. */
function creer_nouveau_nes(array $mb, int $mbId, array $mere): int
{
    $cats = bd_paires('SELECT code, id FROM categories');
    $vivants = (int) $mb['nes_vivants'];
    $males = min((int) $mb['nes_males'], $vivants);
    $nurserie = bd_valeur("SELECT id FROM lots WHERE nom = 'Nurserie'");
    $base = $mere['boucle'] . '-' . date('ymd', strtotime($mb['date_evenement']));
    for ($i = 1; $i <= $vivants; $i++) {
        $sexe = $i <= $males ? 'M' : 'F';
        $code = $mere['espece'] === 'caprin' ? ($sexe === 'M' ? 'chevreau' : 'chevrette') : ($sexe === 'M' ? 'agneau' : 'agnelle');
        $boucle = $base . '-' . $i;
        for ($n = 1; bd_valeur('SELECT 1 FROM animaux WHERE boucle = :b', ['b' => $boucle]); $n++) {
            $boucle = $base . '-' . $i . chr(97 + $n);
        }
        $jeune = bd_inserer('animaux', [
            'boucle' => mb_substr($boucle, 0, 30), 'espece' => $mere['espece'], 'categorie_id' => (int) $cats[$code], 'sexe' => $sexe,
            'race_id' => $mere['race_id'], 'lot_id' => $nurserie ? (int) $nurserie : null, 'date_naissance' => $mb['date_evenement'],
            'type_naissance' => [1 => 'simple', 2 => 'double', 3 => 'triple'][min(3, (int) $mb['nes_total'])] ?? 'quadruple+',
            'mere_id' => (int) $mb['animal_id'], 'pere_id' => $mb['pere_id'] ?? null, 'origine' => 'naissance',
            'date_entree' => $mb['date_evenement'], 'cree_par' => utilisateur_id(),
        ]);
        historique_ajouter($jeune, $mb['date_evenement'], 'naissance', 'Né(e) de ' . $mere['boucle'] . ' (mise bas n° ' . $mbId . ')', 'mises_bas', $mbId);
        tracer('ajout', 'animal', $jeune, 'Création automatique du nouveau-né ' . $boucle);
    }
    return $vivants;
}

// ====================================================================== GET
$l = $ancien ?? ['date_evenement' => date('Y-m-d')];
if (!$ancien) {
    foreach ($s['champs'] as $c => $d) {
        if (isset($d['defaut'])) {
            $l[$c] = $d['defaut'];
        }
    }
    if (!empty($_GET['animal'])) {
        $l['animal_boucle'] = (string) $_GET['animal'];
    }
}
$err = erreurs_form();
$annuler = $retour === 'animal' && !empty($l['animal_id']) ? url('animal.php', ['id' => $l['animal_id'], 'onglet' => $type])
    : ($retour === 'animal' && !empty($l['animal_boucle']) ? url('animaux.php', ['q' => $l['animal_boucle'], 'statut' => 'tous']) : url('suivis.php', ['type' => $type]));
$titre = ($ancien ? 'Modifier : ' : 'Ajouter : ') . $s['singulier'];
require INC . '/entete.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 titre-page mb-0"><i class="bi <?= $s['icone'] ?> me-2"></i><?= e(ucfirst($titre)) ?></h1>
    <a href="<?= e($annuler) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Retour</a>
</div>
<?php if ($err): ?><div class="alert alert-danger">Veuillez corriger les erreurs signalées.</div><?php endif; ?>
<form method="post" action="suivi_form.php" novalidate>
    <?= champ_csrf() ?>
    <input type="hidden" name="type" value="<?= e($type) ?>"><input type="hidden" name="retour" value="<?= e($retour) ?>">
    <?php if ($ancien): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
    <div class="card"><div class="card-body row g-3">
        <?php foreach ($s['champs'] as $c => $d):
            $etoile = !empty($d['obligatoire']) ? ' *' : '';
            if ($d['type'] === 'virtuel'):
                if ($ancien) continue; ?>
                <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="<?= $c ?>" value="1" id="c_<?= $c ?>" checked>
                    <label class="form-check-label" for="c_<?= $c ?>"><?= e($d['libelle']) ?></label></div></div>
            <?php continue; endif; ?>
            <div class="<?= $d['type'] === 'zone' ? 'col-12' : 'col-md-4' ?>">
                <label class="form-label" for="c_<?= $c ?>"><?= e($d['libelle']) . $etoile ?></label>
                <?php if ($d['type'] === 'animal'):
                    $val = fv($l, $c . '_boucle', $c === 'animal_id' ? ($l['animal_boucle'] ?? '') : ($l[suivi_alias($c, 'animal')] ?? '')); ?>
                    <input id="c_<?= $c ?>" name="<?= $c ?>_boucle" class="form-control<?= invalide($err, $c) ?>" value="<?= e($val) ?>" placeholder="N° boucle"
                           data-recherche-animal<?= !empty($d['sexe']) ? ' data-sexe="' . $d['sexe'] . '"' : '' ?>>
                <?php elseif ($d['type'] === 'date'): ?>
                    <input type="date" id="c_<?= $c ?>" name="<?= $c ?>" class="form-control<?= invalide($err, $c) ?>" value="<?= e(fv($l, $c)) ?>">
                <?php elseif ($d['type'] === 'nombre'): ?>
                    <input type="number" id="c_<?= $c ?>" name="<?= $c ?>" class="form-control<?= invalide($err, $c) ?>" value="<?= e(fv($l, $c)) ?>" step="<?= e($d['pas'] ?? 'any') ?>"<?= isset($d['min']) ? ' min="' . $d['min'] . '"' : '' ?><?= isset($d['max']) ? ' max="' . $d['max'] . '"' : '' ?>>
                <?php elseif ($d['type'] === 'liste'): ?>
                    <select id="c_<?= $c ?>" name="<?= $c ?>" class="form-select<?= invalide($err, $c) ?>"><?= options(libelles($d['options']), fv($l, $c), empty($d['obligatoire']) && !isset($d['defaut']) ? '—' : null) ?></select>
                <?php elseif ($d['type'] === 'lot'): ?>
                    <select id="c_<?= $c ?>" name="<?= $c ?>" class="form-select<?= invalide($err, $c) ?>"><?= options(array_column(ref_lots(), 'nom', 'id'), fv($l, $c), '—') ?></select>
                <?php elseif ($d['type'] === 'case'): ?>
                    <div class="form-check form-switch mt-1"><input class="form-check-input" type="checkbox" id="c_<?= $c ?>" name="<?= $c ?>" value="1"<?= coche(fv($l, $c)) ?>></div>
                <?php elseif ($d['type'] === 'zone'): ?>
                    <textarea id="c_<?= $c ?>" name="<?= $c ?>" rows="3" class="form-control<?= invalide($err, $c) ?>"><?= e(fv($l, $c)) ?></textarea>
                <?php else: ?>
                    <input id="c_<?= $c ?>" name="<?= $c ?>" class="form-control<?= invalide($err, $c) ?>" value="<?= e(fv($l, $c)) ?>"<?= isset($d['maxcar']) ? ' maxlength="' . $d['maxcar'] . '"' : '' ?>>
                <?php endif; ?>
                <?= erreur_champ($err, $c) ?>
            </div>
        <?php endforeach; ?>
    </div></div>
    <div class="mt-3 d-flex gap-2">
        <button class="btn btn-foret"><i class="bi bi-check2 me-1"></i>Enregistrer</button>
        <?php if (!$ancien): ?><button class="btn btn-outline-success" name="enregistrer_nouveau" value="1"><i class="bi bi-plus me-1"></i>Enregistrer et nouveau</button><?php endif; ?>
        <a href="<?= e($annuler) ?>" class="btn btn-outline-secondary">Annuler</a>
    </div>
</form>
<?php require INC . '/pied.php';
