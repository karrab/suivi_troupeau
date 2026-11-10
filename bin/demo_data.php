<?php
/**
 * Génération d'un troupeau de démonstration (chèvres, boucs, chevreaux,
 * brebis, béliers, agneaux) sur 3 ans : saillies, mises bas, pesées,
 * santé, lait, ventes, mortalité. Inclus par bin/install.php --demo.
 */
declare(strict_types=1);

use App\Core\Database;

mt_srand(20240101);
$today = date('Y-m-d');
$admin = (int) Database::value('SELECT id FROM users ORDER BY id LIMIT 1');
$cat = Database::pairs('SELECT code, id FROM categories');
$breeds = ['caprin' => Database::query("SELECT id FROM breeds WHERE species='caprin'")->fetchAll(PDO::FETCH_COLUMN),
           'ovin'   => Database::query("SELECT id FROM breeds WHERE species='ovin'")->fetchAll(PDO::FETCH_COLUMN)];
$lots = Database::pairs('SELECT name, id FROM lots');
$seq = ['caprin' => 0, 'ovin' => 0];
$names = ['Blanchette', 'Noisette', 'Cannelle', 'Perle', 'Biscotte', 'Caramel', 'Réglisse', 'Praline', 'Vanille', 'Mimosa',
          'Bella', 'Luna', 'Tilda', 'Fleur', 'Daisy', 'Iris', 'Lila', 'Nina', 'Olive', 'Pépite'];

$rnd = static fn(float $a, float $b): float => $a + mt_rand() / mt_getrandmax() * ($b - $a);
$addDays = static fn(string $d, int $n): string => date('Y-m-d', strtotime("$d +$n days"));
$hist = static function (int $aid, string $date, string $type, string $summary, ?string $t = null, ?int $ref = null) use ($admin): void {
    Database::insert('animal_history', ['animal_id' => $aid, 'event_date' => $date, 'event_type' => $type,
        'summary' => $summary, 'ref_table' => $t, 'ref_id' => $ref, 'user_id' => $admin]);
};
$newAnimal = static function (array $d) use (&$seq, $admin, $hist): int {
    $sp = $d['species'];
    $seq[$sp]++;
    $d['tag'] ??= ($sp === 'caprin' ? 'C' : 'O') . '-' . substr($d['birth_date'], 0, 4) . '-' . str_pad((string) $seq[$sp], 4, '0', STR_PAD_LEFT);
    $d['created_by'] = $admin;
    $id = Database::insert('animals', $d);
    $hist($id, $d['entry_date'], 'creation', $d['origin'] === 'naissance' ? 'Naissance sur l\'exploitation' : 'Entrée dans le troupeau (achat)');
    return $id;
};
$weigh = static function (int $aid, string $date, float $w, string $ctx = 'routine') use ($admin, $hist, $today): void {
    if ($date > $today) {
        return;
    }
    $id = Database::insert('weighings', ['animal_id' => $aid, 'event_date' => $date, 'weight' => round($w, 2),
        'body_score' => round(mt_rand(5, 9) / 2, 1), 'context' => $ctx, 'created_by' => $admin]);
    $hist($aid, $date, 'pesee', 'Pesée : ' . number_format($w, 2, ',', ' ') . ' kg', 'weighings', $id);
};
$health = static function (int $aid, string $date, string $type, string $diag, string $product, float $cost, int $withdrawal = 0, string $outcome = 'na') use ($admin, $hist, $today): void {
    if ($date > $today) {
        return;
    }
    $id = Database::insert('health_events', ['animal_id' => $aid, 'event_date' => $date, 'event_type' => $type,
        'diagnosis' => $diag, 'product' => $product, 'cost' => $cost, 'withdrawal_days' => $withdrawal ?: null,
        'veterinarian' => 'Dr. Vétérinaire', 'outcome' => $outcome, 'created_by' => $admin]);
    $hist($aid, $date, 'sante', ucfirst($type) . ' : ' . $diag . ' — ' . $product, 'health_events', $id);
};
$exitAnimal = static function (int $aid, string $status, string $date, ?string $reason = null, ?float $price = null) use ($hist): void {
    Database::update('animals', ['status' => $status, 'exit_date' => $date, 'exit_reason' => $reason, 'sale_price' => $price], $aid);
    $hist($aid, $date, 'sortie', 'Sortie : ' . $status . ($reason ? ' (' . $reason . ')' : ''));
};

echo "Génération du troupeau de démonstration...\n";
Database::transaction(static function () use (
    $cat, $breeds, $lots, $names, $rnd, $addDays, $hist, $newAnimal, $weigh, $health, $exitAnimal, $admin, $today
) {
    $start = '2024-01-01';
    // --- Fondateurs
    $config = [
        ['caprin', 'F', 'chevre', 70], ['caprin', 'M', 'bouc', 4],
        ['ovin', 'F', 'brebis', 60], ['ovin', 'M', 'belier', 4],
    ];
    foreach ($config as [$sp, $sex, $code, $n]) {
        for ($i = 0; $i < $n; $i++) {
            $birth = date('Y-m-d', mt_rand(strtotime('2018-06-01'), strtotime('2022-06-01')));
            $id = $newAnimal([
                'species' => $sp, 'sex' => $sex, 'category_id' => $cat[$code],
                'breed_id' => $breeds[$sp][array_rand($breeds[$sp])],
                'lot_id' => $sex === 'M' ? $lots['Mâles'] : ($i % 2 ? $lots['Bergerie A'] : $lots['Bergerie B']),
                'name' => $sex === 'F' && mt_rand(0, 2) === 0 ? $names[array_rand($names)] : null,
                'birth_date' => $birth, 'birth_date_estimated' => 1, 'origin' => 'achat', 'entry_date' => $start,
                'purchase_price' => $sex === 'M' ? mt_rand(2500, 4500) : mt_rand(1200, 2500),
                'color' => ['Blanc', 'Noir', 'Roux', 'Pie', 'Brun'][mt_rand(0, 4)],
            ]);
            $weigh($id, $start, $sp === 'caprin' ? $rnd(45, 65) : $rnd(50, 75), 'routine');
        }
    }

    for ($year = 2024; $year <= (int) date('Y'); $year++) {
        // Animaux adultes présents
        foreach (['caprin', 'ovin'] as $sp) {
            $males = Database::query("SELECT id FROM animals WHERE species = :s AND sex = 'M' AND status = 'actif'
                AND birth_date <= :d AND category_id IN (:c1, :c2)", ['s' => $sp, 'd' => "$year-01-01",
                'c1' => $cat[$sp === 'caprin' ? 'bouc' : 'belier'], 'c2' => $cat[$sp === 'caprin' ? 'chevreau' : 'agneau']])->fetchAll(PDO::FETCH_COLUMN);
            $females = Database::all("SELECT id, birth_date FROM animals WHERE species = :s AND sex = 'F' AND status = 'actif'
                AND birth_date <= :d", ['s' => $sp, 'd' => date('Y-m-d', strtotime("$year-01-15 -8 months"))]);
            $gest = $sp === 'caprin' ? 150 : 147;

            foreach ($females as $f) {
                $fid = (int) $f['id'];
                // Santé de routine
                $health($fid, "$year-03-" . str_pad((string) mt_rand(1, 28), 2, '0', STR_PAD_LEFT), 'vaccination', 'Entérotoxémie', 'Covexin 10', 12.0);
                $health($fid, "$year-09-" . str_pad((string) mt_rand(1, 28), 2, '0', STR_PAD_LEFT), 'vermifuge', 'Strongles digestifs', 'Ivermectine', 8.5, 14);
                if (mt_rand(1, 100) <= 10) {
                    $d = date('Y-m-d', mt_rand(strtotime("$year-01-01"), strtotime("$year-12-28")));
                    $health($fid, $d, 'maladie', ['Mammite', 'Boiterie', 'Pneumonie', 'Diarrhée'][mt_rand(0, 3)], 'Antibiotique', $rnd(40, 180), 7, 'gueri');
                }
                // Mortalité / réforme adultes
                $r = mt_rand(1, 1000);
                if ($r <= 30) {
                    $d = date('Y-m-d', mt_rand(strtotime("$year-01-20"), strtotime("$year-12-20")));
                    if ($d <= $today) {
                        $exitAnimal($fid, 'mort', $d, 'Maladie');
                        continue;
                    }
                }
                // Saillie
                $md = date('Y-m-d', mt_rand(strtotime("$year-01-10"), strtotime("$year-03-10")));
                $male = $males ? (int) $males[array_rand($males)] : null;
                $fertile = mt_rand(1, 100) <= 88;
                $mid = Database::insert('matings', ['animal_id' => $fid, 'male_id' => $male, 'event_date' => $md,
                    'method' => 'monte_naturelle', 'diagnosis' => $fertile ? 'gestante' : 'vide',
                    'diagnosis_date' => $addDays($md, 45), 'expected_birth' => $addDays($md, $gest), 'created_by' => $admin]);
                $hist($fid, $md, 'saillie', 'Saillie (monte naturelle)', 'matings', $mid);
                if (!$fertile) {
                    if ($r > 950) {
                        $exitAnimal($fid, 'reforme', "$year-10-15", 'Infertilité', $rnd(900, 1400));
                    }
                    continue;
                }
                $bd = $addDays($md, $gest + mt_rand(-3, 3));
                if ($bd > $today) {
                    continue;
                }
                $abortion = mt_rand(1, 100) <= 3;
                $p = mt_rand(1, 100);
                $total = $sp === 'caprin' ? ($p <= 40 ? 1 : ($p <= 88 ? 2 : 3)) : ($p <= 62 ? 1 : ($p <= 96 ? 2 : 3));
                $kids = [];
                $alive = 0;
                for ($k = 0; $k < $total; $k++) {
                    $isAlive = mt_rand(1, 100) <= 95;
                    $sex = mt_rand(0, 1) ? 'M' : 'F';
                    $kids[] = [$sex, $isAlive];
                    $alive += $isAlive ? 1 : 0;
                }
                $males_n = count(array_filter($kids, static fn($k) => $k[0] === 'M'));
                $bid = Database::insert('births', ['animal_id' => $fid, 'father_id' => $male, 'event_date' => $bd,
                    'born_total' => $abortion ? 0 : $total, 'born_alive' => $abortion ? 0 : $alive,
                    'born_males' => $abortion ? 0 : $males_n, 'born_females' => $abortion ? 0 : $total - $males_n,
                    'ease' => mt_rand(1, 10) === 1 ? 'aidee' : 'facile', 'abortion' => $abortion ? 1 : 0, 'created_by' => $admin]);
                $hist($fid, $bd, 'mise_bas', $abortion ? 'Avortement' : "Mise bas : $total né(s) dont $alive vivant(s)", 'births', $bid);
                if ($abortion) {
                    continue;
                }
                // Lait (chèvres)
                if ($sp === 'caprin') {
                    $peak = $rnd(1.8, 3.6);
                    for ($m = 1; $m <= 9; $m++) {
                        $d = $addDays($bd, 30 * $m);
                        if ($d > $today) {
                            break;
                        }
                        $q = max(0.3, $peak * (1 - abs($m - 2.5) * 0.11) + $rnd(-0.3, 0.3));
                        $lid = Database::insert('milk_records', ['animal_id' => $fid, 'event_date' => $d, 'quantity' => round($q, 2),
                            'fat_rate' => round($rnd(32, 40), 1), 'protein_rate' => round($rnd(29, 34), 1), 'created_by' => $admin]);
                        $hist($fid, $d, 'lait', 'Contrôle laitier : ' . number_format($q, 2, ',', ' ') . ' L/j', 'milk_records', $lid);
                    }
                }
                // Jeunes
                foreach ($kids as [$sex, $isAlive]) {
                    if (!$isAlive) {
                        continue;
                    }
                    $code = $sp === 'caprin' ? ($sex === 'M' ? 'chevreau' : 'chevrette') : ($sex === 'M' ? 'agneau' : 'agnelle');
                    $bw = $rnd(2.2, 4.2) - ($total - 1) * 0.3;
                    $kid = $newAnimal([
                        'species' => $sp, 'sex' => $sex, 'category_id' => $cat[$code],
                        'breed_id' => $breeds[$sp][array_rand($breeds[$sp])], 'lot_id' => $lots['Nurserie'],
                        'birth_date' => $bd, 'birth_weight' => round($bw, 2),
                        'birth_type' => ['simple', 'double', 'triple'][$total - 1], 'mother_id' => $fid, 'father_id' => $male,
                        'origin' => 'naissance', 'entry_date' => $bd,
                    ]);
                    $weigh($kid, $bd, $bw, 'naissance');
                    $gmq = $rnd(0.09, 0.19);
                    if (mt_rand(1, 100) <= 7) {
                        $dd = $addDays($bd, mt_rand(2, 80));
                        if ($dd <= $today) {
                            $exitAnimal($kid, 'mort', $dd, 'Mortalité néonatale');
                            continue;
                        }
                    }
                    $weigh($kid, $addDays($bd, 30 + mt_rand(-3, 3)), $bw + $gmq * 30);
                    $weigh($kid, $addDays($bd, 90 + mt_rand(-5, 5)), $bw + $gmq * 90, 'sevrage');
                    $weigh($kid, $addDays($bd, 180), $bw + $gmq * 0.8 * 180);
                    if ($sex === 'M' && mt_rand(1, 100) <= 85) {
                        $sd = $addDays($bd, mt_rand(120, 200));
                        if ($sd <= $today) {
                            $exitAnimal($kid, 'vendu', $sd, 'Vente engraissement', $rnd(700, 1500));
                        }
                    } else {
                        Database::update('animals', ['lot_id' => $lots[$sex === 'M' ? 'Mâles' : 'Bergerie B']], $kid);
                    }
                }
            }
        }
    }

    // Reclassement des jeunes devenus adultes (> 12 mois)
    $map = ['chevreau' => 'bouc', 'chevrette' => 'chevre', 'agneau' => 'belier', 'agnelle' => 'brebis'];
    foreach ($map as $young => $adult) {
        Database::query('UPDATE animals SET category_id = :a WHERE category_id = :y AND status = :st AND birth_date <= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)',
            ['a' => $cat[$adult], 'y' => $cat[$young], 'st' => 'actif']);
    }
});
echo 'Animaux : ' . Database::value('SELECT COUNT(*) FROM animals') . "\n";
