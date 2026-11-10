<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Cache;
use App\Core\Database;
use App\Core\FormulaEngine;
use App\Core\Settings;

/**
 * Calcul, interprétation et enregistrement des indices zootechniques.
 *
 *  1. Les "variables de base" sont calculées en SQL (agrégats groupés,
 *     une requête par famille, pas de requête par animal).
 *  2. Chaque indice applique sa formule (paramétrable) sur ces variables.
 *  3. La valeur est interprétée selon les seuils de l'indice puis
 *     enregistrée (upsert) dans index_results, avec la formule et les
 *     variables utilisées (traçabilité).
 */
final class IndexCalculator
{
    /** Variables disponibles pour les indices "par animal". */
    public const ANIMAL_VARS = [
        'age_jours'            => 'Âge en fin de période (jours)',
        'age_mois'             => 'Âge en fin de période (mois)',
        'est_femelle'          => '1 si femelle, 0 sinon',
        'est_actif'            => '1 si l\'animal est actif',
        'poids_naissance'      => 'Poids à la naissance (kg)',
        'poids_debut'          => 'Première pesée de la période (kg)',
        'poids_fin'            => 'Dernière pesée de la période (kg)',
        'jours_pesee'          => 'Jours entre première et dernière pesée de la période',
        'nb_pesees'            => 'Nombre de pesées dans la période',
        'nec_moyenne'          => 'Note d\'état corporel moyenne',
        'poids_30j'            => 'Poids le plus proche de 30 jours d\'âge (kg)',
        'poids_90j'            => 'Poids le plus proche de 90 jours d\'âge (kg)',
        'poids_sevrage'        => 'Poids le plus proche de l\'âge de sevrage (kg)',
        'nb_saillies'          => 'Nombre de saillies dans la période',
        'nb_mises_bas'         => 'Nombre de mises bas (hors avortements)',
        'nb_avortements'       => 'Nombre d\'avortements',
        'nb_nes'               => 'Nombre de nés (total)',
        'nb_nes_vivants'       => 'Nombre de nés vivants',
        'nb_morts_nes'         => 'Nombre de mort-nés',
        'age_premiere_mise_bas'=> 'Âge à la première mise bas (jours)',
        'intervalle_mises_bas' => 'Intervalle moyen entre mises bas (jours)',
        'nb_controles_lait'    => 'Nombre de contrôles laitiers',
        'lait_moyen'           => 'Production moyenne (L/jour)',
        'lait_max'             => 'Production maximale (L/jour)',
        'lait_total_estime'    => 'Lait total estimé sur la période (L, méthode des intervalles)',
        'tb_moyen'             => 'Taux butyreux moyen (g/L)',
        'tp_moyen'             => 'Taux protéique moyen (g/L)',
        'nb_soins'             => 'Nombre d\'interventions sanitaires',
        'nb_maladies'          => 'Nombre d\'épisodes de maladie',
        'cout_sante'           => 'Coût sanitaire de la période',
        'nb_descendants'       => 'Descendants nés dans la période',
    ];

    /** Variables disponibles pour les indices "troupeau" (année + groupe). */
    public const HERD_VARS = [
        'effectif_debut'         => 'Effectif au 1er jour de la période',
        'effectif_fin'           => 'Effectif au dernier jour de la période',
        'effectif_moyen'         => 'Effectif moyen ((début + fin) / 2)',
        'femelles_reproductrices'=> 'Femelles présentes au 1er jour et en âge de reproduction',
        'males_reproducteurs'    => 'Mâles présents au 1er jour et en âge de reproduction',
        'femelles_saillies'      => 'Femelles saillies / mises à la lutte',
        'femelles_gestantes'     => 'Femelles diagnostiquées gestantes',
        'femelles_mises_bas'     => 'Femelles ayant mis bas',
        'mises_bas'              => 'Nombre de mises bas (hors avortements)',
        'avortements'            => 'Nombre d\'avortements',
        'nes_total'              => 'Nés (total)',
        'nes_vivants'            => 'Nés vivants',
        'morts_nes'              => 'Mort-nés',
        'naissances_males'       => 'Nés mâles',
        'naissances_femelles'    => 'Nés femelles',
        'jeunes_enregistres'     => 'Jeunes nés dans la période et enregistrés',
        'morts_jeunes'           => 'Jeunes morts avant l\'âge de sevrage',
        'sevres'                 => 'Jeunes nés dans la période ayant atteint le sevrage vivants',
        'poids_naissance_moyen'  => 'Poids moyen à la naissance (kg)',
        'gmq_moyen_jeunes'       => 'GMQ moyen des jeunes nés dans la période (g/j)',
        'morts_total'            => 'Morts (tous âges)',
        'morts_adultes'          => 'Morts après l\'âge de sevrage',
        'ventes'                 => 'Animaux vendus',
        'reformes'               => 'Animaux réformés',
        'pertes'                 => 'Animaux perdus',
        'achats'                 => 'Animaux achetés',
        'recettes_ventes'        => 'Montant des ventes',
        'depenses_achats'        => 'Montant des achats',
        'nb_soins'               => 'Interventions sanitaires',
        'nb_maladies'            => 'Épisodes de maladie',
        'nb_vaccinations'        => 'Vaccinations',
        'cout_sante'             => 'Coût sanitaire total',
        'nec_moyenne'            => 'Note d\'état corporel moyenne',
        'lait_moyen'             => 'Production moyenne par contrôle (L/jour)',
        'nb_controles_lait'      => 'Nombre de contrôles laitiers',
        'femelles_traites'       => 'Femelles contrôlées',
        'tb_moyen'               => 'Taux butyreux moyen (g/L)',
        'tp_moyen'               => 'Taux protéique moyen (g/L)',
    ];

    private FormulaEngine $engine;

    public function __construct()
    {
        $this->engine = new FormulaEngine();
    }

    public static function variablesFor(string $scope): array
    {
        return $scope === 'animal' ? self::ANIMAL_VARS : self::HERD_VARS;
    }

    /** Bornes de la période ; $year = 0 => vie entière jusqu'à aujourd'hui. */
    public static function period(int $year): array
    {
        $today = date('Y-m-d');
        if ($year <= 0) {
            return ['1900-01-01', $today];
        }
        $end = sprintf('%04d-12-31', $year);
        return [sprintf('%04d-01-01', $year), min($end, $today)];
    }

    public static function definitions(string $scope, array $ids = []): array
    {
        $sql = 'SELECT * FROM index_definitions WHERE scope = :s AND is_active = 1';
        $params = ['s' => $scope];
        if ($ids) {
            $sql .= ' AND id IN (' . implode(',', array_map('intval', $ids)) . ')';
        }
        return Database::all($sql . ' ORDER BY sort_order, id', $params);
    }

    // =================================================================
    //  Interprétation
    // =================================================================
    public static function interpret(array $def, ?float $value): array
    {
        if ($value === null) {
            return ['na', 'Non calculable : données insuffisantes ou division par zéro.'];
        }
        $low = $def['threshold_low'] !== null ? (float) $def['threshold_low'] : null;
        $high = $def['threshold_high'] !== null ? (float) $def['threshold_high'] : null;
        $level = 'na';
        if ($low !== null || $high !== null) {
            if ($def['better'] === 'bas') {
                $level = ($low !== null && $value <= $low) ? 'bon' : (($high !== null && $value > $high) ? 'faible' : 'moyen');
            } else {
                $level = ($high !== null && $value >= $high) ? 'bon' : (($low !== null && $value < $low) ? 'faible' : 'moyen');
            }
        }
        $tpl = match ($level) {
            'bon'    => $def['text_good'] ?: 'Résultat satisfaisant.',
            'faible' => $def['text_bad'] ?: 'Résultat insuffisant : à surveiller.',
            'moyen'  => $def['text_medium'] ?: 'Résultat moyen.',
            default  => $def['text_medium'] ?: 'Pas de seuil d\'interprétation défini.',
        };
        $dec = (int) $def['decimals'];
        $text = strtr($tpl, [
            '{valeur}'     => number_format($value, $dec, ',', ' '),
            '{unite}'      => (string) $def['unit'],
            '{seuil_bas}'  => $low !== null ? number_format($low, $dec, ',', ' ') : '—',
            '{seuil_haut}' => $high !== null ? number_format($high, $dec, ',', ' ') : '—',
        ]);
        return [$level, $text];
    }

    // =================================================================
    //  Troupeau : année x groupe
    // =================================================================
    /** Groupes disponibles pour un type de regroupement. */
    public static function groups(string $groupType): array
    {
        return match ($groupType) {
            'espece'    => ['caprin' => 'Caprins', 'ovin' => 'Ovins'],
            'categorie' => Database::pairs('SELECT id, label FROM categories ORDER BY sort_order, id'),
            'race'      => Database::pairs("SELECT id, CONCAT(name, ' (', species, ')') FROM breeds ORDER BY species, name"),
            'lot'       => Database::pairs('SELECT id, name FROM lots ORDER BY name'),
            default     => ['' => 'Troupeau entier'],
        };
    }

    private static function groupSql(string $groupType, string $alias): string
    {
        return match ($groupType) {
            'espece'    => "$alias.species = :gv",
            'categorie' => "$alias.category_id = :gv",
            'race'      => "$alias.breed_id = :gv",
            'lot'       => "$alias.lot_id = :gv",
            default     => '1 = 1',
        };
    }

    public function herdVariables(int $year, string $groupType = 'global', string $groupValue = ''): array
    {
        [$s, $e] = self::period($year);
        $ageRepro = max(1, (int) Settings::get('age_reproduction_mois', 8));
        $sevrage = max(1, (int) Settings::get('age_sevrage_jours', 90));
        $g = self::groupSql($groupType, 'a');
        $gy = $groupType === 'global' ? '1 = 1' : '(' . self::groupSql($groupType, 'y') . ' OR ' . self::groupSql($groupType, 'mo') . ')';
        $p = ['s' => $s, 'e' => $e, 'gv' => $groupValue];
        $v = [];
        // Reproducteurs : présents au début de la campagne et en âge de reproduction à cette date
        // (vie entière : présents à un moment et ayant atteint l'âge).
        $repro = $year > 0
            ? "a.entry_date <= :s AND (a.exit_date IS NULL OR a.exit_date > :s) AND a.birth_date <= DATE_SUB(:s, INTERVAL $ageRepro MONTH)"
            : "a.birth_date <= DATE_SUB(COALESCE(a.exit_date, :e), INTERVAL $ageRepro MONTH)";

        // Effectifs et reproducteurs
        $r = Database::one("
            SELECT
              SUM(a.entry_date <= :s AND a.birth_date <= :s AND (a.exit_date IS NULL OR a.exit_date > :s)) AS effectif_debut,
              SUM(a.entry_date <= :e AND a.birth_date <= :e AND (a.exit_date IS NULL OR a.exit_date > :e)) AS effectif_fin,
              SUM(a.sex = 'F' AND $repro) AS femelles_reproductrices,
              SUM(a.sex = 'M' AND $repro) AS males_reproducteurs,
              SUM(a.status = 'mort' AND a.exit_date BETWEEN :s AND :e) AS morts_total,
              SUM(a.status = 'mort' AND a.exit_date BETWEEN :s AND :e AND DATEDIFF(a.exit_date, a.birth_date) >= $sevrage) AS morts_adultes,
              SUM(a.status = 'vendu' AND a.exit_date BETWEEN :s AND :e) AS ventes,
              SUM(a.status = 'reforme' AND a.exit_date BETWEEN :s AND :e) AS reformes,
              SUM(a.status = 'perdu' AND a.exit_date BETWEEN :s AND :e) AS pertes,
              SUM(a.origin = 'achat' AND a.entry_date BETWEEN :s AND :e) AS achats,
              SUM(CASE WHEN a.status = 'vendu' AND a.exit_date BETWEEN :s AND :e THEN a.sale_price ELSE 0 END) AS recettes_ventes,
              SUM(CASE WHEN a.origin = 'achat' AND a.entry_date BETWEEN :s AND :e THEN a.purchase_price ELSE 0 END) AS depenses_achats
            FROM animals a WHERE $g", $p) ?? [];
        $v += $r;
        $v['effectif_moyen'] = ((float) $v['effectif_debut'] + (float) $v['effectif_fin']) / 2;

        // Reproduction
        $r = Database::one("
            SELECT COUNT(DISTINCT m.animal_id) AS femelles_saillies,
                   COUNT(DISTINCT CASE WHEN m.diagnosis = 'gestante' THEN m.animal_id END) AS femelles_gestantes
            FROM matings m JOIN animals a ON a.id = m.animal_id
            WHERE m.event_date BETWEEN :s AND :e AND $g", $p) ?? [];
        $v += $r;

        $r = Database::one("
            SELECT COUNT(DISTINCT CASE WHEN b.abortion = 0 THEN b.animal_id END) AS femelles_mises_bas,
                   SUM(b.abortion = 0) AS mises_bas,
                   SUM(b.abortion = 1) AS avortements,
                   SUM(CASE WHEN b.abortion = 0 THEN b.born_total ELSE 0 END) AS nes_total,
                   SUM(CASE WHEN b.abortion = 0 THEN b.born_alive ELSE 0 END) AS nes_vivants,
                   SUM(CASE WHEN b.abortion = 0 THEN b.born_males ELSE 0 END) AS naissances_males,
                   SUM(CASE WHEN b.abortion = 0 THEN b.born_females ELSE 0 END) AS naissances_femelles
            FROM births b JOIN animals a ON a.id = b.animal_id
            WHERE b.event_date BETWEEN :s AND :e AND $g", $p) ?? [];
        $v += $r;
        $v['morts_nes'] = (float) ($v['nes_total'] ?? 0) - (float) ($v['nes_vivants'] ?? 0);

        // Jeunes (le groupe s'applique au jeune ou à sa mère)
        $r = Database::one("
            SELECT SUM(y.birth_date BETWEEN :s AND :e) AS jeunes_enregistres,
                   SUM(y.status IN ('mort','perdu') AND y.exit_date BETWEEN :s AND :e AND DATEDIFF(y.exit_date, y.birth_date) < $sevrage) AS morts_jeunes,
                   SUM(y.birth_date BETWEEN :s AND :e
                       AND DATE_ADD(y.birth_date, INTERVAL $sevrage DAY) <= CURDATE()
                       AND NOT (y.status IN ('mort','perdu') AND y.exit_date IS NOT NULL AND DATEDIFF(y.exit_date, y.birth_date) < $sevrage)) AS sevres,
                   AVG(CASE WHEN y.birth_date BETWEEN :s AND :e THEN y.birth_weight END) AS poids_naissance_moyen
            FROM animals y LEFT JOIN animals mo ON mo.id = y.mother_id
            WHERE $gy AND (y.birth_date BETWEEN :s AND :e OR y.exit_date BETWEEN :s AND :e)", $p) ?? [];
        $v += $r;

        $v['gmq_moyen_jeunes'] = Database::value("
            SELECT AVG((w.weight - y.birth_weight) / DATEDIFF(w.event_date, y.birth_date) * 1000)
            FROM animals y
            LEFT JOIN animals mo ON mo.id = y.mother_id
            JOIN weighings w ON w.id = (
                SELECT w2.id FROM weighings w2
                WHERE w2.animal_id = y.id AND w2.event_date <= :e AND w2.event_date > y.birth_date
                ORDER BY w2.event_date DESC, w2.id DESC LIMIT 1)
            WHERE $gy AND y.birth_date BETWEEN :s AND :e AND y.birth_weight IS NOT NULL", $p);

        // Santé
        $r = Database::one("
            SELECT COUNT(*) AS nb_soins, SUM(h.event_type = 'maladie') AS nb_maladies,
                   SUM(h.event_type = 'vaccination') AS nb_vaccinations, SUM(h.cost) AS cout_sante
            FROM health_events h JOIN animals a ON a.id = h.animal_id
            WHERE h.event_date BETWEEN :s AND :e AND $g", $p) ?? [];
        $v += $r;

        // Pesées
        $v['nec_moyenne'] = Database::value("
            SELECT AVG(w.body_score) FROM weighings w JOIN animals a ON a.id = w.animal_id
            WHERE w.event_date BETWEEN :s AND :e AND $g", $p);

        // Lait
        $r = Database::one("
            SELECT AVG(l.quantity) AS lait_moyen, COUNT(*) AS nb_controles_lait,
                   COUNT(DISTINCT l.animal_id) AS femelles_traites, AVG(l.fat_rate) AS tb_moyen, AVG(l.protein_rate) AS tp_moyen
            FROM milk_records l JOIN animals a ON a.id = l.animal_id
            WHERE l.event_date BETWEEN :s AND :e AND $g", $p) ?? [];
        $v += $r;

        return self::normalize($v, self::HERD_VARS);
    }

    /** Variables nulles si moyenne sans données ; compteurs => 0. */
    private static function normalize(array $v, array $catalog): array
    {
        $out = [];
        foreach ($catalog as $k => $_) {
            $val = $v[$k] ?? null;
            $out[$k] = $val === null ? null : round((float) $val, 6);
        }
        foreach (['cout_sante', 'recettes_ventes', 'depenses_achats'] as $k) {
            if (array_key_exists($k, $out) && $out[$k] === null) {
                $out[$k] = 0.0;
            }
        }
        return $out;
    }

    /**
     * Calcule et enregistre les indices troupeau.
     * @return array ['saved' => int, 'results' => [...]]
     */
    public function computeHerd(int $year, string $groupType = 'global', array $indexIds = []): array
    {
        $defs = self::definitions('troupeau', $indexIds);
        $rows = [];
        foreach (self::groups($groupType) as $gValue => $gLabel) {
            $vars = $this->herdVariables($year, $groupType, (string) $gValue);
            foreach ($defs as $def) {
                $rows[] = $this->buildRow($def, $vars, $groupType, (string) $gValue, (string) $gLabel, null, $year);
            }
        }
        $this->save($rows);
        return ['saved' => count($rows), 'results' => $rows];
    }

    // =================================================================
    //  Par animal
    // =================================================================
    public function animalVariables(int $year, ?int $animalId = null): array
    {
        [$s, $e] = self::period($year);
        $sevrage = max(1, (int) Settings::get('age_sevrage_jours', 90));
        $pop = 'a.entry_date <= :e AND (a.exit_date IS NULL OR a.exit_date >= :s)';
        $p = ['s' => $s, 'e' => $e];
        if ($animalId) {
            $pop = 'a.id = :aid';
            $p = ['aid' => $animalId];
        }

        $vars = [];
        $animals = Database::all("SELECT a.id, a.tag, a.name, a.sex, a.status, a.birth_date, a.birth_weight, a.exit_date FROM animals a WHERE $pop", $p);
        foreach ($animals as $a) {
            $until = $a['exit_date'] && $a['exit_date'] < $e ? $a['exit_date'] : $e;
            $age = max(0, (int) ((strtotime($until) - strtotime($a['birth_date'])) / 86400));
            $vars[$a['id']] = array_fill_keys(array_keys(self::ANIMAL_VARS), null);
            $vars[$a['id']] = array_merge($vars[$a['id']], [
                '_label'         => trim($a['tag'] . ' ' . ($a['name'] ?? '')),
                '_birth'         => $a['birth_date'],
                'age_jours'      => $age,
                'age_mois'       => round($age / 30.4375, 2),
                'est_femelle'    => $a['sex'] === 'F' ? 1 : 0,
                'est_actif'      => $a['status'] === 'actif' ? 1 : 0,
                'poids_naissance'=> $a['birth_weight'] !== null ? (float) $a['birth_weight'] : null,
                'nb_pesees' => 0, 'nb_saillies' => 0, 'nb_mises_bas' => 0, 'nb_avortements' => 0, 'nb_nes' => 0,
                'nb_nes_vivants' => 0, 'nb_morts_nes' => 0, 'nb_controles_lait' => 0, 'nb_soins' => 0,
                'nb_maladies' => 0, 'cout_sante' => 0.0, 'nb_descendants' => 0,
            ]);
        }
        if (!$vars) {
            return [];
        }
        $pe = $p + ['e2' => $e];

        // Pesées (toute la vie jusqu'à la fin de période : nécessaire pour poids à âge type)
        $stmt = Database::query("SELECT w.animal_id, w.event_date, w.weight, w.body_score FROM weighings w JOIN animals a ON a.id = w.animal_id
                                 WHERE $pop AND w.event_date <= :e2 ORDER BY w.animal_id, w.event_date, w.id", $pe);
        $targets = ['poids_30j' => [30, 12], 'poids_90j' => [90, 20], 'poids_sevrage' => [$sevrage, 20]];
        $best = [];
        $nec = [];
        while ($w = $stmt->fetch()) {
            $id = $w['animal_id'];
            $v = &$vars[$id];
            $ageAt = (int) ((strtotime($w['event_date']) - strtotime($v['_birth'])) / 86400);
            foreach ($targets as $k => [$t, $tol]) {
                $d = abs($ageAt - $t);
                if ($d <= $tol && (!isset($best[$id][$k]) || $d < $best[$id][$k])) {
                    $best[$id][$k] = $d;
                    $v[$k] = (float) $w['weight'];
                }
            }
            if ($w['event_date'] >= $s) {
                if ($v['nb_pesees'] === 0) {
                    $v['poids_debut'] = (float) $w['weight'];
                    $v['_first_w'] = $w['event_date'];
                }
                $v['poids_fin'] = (float) $w['weight'];
                $v['jours_pesee'] = (int) ((strtotime($w['event_date']) - strtotime($v['_first_w'])) / 86400);
                $v['nb_pesees']++;
                if ($w['body_score'] !== null) {
                    $nec[$id][] = (float) $w['body_score'];
                }
            }
            unset($v);
        }
        foreach ($nec as $id => $list) {
            $vars[$id]['nec_moyenne'] = round(array_sum($list) / count($list), 2);
        }

        // Saillies
        foreach (Database::all("SELECT m.animal_id, COUNT(*) n FROM matings m JOIN animals a ON a.id = m.animal_id
                                WHERE $pop AND m.event_date BETWEEN :s2 AND :e2 GROUP BY m.animal_id", $pe + ['s2' => $s]) as $r) {
            $vars[$r['animal_id']]['nb_saillies'] = (int) $r['n'];
        }

        // Mises bas (toute la vie pour intervalle et âge à la 1re mise bas)
        $births = Database::all("SELECT b.animal_id, b.event_date, b.born_total, b.born_alive, b.abortion FROM births b JOIN animals a ON a.id = b.animal_id
                                 WHERE $pop AND b.event_date <= :e2 ORDER BY b.animal_id, b.event_date, b.id", $pe);
        $dates = [];
        foreach ($births as $b) {
            $id = $b['animal_id'];
            $v = &$vars[$id];
            if ((int) $b['abortion'] === 0) {
                $dates[$id][] = $b['event_date'];
            }
            if ($b['event_date'] >= $s) {
                if ((int) $b['abortion'] === 1) {
                    $v['nb_avortements']++;
                } else {
                    $v['nb_mises_bas']++;
                    $v['nb_nes'] += (int) $b['born_total'];
                    $v['nb_nes_vivants'] += (int) $b['born_alive'];
                    $v['nb_morts_nes'] += (int) $b['born_total'] - (int) $b['born_alive'];
                }
            }
            unset($v);
        }
        foreach ($dates as $id => $list) {
            $vars[$id]['age_premiere_mise_bas'] = (int) ((strtotime($list[0]) - strtotime($vars[$id]['_birth'])) / 86400);
            if (count($list) > 1) {
                $intervals = [];
                for ($i = 1, $n = count($list); $i < $n; $i++) {
                    $intervals[] = (strtotime($list[$i]) - strtotime($list[$i - 1])) / 86400;
                }
                $vars[$id]['intervalle_mises_bas'] = round(array_sum($intervals) / count($intervals), 1);
            }
        }

        // Lait (méthode des intervalles pour le total estimé)
        $stmt = Database::query("SELECT l.animal_id, l.event_date, l.quantity, l.fat_rate, l.protein_rate FROM milk_records l JOIN animals a ON a.id = l.animal_id
                                 WHERE $pop AND l.event_date BETWEEN :s2 AND :e2 ORDER BY l.animal_id, l.event_date, l.id", $pe + ['s2' => $s]);
        $milk = [];
        while ($l = $stmt->fetch()) {
            $milk[$l['animal_id']][] = $l;
        }
        foreach ($milk as $id => $list) {
            $q = array_map(static fn($x) => (float) $x['quantity'], $list);
            $tb = array_filter(array_map(static fn($x) => $x['fat_rate'], $list), static fn($x) => $x !== null);
            $tp = array_filter(array_map(static fn($x) => $x['protein_rate'], $list), static fn($x) => $x !== null);
            $total = 0.0;
            for ($i = 1, $n = count($list); $i < $n; $i++) {
                $days = (strtotime($list[$i]['event_date']) - strtotime($list[$i - 1]['event_date'])) / 86400;
                $total += ($q[$i] + $q[$i - 1]) / 2 * $days;
            }
            $vars[$id]['nb_controles_lait'] = count($q);
            $vars[$id]['lait_moyen'] = round(array_sum($q) / count($q), 3);
            $vars[$id]['lait_max'] = max($q);
            $vars[$id]['lait_total_estime'] = count($q) > 1 ? round($total, 2) : null;
            $vars[$id]['tb_moyen'] = $tb ? round(array_sum($tb) / count($tb), 2) : null;
            $vars[$id]['tp_moyen'] = $tp ? round(array_sum($tp) / count($tp), 2) : null;
        }

        // Santé
        foreach (Database::all("SELECT h.animal_id, COUNT(*) n, SUM(h.event_type = 'maladie') mal, COALESCE(SUM(h.cost), 0) cost
                                FROM health_events h JOIN animals a ON a.id = h.animal_id
                                WHERE $pop AND h.event_date BETWEEN :s2 AND :e2 GROUP BY h.animal_id", $pe + ['s2' => $s]) as $r) {
            $vars[$r['animal_id']]['nb_soins'] = (int) $r['n'];
            $vars[$r['animal_id']]['nb_maladies'] = (int) $r['mal'];
            $vars[$r['animal_id']]['cout_sante'] = (float) $r['cost'];
        }

        // Descendants
        foreach (['mother_id', 'father_id'] as $col) {
            foreach (Database::all("SELECT d.$col AS pid, COUNT(*) n FROM animals d JOIN animals a ON a.id = d.$col
                                    WHERE $pop AND d.birth_date BETWEEN :s2 AND :e2 GROUP BY d.$col", $pe + ['s2' => $s]) as $r) {
                $vars[$r['pid']]['nb_descendants'] += (int) $r['n'];
            }
        }

        return $vars;
    }

    public function computeAnimals(int $year, ?int $animalId = null, array $indexIds = []): array
    {
        $defs = self::definitions('animal', $indexIds);
        $all = $this->animalVariables($year, $animalId);
        $rows = [];
        foreach ($all as $id => $vars) {
            $label = $vars['_label'];
            $clean = array_intersect_key($vars, self::ANIMAL_VARS);
            foreach ($defs as $def) {
                $rows[] = $this->buildRow($def, $clean, 'animal', (string) $id, $label, (int) $id, $year);
            }
        }
        $this->save($rows);
        return ['saved' => count($rows), 'animals' => count($all), 'results' => $rows];
    }

    // =================================================================
    //  Évaluation & enregistrement
    // =================================================================
    private function buildRow(array $def, array $vars, string $gType, string $gValue, string $gLabel, ?int $animalId, int $year): array
    {
        try {
            $value = $this->engine->evaluate($def['formula'], $vars);
        } catch (\InvalidArgumentException) {
            $value = null;
        }
        if ($value !== null) {
            $value = round($value, 4);
        }
        [$level, $text] = self::interpret($def, $value);
        $used = [];
        try {
            foreach ($this->engine->variables($def['formula']) as $name) {
                $used[$name] = $vars[$name] ?? null;
            }
        } catch (\InvalidArgumentException) {
        }
        return [
            'index_id' => (int) $def['id'], 'group_type' => $gType, 'group_value' => $gValue,
            'group_label' => mb_substr($gLabel, 0, 120), 'animal_id' => $animalId, 'year' => $year,
            'value' => $value, 'level' => $level, 'interpretation' => mb_substr($text, 0, 500),
            'variables' => json_encode($used, JSON_UNESCAPED_UNICODE), 'formula_used' => $def['formula'],
            'computed_by' => Auth::id(),
            // pour affichage immédiat
            '_name' => $def['name'], '_unit' => $def['unit'], '_decimals' => (int) $def['decimals'],
        ];
    }

    /** Upsert par lots de 200 lignes dans une transaction. */
    private function save(array $rows): void
    {
        if (!$rows) {
            return;
        }
        $cols = ['index_id', 'group_type', 'group_value', 'group_label', 'animal_id', 'year', 'value', 'level', 'interpretation', 'variables', 'formula_used', 'computed_by'];
        Database::transaction(static function () use ($rows, $cols) {
            foreach (array_chunk($rows, 200) as $chunk) {
                $place = '(' . implode(',', array_fill(0, count($cols), '?')) . ', NOW())';
                $sql = 'INSERT INTO index_results (' . implode(',', $cols) . ', computed_at) VALUES '
                    . implode(',', array_fill(0, count($chunk), $place))
                    . ' ON DUPLICATE KEY UPDATE group_label = VALUES(group_label), animal_id = VALUES(animal_id), value = VALUES(value),
                        level = VALUES(level), interpretation = VALUES(interpretation), variables = VALUES(variables),
                        formula_used = VALUES(formula_used), computed_by = VALUES(computed_by), computed_at = NOW()';
                $params = [];
                foreach ($chunk as $r) {
                    foreach ($cols as $c) {
                        $params[] = $r[$c];
                    }
                }
                Database::query($sql, $params);
            }
        });
        Cache::bump('indices');
    }

    /** Teste une formule sur des variables réelles (aperçu dans les paramètres). */
    public function preview(array $def, int $year): array
    {
        if ($def['scope'] === 'animal') {
            $all = $this->animalVariables($year);
            $sample = array_slice($all, 0, 5, true);
            $out = [];
            foreach ($sample as $vars) {
                $clean = array_intersect_key($vars, self::ANIMAL_VARS);
                $val = $this->engine->evaluate($def['formula'], $clean);
                $out[] = ['label' => $vars['_label'], 'value' => $val, 'interp' => self::interpret($def, $val)];
            }
            return $out;
        }
        $vars = $this->herdVariables($year);
        $val = $this->engine->evaluate($def['formula'], $vars);
        return [['label' => 'Troupeau entier', 'value' => $val, 'interp' => self::interpret($def, $val), 'vars' => $vars]];
    }
}
