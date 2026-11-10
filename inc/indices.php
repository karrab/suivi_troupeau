<?php
defined('INC') || exit; // fichier inclus uniquement
/**
 * Calcul, interprétation et enregistrement des indices zootechniques.
 *
 *  1. Les « variables de base » sont calculées en SQL par agrégats groupés
 *     (une requête par famille de données, jamais une requête par animal).
 *  2. Chaque indice applique sa formule (modifiable dans formules.php).
 *  3. La valeur est interprétée selon les seuils de l'indice puis enregistrée
 *     dans resultats_indices (INSERT … ON DUPLICATE KEY UPDATE), avec la
 *     formule et les variables utilisées pour garder la traçabilité.
 */
require_once INC . '/formule.php';

/** Variables disponibles pour les indices « par animal ». */
function variables_animal(): array
{
    return [
        'age_jours'             => 'Âge en fin de période (jours)',
        'age_mois'              => 'Âge en fin de période (mois)',
        'est_femelle'           => '1 si femelle, 0 sinon',
        'est_actif'             => '1 si l\'animal est actif',
        'poids_naissance'       => 'Poids à la naissance (kg)',
        'poids_debut'           => 'Première pesée de la période (kg)',
        'poids_fin'             => 'Dernière pesée de la période (kg)',
        'jours_pesee'           => 'Jours entre première et dernière pesée de la période',
        'nb_pesees'             => 'Nombre de pesées dans la période',
        'nec_moyenne'           => 'Note d\'état corporel moyenne',
        'poids_30j'             => 'Poids le plus proche de 30 jours d\'âge (kg)',
        'poids_90j'             => 'Poids le plus proche de 90 jours d\'âge (kg)',
        'poids_sevrage'         => 'Poids le plus proche de l\'âge de sevrage (kg)',
        'nb_saillies'           => 'Nombre de saillies dans la période',
        'nb_mises_bas'          => 'Nombre de mises bas (hors avortements)',
        'nb_avortements'        => 'Nombre d\'avortements',
        'nb_nes'                => 'Nombre de nés (total)',
        'nb_nes_vivants'        => 'Nombre de nés vivants',
        'nb_morts_nes'          => 'Nombre de mort-nés',
        'age_premiere_mise_bas' => 'Âge à la première mise bas (jours)',
        'intervalle_mises_bas'  => 'Intervalle moyen entre mises bas (jours)',
        'nb_controles_lait'     => 'Nombre de contrôles laitiers',
        'lait_moyen'            => 'Production moyenne (L/jour)',
        'lait_max'              => 'Production maximale (L/jour)',
        'lait_total_estime'     => 'Lait total estimé sur la période (L, méthode des intervalles)',
        'tb_moyen'              => 'Taux butyreux moyen (g/L)',
        'tp_moyen'              => 'Taux protéique moyen (g/L)',
        'nb_soins'              => 'Nombre d\'interventions sanitaires',
        'nb_maladies'           => 'Nombre d\'épisodes de maladie',
        'cout_sante'            => 'Coût sanitaire de la période',
        'nb_descendants'        => 'Descendants nés dans la période',
    ];
}

/** Variables disponibles pour les indices « troupeau » (année + groupe). */
function variables_troupeau(): array
{
    return [
        'effectif_debut'          => 'Effectif au 1er jour de la période',
        'effectif_fin'            => 'Effectif au dernier jour de la période',
        'effectif_moyen'          => 'Effectif moyen ((début + fin) / 2)',
        'femelles_reproductrices' => 'Femelles présentes au 1er jour et en âge de reproduction',
        'males_reproducteurs'     => 'Mâles présents au 1er jour et en âge de reproduction',
        'femelles_saillies'       => 'Femelles saillies / mises à la lutte',
        'femelles_gestantes'      => 'Femelles diagnostiquées gestantes',
        'femelles_mises_bas'      => 'Femelles ayant mis bas',
        'mises_bas'               => 'Nombre de mises bas (hors avortements)',
        'avortements'             => 'Nombre d\'avortements',
        'nes_total'               => 'Nés (total)',
        'nes_vivants'             => 'Nés vivants',
        'morts_nes'               => 'Mort-nés',
        'naissances_males'        => 'Nés mâles',
        'naissances_femelles'     => 'Nés femelles',
        'jeunes_enregistres'      => 'Jeunes nés dans la période et enregistrés',
        'morts_jeunes'            => 'Jeunes morts avant l\'âge de sevrage',
        'sevres'                  => 'Jeunes nés dans la période ayant atteint le sevrage vivants',
        'poids_naissance_moyen'   => 'Poids moyen à la naissance (kg)',
        'gmq_moyen_jeunes'        => 'GMQ moyen des jeunes nés dans la période (g/j)',
        'morts_total'             => 'Morts (tous âges)',
        'morts_adultes'           => 'Morts après l\'âge de sevrage',
        'ventes'                  => 'Animaux vendus',
        'reformes'                => 'Animaux réformés',
        'pertes'                  => 'Animaux perdus',
        'achats'                  => 'Animaux achetés',
        'recettes_ventes'         => 'Montant des ventes',
        'depenses_achats'         => 'Montant des achats',
        'nb_soins'                => 'Interventions sanitaires',
        'nb_maladies'             => 'Épisodes de maladie',
        'nb_vaccinations'         => 'Vaccinations',
        'cout_sante'              => 'Coût sanitaire total',
        'nec_moyenne'             => 'Note d\'état corporel moyenne',
        'lait_moyen'              => 'Production moyenne par contrôle (L/jour)',
        'nb_controles_lait'       => 'Nombre de contrôles laitiers',
        'femelles_traites'        => 'Femelles contrôlées',
        'tb_moyen'                => 'Taux butyreux moyen (g/L)',
        'tp_moyen'                => 'Taux protéique moyen (g/L)',
    ];
}

function variables_portee(string $portee): array
{
    return $portee === 'animal' ? variables_animal() : variables_troupeau();
}

/** Bornes de la période ; $annee = 0 => vie entière jusqu'à aujourd'hui. */
function periode(int $annee): array
{
    $auj = date('Y-m-d');
    return $annee <= 0 ? ['1900-01-01', $auj] : [sprintf('%04d-01-01', $annee), min(sprintf('%04d-12-31', $annee), $auj)];
}

function annees_disponibles(): array
{
    $min = (int) (bd_valeur('SELECT YEAR(MIN(date_entree)) FROM animaux') ?: date('Y'));
    return range((int) date('Y'), min($min, (int) date('Y')));
}

function definitions_indices(string $portee, array $ids = []): array
{
    $sql = 'SELECT * FROM indices WHERE portee = :p AND actif = 1';
    if ($ids) {
        $sql .= ' AND id IN (' . implode(',', array_map('intval', $ids)) . ')';
    }
    return bd_tous($sql . ' ORDER BY ordre, id', ['p' => $portee]);
}

// =====================================================================
//  Interprétation
// =====================================================================
/** @return array [niveau, texte] */
function interpreter(array $def, ?float $valeur): array
{
    if ($valeur === null) {
        return ['nd', 'Non calculable : données insuffisantes ou division par zéro.'];
    }
    $bas = $def['seuil_bas'] !== null && $def['seuil_bas'] !== '' ? (float) $def['seuil_bas'] : null;
    $haut = $def['seuil_haut'] !== null && $def['seuil_haut'] !== '' ? (float) $def['seuil_haut'] : null;
    $niveau = 'nd';
    if ($bas !== null || $haut !== null) {
        if ($def['sens'] === 'bas') {
            $niveau = ($bas !== null && $valeur <= $bas) ? 'bon' : (($haut !== null && $valeur > $haut) ? 'faible' : 'moyen');
        } else {
            $niveau = ($haut !== null && $valeur >= $haut) ? 'bon' : (($bas !== null && $valeur < $bas) ? 'faible' : 'moyen');
        }
    }
    $modele = match ($niveau) {
        'bon'    => $def['texte_bon'] ?: 'Résultat satisfaisant.',
        'faible' => $def['texte_faible'] ?: 'Résultat insuffisant : à surveiller.',
        'moyen'  => $def['texte_moyen'] ?: 'Résultat moyen.',
        default  => $def['texte_moyen'] ?: 'Aucun seuil d\'interprétation défini.',
    };
    $dec = (int) $def['decimales'];
    return [$niveau, strtr($modele, [
        '{valeur}'     => number_format($valeur, $dec, ',', ' '),
        '{unite}'      => (string) $def['unite'],
        '{seuil_bas}'  => $bas !== null ? number_format($bas, $dec, ',', ' ') : '—',
        '{seuil_haut}' => $haut !== null ? number_format($haut, $dec, ',', ' ') : '—',
    ])];
}

// =====================================================================
//  Troupeau : année x groupe
// =====================================================================
/** Groupes d'un type de regroupement : valeur => libellé. */
function groupes(string $type): array
{
    return match ($type) {
        'espece'    => ['caprin' => 'Caprins', 'ovin' => 'Ovins'],
        'categorie' => bd_paires('SELECT id, libelle FROM categories ORDER BY ordre, id'),
        'race'      => bd_paires("SELECT id, CONCAT(nom, ' (', espece, ')') FROM races ORDER BY espece, nom"),
        'lot'       => bd_paires('SELECT id, nom FROM lots ORDER BY nom'),
        default     => ['' => 'Troupeau entier'],
    };
}

function condition_groupe(string $type, string $alias): string
{
    return match ($type) {
        'espece'    => "$alias.espece = :gv",
        'categorie' => "$alias.categorie_id = :gv",
        'race'      => "$alias.race_id = :gv",
        'lot'       => "$alias.lot_id = :gv",
        default     => '1 = 1',
    };
}

function variables_troupeau_calculer(int $annee, string $type = 'troupeau', string $valeur = ''): array
{
    [$d, $f] = periode($annee);
    $ageRepro = max(1, (int) parametre('age_reproduction_mois', 8));
    $sevrage = max(1, (int) parametre('age_sevrage_jours', 90));
    $g = condition_groupe($type, 'a');
    // Le groupe s'applique au jeune ou à sa mère pour les indicateurs de jeunes
    $gj = $type === 'troupeau' ? '1 = 1' : '(' . condition_groupe($type, 'j') . ' OR ' . condition_groupe($type, 'm') . ')';
    $p = ['d' => $d, 'f' => $f, 'gv' => $valeur];
    // Reproducteurs : présents au début de la campagne et en âge de reproduction
    $repro = $annee > 0
        ? "a.date_entree <= :d AND (a.date_sortie IS NULL OR a.date_sortie > :d) AND a.date_naissance <= DATE_SUB(:d, INTERVAL $ageRepro MONTH)"
        : "a.date_naissance <= DATE_SUB(COALESCE(a.date_sortie, :f), INTERVAL $ageRepro MONTH)";
    $v = [];

    $v += bd_un("
        SELECT SUM(a.date_entree <= :d AND a.date_naissance <= :d AND (a.date_sortie IS NULL OR a.date_sortie > :d)) AS effectif_debut,
               SUM(a.date_entree <= :f AND a.date_naissance <= :f AND (a.date_sortie IS NULL OR a.date_sortie > :f)) AS effectif_fin,
               SUM(a.sexe = 'F' AND $repro) AS femelles_reproductrices,
               SUM(a.sexe = 'M' AND $repro) AS males_reproducteurs,
               SUM(a.statut = 'mort' AND a.date_sortie BETWEEN :d AND :f) AS morts_total,
               SUM(a.statut = 'mort' AND a.date_sortie BETWEEN :d AND :f AND DATEDIFF(a.date_sortie, a.date_naissance) >= $sevrage) AS morts_adultes,
               SUM(a.statut = 'vendu' AND a.date_sortie BETWEEN :d AND :f) AS ventes,
               SUM(a.statut = 'reforme' AND a.date_sortie BETWEEN :d AND :f) AS reformes,
               SUM(a.statut = 'perdu' AND a.date_sortie BETWEEN :d AND :f) AS pertes,
               SUM(a.origine = 'achat' AND a.date_entree BETWEEN :d AND :f) AS achats,
               SUM(CASE WHEN a.statut = 'vendu' AND a.date_sortie BETWEEN :d AND :f THEN a.prix_vente ELSE 0 END) AS recettes_ventes,
               SUM(CASE WHEN a.origine = 'achat' AND a.date_entree BETWEEN :d AND :f THEN a.prix_achat ELSE 0 END) AS depenses_achats
        FROM animaux a WHERE $g", $p) ?? [];
    $v['effectif_moyen'] = ((float) $v['effectif_debut'] + (float) $v['effectif_fin']) / 2;

    $v += bd_un("
        SELECT COUNT(DISTINCT s.animal_id) AS femelles_saillies,
               COUNT(DISTINCT CASE WHEN s.diagnostic = 'gestante' THEN s.animal_id END) AS femelles_gestantes
        FROM saillies s JOIN animaux a ON a.id = s.animal_id
        WHERE s.date_evenement BETWEEN :d AND :f AND $g", $p) ?? [];

    $v += bd_un("
        SELECT COUNT(DISTINCT CASE WHEN mb.avortement = 0 THEN mb.animal_id END) AS femelles_mises_bas,
               SUM(mb.avortement = 0) AS mises_bas, SUM(mb.avortement = 1) AS avortements,
               SUM(CASE WHEN mb.avortement = 0 THEN mb.nes_total ELSE 0 END) AS nes_total,
               SUM(CASE WHEN mb.avortement = 0 THEN mb.nes_vivants ELSE 0 END) AS nes_vivants,
               SUM(CASE WHEN mb.avortement = 0 THEN mb.nes_males ELSE 0 END) AS naissances_males,
               SUM(CASE WHEN mb.avortement = 0 THEN mb.nes_femelles ELSE 0 END) AS naissances_femelles
        FROM mises_bas mb JOIN animaux a ON a.id = mb.animal_id
        WHERE mb.date_evenement BETWEEN :d AND :f AND $g", $p) ?? [];
    $v['morts_nes'] = (float) ($v['nes_total'] ?? 0) - (float) ($v['nes_vivants'] ?? 0);

    $v += bd_un("
        SELECT SUM(j.date_naissance BETWEEN :d AND :f) AS jeunes_enregistres,
               SUM(j.statut IN ('mort','perdu') AND j.date_sortie BETWEEN :d AND :f AND DATEDIFF(j.date_sortie, j.date_naissance) < $sevrage) AS morts_jeunes,
               SUM(j.date_naissance BETWEEN :d AND :f AND DATE_ADD(j.date_naissance, INTERVAL $sevrage DAY) <= CURDATE()
                   AND NOT (j.statut IN ('mort','perdu') AND j.date_sortie IS NOT NULL AND DATEDIFF(j.date_sortie, j.date_naissance) < $sevrage)) AS sevres,
               AVG(CASE WHEN j.date_naissance BETWEEN :d AND :f THEN j.poids_naissance END) AS poids_naissance_moyen
        FROM animaux j LEFT JOIN animaux m ON m.id = j.mere_id
        WHERE $gj AND (j.date_naissance BETWEEN :d AND :f OR j.date_sortie BETWEEN :d AND :f)", $p) ?? [];

    $v['gmq_moyen_jeunes'] = bd_valeur("
        SELECT AVG((pe.poids - j.poids_naissance) / DATEDIFF(pe.date_evenement, j.date_naissance) * 1000)
        FROM animaux j LEFT JOIN animaux m ON m.id = j.mere_id
        JOIN pesees pe ON pe.id = (SELECT p2.id FROM pesees p2 WHERE p2.animal_id = j.id AND p2.date_evenement <= :f
                                   AND p2.date_evenement > j.date_naissance ORDER BY p2.date_evenement DESC, p2.id DESC LIMIT 1)
        WHERE $gj AND j.date_naissance BETWEEN :d AND :f AND j.poids_naissance IS NOT NULL", $p);

    $v += bd_un("
        SELECT COUNT(*) AS nb_soins, SUM(s.type_soin = 'maladie') AS nb_maladies,
               SUM(s.type_soin = 'vaccination') AS nb_vaccinations, SUM(s.cout) AS cout_sante
        FROM soins s JOIN animaux a ON a.id = s.animal_id
        WHERE s.date_evenement BETWEEN :d AND :f AND $g", $p) ?? [];

    $v['nec_moyenne'] = bd_valeur("SELECT AVG(pe.note_etat) FROM pesees pe JOIN animaux a ON a.id = pe.animal_id
                                   WHERE pe.date_evenement BETWEEN :d AND :f AND $g", $p);

    $v += bd_un("
        SELECT AVG(l.quantite) AS lait_moyen, COUNT(*) AS nb_controles_lait, COUNT(DISTINCT l.animal_id) AS femelles_traites,
               AVG(l.taux_butyreux) AS tb_moyen, AVG(l.taux_proteique) AS tp_moyen
        FROM controles_laitiers l JOIN animaux a ON a.id = l.animal_id
        WHERE l.date_evenement BETWEEN :d AND :f AND $g", $p) ?? [];

    $out = [];
    foreach (variables_troupeau() as $k => $_) {
        $out[$k] = ($v[$k] ?? null) === null ? null : round((float) $v[$k], 6);
    }
    foreach (['cout_sante', 'recettes_ventes', 'depenses_achats'] as $k) {
        $out[$k] ??= 0.0;
    }
    return $out;
}

/** Calcule et enregistre les indices troupeau pour tous les groupes d'un type. */
function calculer_indices_troupeau(int $annee, string $type = 'troupeau', array $ids = []): int
{
    $defs = definitions_indices('troupeau', $ids);
    $lignes = [];
    foreach (groupes($type) as $val => $lib) {
        $vars = variables_troupeau_calculer($annee, $type, (string) $val);
        foreach ($defs as $def) {
            $lignes[] = ligne_resultat($def, $vars, $type, (string) $val, (string) $lib, null, $annee);
        }
    }
    enregistrer_resultats($lignes);
    return count($lignes);
}

// =====================================================================
//  Par animal
// =====================================================================
function variables_animaux_calculer(int $annee, ?int $animalId = null): array
{
    [$d, $f] = periode($annee);
    $sevrage = max(1, (int) parametre('age_sevrage_jours', 90));
    if ($animalId) {
        $pop = 'a.id = :aid';
        $p = ['aid' => $animalId, 'd' => $d, 'f' => $f];
    } else {
        $pop = 'a.date_entree <= :f AND (a.date_sortie IS NULL OR a.date_sortie >= :d)';
        $p = ['d' => $d, 'f' => $f];
    }
    $vars = [];
    foreach (bd_tous("SELECT a.id, a.boucle, a.nom, a.sexe, a.statut, a.date_naissance, a.poids_naissance, a.date_sortie FROM animaux a WHERE $pop", $p) as $a) {
        $jusqua = $a['date_sortie'] && $a['date_sortie'] < $f ? $a['date_sortie'] : $f;
        $age = max(0, (int) ((strtotime($jusqua) - strtotime($a['date_naissance'])) / 86400));
        $vars[$a['id']] = array_merge(array_fill_keys(array_keys(variables_animal()), null), [
            '_libelle' => trim($a['boucle'] . ' ' . ($a['nom'] ?? '')), '_naissance' => $a['date_naissance'],
            'age_jours' => $age, 'age_mois' => round($age / 30.4375, 2),
            'est_femelle' => $a['sexe'] === 'F' ? 1 : 0, 'est_actif' => $a['statut'] === 'actif' ? 1 : 0,
            'poids_naissance' => $a['poids_naissance'] !== null ? (float) $a['poids_naissance'] : null,
            'nb_pesees' => 0, 'nb_saillies' => 0, 'nb_mises_bas' => 0, 'nb_avortements' => 0, 'nb_nes' => 0,
            'nb_nes_vivants' => 0, 'nb_morts_nes' => 0, 'nb_controles_lait' => 0, 'nb_soins' => 0,
            'nb_maladies' => 0, 'cout_sante' => 0.0, 'nb_descendants' => 0,
        ]);
    }
    if (!$vars) {
        return [];
    }

    // Pesées (toute la vie jusqu'à la fin de la période : poids aux âges types)
    $cibles = ['poids_30j' => [30, 12], 'poids_90j' => [90, 20], 'poids_sevrage' => [$sevrage, 20]];
    $meilleur = [];
    $nec = [];
    $st = bd_requete("SELECT pe.animal_id, pe.date_evenement, pe.poids, pe.note_etat FROM pesees pe JOIN animaux a ON a.id = pe.animal_id
                      WHERE $pop AND pe.date_evenement <= :f ORDER BY pe.animal_id, pe.date_evenement, pe.id", $p);
    while ($w = $st->fetch()) {
        $id = $w['animal_id'];
        $v = &$vars[$id];
        $ageA = (int) ((strtotime($w['date_evenement']) - strtotime($v['_naissance'])) / 86400);
        foreach ($cibles as $k => [$cible, $tol]) {
            $ecart = abs($ageA - $cible);
            if ($ecart <= $tol && (!isset($meilleur[$id][$k]) || $ecart < $meilleur[$id][$k])) {
                $meilleur[$id][$k] = $ecart;
                $v[$k] = (float) $w['poids'];
            }
        }
        if ($w['date_evenement'] >= $d) {
            if ($v['nb_pesees'] === 0) {
                $v['poids_debut'] = (float) $w['poids'];
                $v['_premiere'] = $w['date_evenement'];
            }
            $v['poids_fin'] = (float) $w['poids'];
            $v['jours_pesee'] = (int) ((strtotime($w['date_evenement']) - strtotime($v['_premiere'])) / 86400);
            $v['nb_pesees']++;
            if ($w['note_etat'] !== null) {
                $nec[$id][] = (float) $w['note_etat'];
            }
        }
        unset($v);
    }
    foreach ($nec as $id => $l) {
        $vars[$id]['nec_moyenne'] = round(array_sum($l) / count($l), 2);
    }

    foreach (bd_tous("SELECT s.animal_id, COUNT(*) n FROM saillies s JOIN animaux a ON a.id = s.animal_id
                      WHERE $pop AND s.date_evenement BETWEEN :d AND :f GROUP BY s.animal_id", $p) as $r) {
        $vars[$r['animal_id']]['nb_saillies'] = (int) $r['n'];
    }

    // Mises bas (toute la vie pour l'intervalle et l'âge à la 1re mise bas)
    $dates = [];
    foreach (bd_tous("SELECT mb.animal_id, mb.date_evenement, mb.nes_total, mb.nes_vivants, mb.avortement FROM mises_bas mb
                      JOIN animaux a ON a.id = mb.animal_id WHERE $pop AND mb.date_evenement <= :f
                      ORDER BY mb.animal_id, mb.date_evenement, mb.id", $p) as $b) {
        $id = $b['animal_id'];
        if (!(int) $b['avortement']) {
            $dates[$id][] = $b['date_evenement'];
        }
        if ($b['date_evenement'] >= $d) {
            if ((int) $b['avortement']) {
                $vars[$id]['nb_avortements']++;
            } else {
                $vars[$id]['nb_mises_bas']++;
                $vars[$id]['nb_nes'] += (int) $b['nes_total'];
                $vars[$id]['nb_nes_vivants'] += (int) $b['nes_vivants'];
                $vars[$id]['nb_morts_nes'] += (int) $b['nes_total'] - (int) $b['nes_vivants'];
            }
        }
    }
    foreach ($dates as $id => $l) {
        $vars[$id]['age_premiere_mise_bas'] = (int) ((strtotime($l[0]) - strtotime($vars[$id]['_naissance'])) / 86400);
        if (count($l) > 1) {
            $iv = [];
            for ($i = 1, $n = count($l); $i < $n; $i++) {
                $iv[] = (strtotime($l[$i]) - strtotime($l[$i - 1])) / 86400;
            }
            $vars[$id]['intervalle_mises_bas'] = round(array_sum($iv) / count($iv), 1);
        }
    }

    // Lait (total estimé par la méthode des intervalles / trapèzes)
    $lait = [];
    foreach (bd_tous("SELECT l.animal_id, l.date_evenement, l.quantite, l.taux_butyreux, l.taux_proteique FROM controles_laitiers l
                      JOIN animaux a ON a.id = l.animal_id WHERE $pop AND l.date_evenement BETWEEN :d AND :f
                      ORDER BY l.animal_id, l.date_evenement, l.id", $p) as $l) {
        $lait[$l['animal_id']][] = $l;
    }
    foreach ($lait as $id => $l) {
        $q = array_map(static fn($x) => (float) $x['quantite'], $l);
        $tb = array_filter(array_column($l, 'taux_butyreux'), static fn($x) => $x !== null);
        $tp = array_filter(array_column($l, 'taux_proteique'), static fn($x) => $x !== null);
        $total = 0.0;
        for ($i = 1, $n = count($l); $i < $n; $i++) {
            $total += ($q[$i] + $q[$i - 1]) / 2 * ((strtotime($l[$i]['date_evenement']) - strtotime($l[$i - 1]['date_evenement'])) / 86400);
        }
        $vars[$id]['nb_controles_lait'] = count($q);
        $vars[$id]['lait_moyen'] = round(array_sum($q) / count($q), 3);
        $vars[$id]['lait_max'] = max($q);
        $vars[$id]['lait_total_estime'] = count($q) > 1 ? round($total, 2) : null;
        $vars[$id]['tb_moyen'] = $tb ? round(array_sum($tb) / count($tb), 2) : null;
        $vars[$id]['tp_moyen'] = $tp ? round(array_sum($tp) / count($tp), 2) : null;
    }

    foreach (bd_tous("SELECT s.animal_id, COUNT(*) n, SUM(s.type_soin = 'maladie') mal, COALESCE(SUM(s.cout), 0) cout
                      FROM soins s JOIN animaux a ON a.id = s.animal_id
                      WHERE $pop AND s.date_evenement BETWEEN :d AND :f GROUP BY s.animal_id", $p) as $r) {
        $vars[$r['animal_id']]['nb_soins'] = (int) $r['n'];
        $vars[$r['animal_id']]['nb_maladies'] = (int) $r['mal'];
        $vars[$r['animal_id']]['cout_sante'] = (float) $r['cout'];
    }

    foreach (['mere_id', 'pere_id'] as $col) {
        foreach (bd_tous("SELECT x.$col AS parent, COUNT(*) n FROM animaux x JOIN animaux a ON a.id = x.$col
                          WHERE $pop AND x.date_naissance BETWEEN :d AND :f GROUP BY x.$col", $p) as $r) {
            $vars[$r['parent']]['nb_descendants'] += (int) $r['n'];
        }
    }
    return $vars;
}

/** @return array ['enregistres' => int, 'animaux' => int] */
function calculer_indices_animaux(int $annee, ?int $animalId = null, array $ids = []): array
{
    $defs = definitions_indices('animal', $ids);
    $tous = variables_animaux_calculer($annee, $animalId);
    $lignes = [];
    foreach ($tous as $id => $vars) {
        $propres = array_intersect_key($vars, variables_animal());
        foreach ($defs as $def) {
            $lignes[] = ligne_resultat($def, $propres, 'animal', (string) $id, $vars['_libelle'], (int) $id, $annee);
        }
    }
    enregistrer_resultats($lignes);
    return ['enregistres' => count($lignes), 'animaux' => count($tous)];
}

// =====================================================================
//  Évaluation & enregistrement
// =====================================================================
function ligne_resultat(array $def, array $vars, string $type, string $val, string $lib, ?int $animalId, int $annee): array
{
    static $moteur = null;
    $moteur ??= new MoteurFormule();
    try {
        $valeur = $moteur->evaluer($def['formule'], $vars);
    } catch (InvalidArgumentException) {
        $valeur = null;
    }
    $valeur = $valeur !== null ? round($valeur, 4) : null;
    [$niveau, $texte] = interpreter($def, $valeur);
    $utilisees = [];
    try {
        foreach ($moteur->variables($def['formule']) as $n) {
            $utilisees[$n] = $vars[$n] ?? null;
        }
    } catch (InvalidArgumentException) {
    }
    return [
        'indice_id' => (int) $def['id'], 'type_groupe' => $type, 'valeur_groupe' => $val,
        'libelle_groupe' => mb_substr($lib, 0, 120), 'animal_id' => $animalId, 'annee' => $annee,
        'valeur' => $valeur, 'niveau' => $niveau, 'interpretation' => mb_substr($texte, 0, 500),
        'variables' => json_encode($utilisees, JSON_UNESCAPED_UNICODE), 'formule_utilisee' => $def['formule'],
        'calcule_par' => utilisateur_id(),
    ];
}

/** Insertion / remplacement par lots de 200 lignes dans une transaction. */
function enregistrer_resultats(array $lignes): void
{
    if (!$lignes) {
        return;
    }
    $cols = array_keys($lignes[0]);
    bd_transaction(static function () use ($lignes, $cols) {
        foreach (array_chunk($lignes, 200) as $lot) {
            $marque = '(' . implode(',', array_fill(0, count($cols), '?')) . ', NOW())';
            $params = [];
            foreach ($lot as $l) {
                foreach ($cols as $c) {
                    $params[] = $l[$c];
                }
            }
            bd_requete('INSERT INTO resultats_indices (' . implode(',', $cols) . ', calcule_le) VALUES '
                . implode(',', array_fill(0, count($lot), $marque))
                . ' ON DUPLICATE KEY UPDATE libelle_groupe = VALUES(libelle_groupe), animal_id = VALUES(animal_id), valeur = VALUES(valeur),
                    niveau = VALUES(niveau), interpretation = VALUES(interpretation), variables = VALUES(variables),
                    formule_utilisee = VALUES(formule_utilisee), calcule_par = VALUES(calcule_par), calcule_le = NOW()', $params);
        }
    });
    cache_invalider('indices');
}

/** Aperçu d'une formule (non enregistrée) sur les données réelles. */
function apercu_formule(array $def, int $annee): array
{
    $moteur = new MoteurFormule();
    if ($def['portee'] === 'animal') {
        $out = [];
        foreach (array_slice(variables_animaux_calculer($annee), 0, 5, true) as $vars) {
            $val = $moteur->evaluer($def['formule'], array_intersect_key($vars, variables_animal()));
            $out[] = ['libelle' => $vars['_libelle'], 'valeur' => $val, 'interp' => interpreter($def, $val)];
        }
        return $out;
    }
    $val = $moteur->evaluer($def['formule'], variables_troupeau_calculer($annee));
    return [['libelle' => 'Troupeau entier', 'valeur' => $val, 'interp' => interpreter($def, $val)]];
}
