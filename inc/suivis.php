<?php
defined('INC') || exit; // fichier inclus uniquement
/**
 * Définition des modules de suivi. Les pages suivis.php, suivi_form.php et
 * suivi_pdf.php lisent cette configuration pour générer listes, filtres,
 * formulaires, validation, historique et exports PDF.
 *
 * Types de champs : animal (saisi par n° de boucle), date, nombre, texte,
 * zone (textarea), liste (ENUM, libellés dans libelles.php), lot, case, virtuel.
 */

function suivis_config(): array
{
    static $c = null;
    return $c ??= [
        'pesees' => [
            'table' => 'pesees', 'titre' => 'Pesées', 'singulier' => 'pesée', 'icone' => 'bi-speedometer2', 'evenement' => 'pesee',
            'champs' => [
                'animal_id'      => ['libelle' => 'Animal', 'type' => 'animal', 'obligatoire' => true],
                'date_evenement' => ['libelle' => 'Date de pesée', 'type' => 'date', 'obligatoire' => true],
                'poids'          => ['libelle' => 'Poids (kg)', 'type' => 'nombre', 'pas' => '0.01', 'min' => 0, 'max' => 300, 'obligatoire' => true],
                'note_etat'      => ['libelle' => 'Note d\'état corporel (1-5)', 'type' => 'nombre', 'pas' => '0.5', 'min' => 1, 'max' => 5],
                'contexte'       => ['libelle' => 'Contexte', 'type' => 'liste', 'options' => 'contexte_pesee', 'defaut' => 'routine'],
                'remarques'      => ['libelle' => 'Remarques', 'type' => 'texte', 'maxcar' => 255],
            ],
            'colonnes' => ['date_evenement', 'animal_id', 'poids', 'note_etat', 'contexte', 'remarques'],
            'filtres'  => ['contexte'],
            'resume'   => static fn(array $r) => 'Pesée : ' . fmt_nombre($r['poids']) . ' kg' . ($r['note_etat'] ? ' (NEC ' . fmt_nombre($r['note_etat'], 1) . ')' : ''),
        ],
        'soins' => [
            'table' => 'soins', 'titre' => 'Santé', 'singulier' => 'intervention sanitaire', 'icone' => 'bi-heart-pulse', 'evenement' => 'soin',
            'champs' => [
                'animal_id'      => ['libelle' => 'Animal', 'type' => 'animal', 'obligatoire' => true],
                'date_evenement' => ['libelle' => 'Date', 'type' => 'date', 'obligatoire' => true],
                'type_soin'      => ['libelle' => 'Type', 'type' => 'liste', 'options' => 'type_soin', 'obligatoire' => true],
                'diagnostic'     => ['libelle' => 'Diagnostic / motif', 'type' => 'texte', 'maxcar' => 160],
                'produit'        => ['libelle' => 'Produit / vaccin', 'type' => 'texte', 'maxcar' => 120],
                'dose'           => ['libelle' => 'Dose', 'type' => 'texte', 'maxcar' => 60],
                'veterinaire'    => ['libelle' => 'Vétérinaire / intervenant', 'type' => 'texte', 'maxcar' => 120],
                'cout'           => ['libelle' => 'Coût', 'type' => 'nombre', 'pas' => '0.01', 'min' => 0],
                'delai_attente'  => ['libelle' => 'Délai d\'attente (jours)', 'type' => 'nombre', 'pas' => '1', 'min' => 0, 'max' => 365],
                'date_rappel'    => ['libelle' => 'Date de rappel', 'type' => 'date'],
                'evolution'      => ['libelle' => 'Évolution', 'type' => 'liste', 'options' => 'evolution_soin', 'defaut' => 'na'],
                'remarques'      => ['libelle' => 'Remarques', 'type' => 'zone', 'maxcar' => 500],
            ],
            'colonnes'  => ['date_evenement', 'animal_id', 'type_soin', 'diagnostic', 'produit', 'cout', 'date_rappel', 'evolution'],
            'filtres'   => ['type_soin', 'evolution'],
            'recherche' => ['diagnostic', 'produit', 'veterinaire'],
            'resume'    => static fn(array $r) => libelle('type_soin', $r['type_soin']) . ($r['diagnostic'] ? ' : ' . $r['diagnostic'] : '') . ($r['produit'] ? ' — ' . $r['produit'] : ''),
        ],
        'saillies' => [
            'table' => 'saillies', 'titre' => 'Saillies / luttes', 'singulier' => 'saillie', 'icone' => 'bi-gender-ambiguous', 'evenement' => 'saillie', 'sexe' => 'F',
            'champs' => [
                'animal_id'       => ['libelle' => 'Femelle', 'type' => 'animal', 'sexe' => 'F', 'obligatoire' => true],
                'male_id'         => ['libelle' => 'Mâle (bouc / bélier)', 'type' => 'animal', 'sexe' => 'M'],
                'date_evenement'  => ['libelle' => 'Date de saillie', 'type' => 'date', 'obligatoire' => true],
                'methode'         => ['libelle' => 'Méthode', 'type' => 'liste', 'options' => 'methode_saillie', 'defaut' => 'monte_naturelle'],
                'diagnostic'      => ['libelle' => 'Diagnostic de gestation', 'type' => 'liste', 'options' => 'diagnostic_saillie', 'defaut' => 'non_fait'],
                'date_diagnostic' => ['libelle' => 'Date du diagnostic', 'type' => 'date'],
                'mise_bas_prevue' => ['libelle' => 'Mise bas prévue (calculée si vide)', 'type' => 'date'],
                'remarques'       => ['libelle' => 'Remarques', 'type' => 'texte', 'maxcar' => 255],
            ],
            'colonnes' => ['date_evenement', 'animal_id', 'male_id', 'methode', 'diagnostic', 'mise_bas_prevue'],
            'filtres'  => ['methode', 'diagnostic'],
            'resume'   => static fn(array $r) => 'Saillie (' . libelle('methode_saillie', $r['methode']) . ')'
                . (!empty($r['male_boucle']) ? ' avec ' . $r['male_boucle'] : '') . ' — diagnostic : ' . libelle('diagnostic_saillie', $r['diagnostic']),
        ],
        'mises_bas' => [
            'table' => 'mises_bas', 'titre' => 'Mises bas', 'singulier' => 'mise bas', 'icone' => 'bi-stars', 'evenement' => 'mise_bas', 'sexe' => 'F',
            'champs' => [
                'animal_id'      => ['libelle' => 'Mère', 'type' => 'animal', 'sexe' => 'F', 'obligatoire' => true],
                'pere_id'        => ['libelle' => 'Père', 'type' => 'animal', 'sexe' => 'M'],
                'date_evenement' => ['libelle' => 'Date de mise bas', 'type' => 'date', 'obligatoire' => true],
                'nes_total'      => ['libelle' => 'Nés (total)', 'type' => 'nombre', 'pas' => '1', 'min' => 0, 'max' => 8, 'obligatoire' => true, 'defaut' => 1],
                'nes_vivants'    => ['libelle' => 'Nés vivants', 'type' => 'nombre', 'pas' => '1', 'min' => 0, 'max' => 8, 'obligatoire' => true, 'defaut' => 1],
                'nes_males'      => ['libelle' => 'dont mâles', 'type' => 'nombre', 'pas' => '1', 'min' => 0, 'max' => 8, 'defaut' => 0],
                'nes_femelles'   => ['libelle' => 'dont femelles', 'type' => 'nombre', 'pas' => '1', 'min' => 0, 'max' => 8, 'defaut' => 0],
                'deroulement'    => ['libelle' => 'Déroulement', 'type' => 'liste', 'options' => 'deroulement', 'defaut' => 'facile'],
                'avortement'     => ['libelle' => 'Avortement', 'type' => 'case'],
                'remarques'      => ['libelle' => 'Remarques', 'type' => 'texte', 'maxcar' => 255],
                'creer_petits'   => ['libelle' => 'Créer automatiquement les fiches des nouveau-nés vivants', 'type' => 'virtuel'],
            ],
            'colonnes' => ['date_evenement', 'animal_id', 'pere_id', 'nes_total', 'nes_vivants', 'deroulement', 'avortement'],
            'filtres'  => ['deroulement'],
            'resume'   => static fn(array $r) => (int) $r['avortement'] ? 'Avortement'
                : sprintf('Mise bas : %d né(s) dont %d vivant(s) (%d M / %d F)', $r['nes_total'], $r['nes_vivants'], $r['nes_males'], $r['nes_femelles']),
        ],
        'lait' => [
            'table' => 'controles_laitiers', 'titre' => 'Production laitière', 'singulier' => 'contrôle laitier', 'icone' => 'bi-droplet', 'evenement' => 'lait', 'sexe' => 'F',
            'champs' => [
                'animal_id'      => ['libelle' => 'Femelle', 'type' => 'animal', 'sexe' => 'F', 'obligatoire' => true],
                'date_evenement' => ['libelle' => 'Date du contrôle', 'type' => 'date', 'obligatoire' => true],
                'quantite'       => ['libelle' => 'Quantité (L / jour)', 'type' => 'nombre', 'pas' => '0.01', 'min' => 0, 'max' => 20, 'obligatoire' => true],
                'taux_butyreux'  => ['libelle' => 'Taux butyreux (g/L)', 'type' => 'nombre', 'pas' => '0.1', 'min' => 0, 'max' => 200],
                'taux_proteique' => ['libelle' => 'Taux protéique (g/L)', 'type' => 'nombre', 'pas' => '0.1', 'min' => 0, 'max' => 200],
                'remarques'      => ['libelle' => 'Remarques', 'type' => 'texte', 'maxcar' => 255],
            ],
            'colonnes' => ['date_evenement', 'animal_id', 'quantite', 'taux_butyreux', 'taux_proteique', 'remarques'],
            'resume'   => static fn(array $r) => 'Contrôle laitier : ' . fmt_nombre($r['quantite']) . ' L/j',
        ],
        'mouvements' => [
            'table' => 'mouvements', 'titre' => 'Mouvements', 'singulier' => 'mouvement', 'icone' => 'bi-arrow-left-right', 'evenement' => 'mouvement',
            'champs' => [
                'animal_id'      => ['libelle' => 'Animal', 'type' => 'animal', 'obligatoire' => true],
                'date_evenement' => ['libelle' => 'Date', 'type' => 'date', 'obligatoire' => true],
                'type_mouvement' => ['libelle' => 'Type', 'type' => 'liste', 'options' => 'type_mouvement', 'defaut' => 'changement_lot'],
                'lot_depart_id'  => ['libelle' => 'Lot de départ (lot actuel si vide)', 'type' => 'lot'],
                'lot_arrivee_id' => ['libelle' => 'Lot d\'arrivée', 'type' => 'lot'],
                'remarques'      => ['libelle' => 'Remarques', 'type' => 'texte', 'maxcar' => 255],
            ],
            'colonnes' => ['date_evenement', 'animal_id', 'type_mouvement', 'lot_depart_id', 'lot_arrivee_id', 'remarques'],
            'filtres'  => ['type_mouvement'],
            'resume'   => static fn(array $r) => libelle('type_mouvement', $r['type_mouvement'])
                . ((!empty($r['lot_depart_nom']) || !empty($r['lot_arrivee_nom'])) ? ' : ' . ($r['lot_depart_nom'] ?: '—') . ' → ' . ($r['lot_arrivee_nom'] ?: '—') : ''),
        ],
    ];
}

/** Configuration d'un suivi (404 si le type est inconnu). */
function suivi(string $type): array
{
    $c = suivis_config()[$type] ?? abandonner(404, 'Module de suivi inconnu.');
    return $c + ['type' => $type, 'filtres' => [], 'recherche' => [], 'sexe' => null];
}

/** Nom de la colonne jointe : male_id => male_boucle, lot_arrivee_id => lot_arrivee_nom. */
function suivi_alias(string $champ, string $type): string
{
    $base = preg_replace('/_id$/', '', $champ);
    return $type === 'lot' ? $base . '_nom' : $base . '_boucle';
}

/** [select, from] avec les jointures vers animaux et lots. */
function suivi_sql(array $s): array
{
    $select = ['t.*', 'a.boucle AS animal_boucle', 'a.nom AS animal_nom', 'a.espece AS animal_espece'];
    $from = "FROM `{$s['table']}` t JOIN animaux a ON a.id = t.animal_id";
    $i = 0;
    foreach ($s['champs'] as $c => $d) {
        if ($c === 'animal_id' || !in_array($d['type'], ['animal', 'lot'], true)) {
            continue;
        }
        $j = 'j' . $i++;
        if ($d['type'] === 'animal') {
            $select[] = "$j.boucle AS " . suivi_alias($c, 'animal');
            $from .= " LEFT JOIN animaux $j ON $j.id = t.$c";
        } else {
            $select[] = "$j.nom AS " . suivi_alias($c, 'lot');
            $from .= " LEFT JOIN lots $j ON $j.id = t.$c";
        }
    }
    return [implode(', ', $select), $from];
}

/** Valeur affichable d'un champ ($html = lien et échappement). */
function suivi_valeur(array $s, string $champ, array $l, bool $html = true): string
{
    $d = $s['champs'][$champ] ?? ['type' => 'texte'];
    $v = $l[$champ] ?? null;
    $txt = match ($d['type']) {
        'animal' => $champ === 'animal_id'
            ? trim(($l['animal_boucle'] ?? '') . (!empty($l['animal_nom']) ? ' (' . $l['animal_nom'] . ')' : ''))
            : (string) ($l[suivi_alias($champ, 'animal')] ?? ''),
        'lot'    => (string) ($l[suivi_alias($champ, 'lot')] ?? ''),
        'date'   => fmt_date($v),
        'nombre' => $v === null ? '' : fmt_nombre($v, str_contains((string) ($d['pas'] ?? '1'), '.') ? 2 : 0),
        'liste'  => $v === null ? '' : libelle($d['options'], (string) $v),
        'case'   => (int) $v ? 'Oui' : 'Non',
        default  => (string) $v,
    };
    if (!$html) {
        return $txt;
    }
    if ($d['type'] === 'animal' && $txt !== '' && !empty($l[$champ])) {
        return '<a href="' . e(url('animal.php', ['id' => $l[$champ]])) . '">' . e($txt) . '</a>';
    }
    return e($txt);
}

function suivi_resume(array $s, array $l): string
{
    try {
        return (string) ($s['resume'])($l);
    } catch (Throwable) {
        return ucfirst($s['singulier']);
    }
}

function suivi_charger(array $s, int $id): array
{
    [$select, $from] = suivi_sql($s);
    return bd_un("SELECT $select $from WHERE t.id = :id", ['id' => $id]) ?? abandonner(404, 'Enregistrement introuvable.');
}
