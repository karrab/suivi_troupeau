<?php
/**
 * Troupeau de démonstration (chèvres, boucs, chevreaux, brebis, béliers,
 * agneaux) sur 3 ans : saillies, mises bas, pesées, soins, lait, ventes,
 * mortalité. Inclus par outils/installer.php --demo.
 */
declare(strict_types=1);

mt_srand(20240101);
$auj = date('Y-m-d');
$admin = (int) bd_valeur('SELECT id FROM utilisateurs ORDER BY id LIMIT 1');
$cat = bd_paires('SELECT code, id FROM categories');
$races = ['caprin' => bd_colonne("SELECT id FROM races WHERE espece = 'caprin'"), 'ovin' => bd_colonne("SELECT id FROM races WHERE espece = 'ovin'")];
$lots = bd_paires('SELECT nom, id FROM lots');
$num = ['caprin' => 0, 'ovin' => 0];
$prenoms = ['Blanchette', 'Noisette', 'Cannelle', 'Perle', 'Biscotte', 'Caramel', 'Réglisse', 'Praline', 'Vanille', 'Mimosa',
            'Bella', 'Luna', 'Tilda', 'Fleur', 'Daisy', 'Iris', 'Lila', 'Nina', 'Olive', 'Pépite'];

$alea = static fn(float $a, float $b): float => $a + mt_rand() / mt_getrandmax() * ($b - $a);
$plus = static fn(string $d, int $n): string => date('Y-m-d', strtotime("$d +$n days"));
$jour = static fn(int $an, int $mois): string => sprintf('%04d-%02d-%02d', $an, $mois, mt_rand(1, 28));
$hist = static function (int $a, string $d, string $t, string $r, ?string $tab = null, ?int $ref = null) use ($admin): void {
    bd_inserer('historique_animaux', ['animal_id' => $a, 'date_evenement' => $d, 'type_evenement' => $t, 'resume' => $r,
        'table_ref' => $tab, 'ref_id' => $ref, 'utilisateur_id' => $admin]);
};
$nouvelAnimal = static function (array $d) use (&$num, $admin, $hist): int {
    $num[$d['espece']]++;
    $d['boucle'] ??= ($d['espece'] === 'caprin' ? 'C' : 'O') . '-' . substr($d['date_naissance'], 0, 4) . '-' . str_pad((string) $num[$d['espece']], 4, '0', STR_PAD_LEFT);
    $d['cree_par'] = $admin;
    $id = bd_inserer('animaux', $d);
    $hist($id, $d['date_entree'], 'creation', $d['origine'] === 'naissance' ? 'Naissance sur l\'exploitation' : 'Entrée dans le troupeau (achat)');
    return $id;
};
$peser = static function (int $a, string $d, float $p, string $ctx = 'routine') use ($admin, $hist, $auj): void {
    if ($d > $auj) {
        return;
    }
    $id = bd_inserer('pesees', ['animal_id' => $a, 'date_evenement' => $d, 'poids' => round($p, 2),
        'note_etat' => round(mt_rand(5, 9) / 2, 1), 'contexte' => $ctx, 'cree_par' => $admin]);
    $hist($a, $d, 'pesee', 'Pesée : ' . number_format($p, 2, ',', ' ') . ' kg', 'pesees', $id);
};
$soigner = static function (int $a, string $d, string $type, string $diag, string $prod, float $cout, int $delai = 0, string $evol = 'na') use ($admin, $hist, $auj): void {
    if ($d > $auj) {
        return;
    }
    $id = bd_inserer('soins', ['animal_id' => $a, 'date_evenement' => $d, 'type_soin' => $type, 'diagnostic' => $diag,
        'produit' => $prod, 'cout' => $cout, 'delai_attente' => $delai ?: null, 'veterinaire' => 'Dr. Vétérinaire',
        'evolution' => $evol, 'cree_par' => $admin]);
    $hist($a, $d, 'soin', ucfirst($type) . ' : ' . $diag . ' — ' . $prod, 'soins', $id);
};
$sortir = static function (int $a, string $statut, string $d, ?string $motif = null, ?float $prix = null) use ($hist): void {
    bd_modifier('animaux', ['statut' => $statut, 'date_sortie' => $d, 'motif_sortie' => $motif, 'prix_vente' => $prix], $a);
    $hist($a, $d, 'sortie', 'Sortie : ' . libelle('statut', $statut) . ($motif ? ' (' . $motif . ')' : ''));
};

echo "Génération du troupeau de démonstration...\n";
bd_transaction(static function () use ($cat, $races, $lots, $prenoms, $alea, $plus, $jour, $hist, $nouvelAnimal, $peser, $soigner, $sortir, $admin, $auj) {
    // Fondateurs achetés le 1er janvier 2024
    foreach ([['caprin', 'F', 'chevre', 70], ['caprin', 'M', 'bouc', 4], ['ovin', 'F', 'brebis', 60], ['ovin', 'M', 'belier', 4]] as [$esp, $sexe, $code, $n]) {
        for ($i = 0; $i < $n; $i++) {
            $id = $nouvelAnimal([
                'espece' => $esp, 'sexe' => $sexe, 'categorie_id' => $cat[$code], 'race_id' => $races[$esp][array_rand($races[$esp])],
                'lot_id' => $sexe === 'M' ? $lots['Mâles'] : ($i % 2 ? $lots['Bergerie A'] : $lots['Bergerie B']),
                'nom' => $sexe === 'F' && mt_rand(0, 2) === 0 ? $prenoms[array_rand($prenoms)] : null,
                'date_naissance' => date('Y-m-d', mt_rand(strtotime('2018-06-01'), strtotime('2022-06-01'))), 'naissance_estimee' => 1,
                'origine' => 'achat', 'date_entree' => '2024-01-01', 'prix_achat' => $sexe === 'M' ? mt_rand(2500, 4500) : mt_rand(1200, 2500),
                'robe' => ['Blanc', 'Noir', 'Roux', 'Pie', 'Brun'][mt_rand(0, 4)],
            ]);
            $peser($id, '2024-01-01', $esp === 'caprin' ? $alea(45, 65) : $alea(50, 75));
        }
    }

    for ($an = 2024; $an <= (int) date('Y'); $an++) {
        foreach (['caprin', 'ovin'] as $esp) {
            $males = bd_colonne("SELECT id FROM animaux WHERE espece = :e AND sexe = 'M' AND statut = 'actif' AND date_naissance <= :d AND categorie_id = :c",
                ['e' => $esp, 'd' => "$an-01-01", 'c' => $cat[$esp === 'caprin' ? 'bouc' : 'belier']]);
            $femelles = bd_colonne("SELECT id FROM animaux WHERE espece = :e AND sexe = 'F' AND statut = 'actif' AND date_naissance <= :d",
                ['e' => $esp, 'd' => date('Y-m-d', strtotime("$an-01-15 -8 months"))]);
            $gestation = $esp === 'caprin' ? 150 : 147;

            foreach ($femelles as $fid) {
                $fid = (int) $fid;
                $soigner($fid, $jour($an, 3), 'vaccination', 'Entérotoxémie', 'Covexin 10', 12.0);
                $soigner($fid, $jour($an, 9), 'vermifuge', 'Strongles digestifs', 'Ivermectine', 8.5, 14);
                if (mt_rand(1, 100) <= 10) {
                    $soigner($fid, $jour($an, mt_rand(1, 12)), 'maladie', ['Mammite', 'Boiterie', 'Pneumonie', 'Diarrhée'][mt_rand(0, 3)], 'Antibiotique', $alea(40, 180), 7, 'gueri');
                }
                $r = mt_rand(1, 1000);
                if ($r <= 30 && ($dm = $jour($an, mt_rand(2, 12))) <= $auj) {
                    $sortir($fid, 'mort', $dm, 'Maladie');
                    continue;
                }
                $ds = date('Y-m-d', mt_rand(strtotime("$an-01-10"), strtotime("$an-03-10")));
                $male = $males ? (int) $males[array_rand($males)] : null;
                $fertile = mt_rand(1, 100) <= 88;
                $sid = bd_inserer('saillies', ['animal_id' => $fid, 'male_id' => $male, 'date_evenement' => $ds, 'methode' => 'monte_naturelle',
                    'diagnostic' => $fertile ? 'gestante' : 'vide', 'date_diagnostic' => $plus($ds, 45), 'mise_bas_prevue' => $plus($ds, $gestation), 'cree_par' => $admin]);
                $hist($fid, $ds, 'saillie', 'Saillie (monte naturelle)', 'saillies', $sid);
                if (!$fertile) {
                    if ($r > 950 && "$an-10-15" <= $auj) {
                        $sortir($fid, 'reforme', "$an-10-15", 'Infertilité', $alea(900, 1400));
                    }
                    continue;
                }
                $dmb = $plus($ds, $gestation + mt_rand(-3, 3));
                if ($dmb > $auj) {
                    continue;
                }
                $avort = mt_rand(1, 100) <= 3;
                $x = mt_rand(1, 100);
                $total = $esp === 'caprin' ? ($x <= 40 ? 1 : ($x <= 88 ? 2 : 3)) : ($x <= 62 ? 1 : ($x <= 96 ? 2 : 3));
                $petits = [];
                for ($k = 0; $k < $total; $k++) {
                    $petits[] = [mt_rand(0, 1) ? 'M' : 'F', mt_rand(1, 100) <= 95];
                }
                $vivants = count(array_filter($petits, static fn($p) => $p[1]));
                $nbM = count(array_filter($petits, static fn($p) => $p[0] === 'M'));
                $mbid = bd_inserer('mises_bas', ['animal_id' => $fid, 'pere_id' => $male, 'date_evenement' => $dmb,
                    'nes_total' => $avort ? 0 : $total, 'nes_vivants' => $avort ? 0 : $vivants, 'nes_males' => $avort ? 0 : $nbM,
                    'nes_femelles' => $avort ? 0 : $total - $nbM, 'deroulement' => mt_rand(1, 10) === 1 ? 'aidee' : 'facile',
                    'avortement' => $avort ? 1 : 0, 'cree_par' => $admin]);
                $hist($fid, $dmb, 'mise_bas', $avort ? 'Avortement' : "Mise bas : $total né(s) dont $vivants vivant(s)", 'mises_bas', $mbid);
                if ($avort) {
                    continue;
                }
                if ($esp === 'caprin') {
                    $pic = $alea(1.8, 3.6);
                    for ($m = 1; $m <= 9; $m++) {
                        $dl = $plus($dmb, 30 * $m);
                        if ($dl > $auj) {
                            break;
                        }
                        $q = max(0.3, $pic * (1 - abs($m - 2.5) * 0.11) + $alea(-0.3, 0.3));
                        $lid = bd_inserer('controles_laitiers', ['animal_id' => $fid, 'date_evenement' => $dl, 'quantite' => round($q, 2),
                            'taux_butyreux' => round($alea(32, 40), 1), 'taux_proteique' => round($alea(29, 34), 1), 'cree_par' => $admin]);
                        $hist($fid, $dl, 'lait', 'Contrôle laitier : ' . number_format($q, 2, ',', ' ') . ' L/j', 'controles_laitiers', $lid);
                    }
                }
                foreach ($petits as [$sexe, $vivant]) {
                    if (!$vivant) {
                        continue;
                    }
                    $code = $esp === 'caprin' ? ($sexe === 'M' ? 'chevreau' : 'chevrette') : ($sexe === 'M' ? 'agneau' : 'agnelle');
                    $pn = $alea(2.2, 4.2) - ($total - 1) * 0.3;
                    $jeune = $nouvelAnimal([
                        'espece' => $esp, 'sexe' => $sexe, 'categorie_id' => $cat[$code], 'race_id' => $races[$esp][array_rand($races[$esp])],
                        'lot_id' => $lots['Nurserie'], 'date_naissance' => $dmb, 'poids_naissance' => round($pn, 2),
                        'type_naissance' => ['simple', 'double', 'triple'][$total - 1], 'mere_id' => $fid, 'pere_id' => $male,
                        'origine' => 'naissance', 'date_entree' => $dmb,
                    ]);
                    $peser($jeune, $dmb, $pn, 'naissance');
                    $gmq = $alea(0.09, 0.19);
                    if (mt_rand(1, 100) <= 7 && ($dd = $plus($dmb, mt_rand(2, 80))) <= $auj) {
                        $sortir($jeune, 'mort', $dd, 'Mortalité néonatale');
                        continue;
                    }
                    $peser($jeune, $plus($dmb, 30 + mt_rand(-3, 3)), $pn + $gmq * 30);
                    $peser($jeune, $plus($dmb, 90 + mt_rand(-5, 5)), $pn + $gmq * 90, 'sevrage');
                    $peser($jeune, $plus($dmb, 180), $pn + $gmq * 0.8 * 180);
                    if ($sexe === 'M' && mt_rand(1, 100) <= 85) {
                        if (($dv = $plus($dmb, mt_rand(120, 200))) <= $auj) {
                            $sortir($jeune, 'vendu', $dv, 'Vente engraissement', $alea(700, 1500));
                        }
                    } else {
                        bd_modifier('animaux', ['lot_id' => $lots[$sexe === 'M' ? 'Mâles' : 'Bergerie B']], $jeune);
                    }
                }
            }
        }
    }
    // Les jeunes de plus de 12 mois passent en catégorie adulte
    foreach (['chevreau' => 'bouc', 'chevrette' => 'chevre', 'agneau' => 'belier', 'agnelle' => 'brebis'] as $jeune => $adulte) {
        bd_requete("UPDATE animaux SET categorie_id = :a WHERE categorie_id = :j AND statut = 'actif' AND date_naissance <= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)",
            ['a' => $cat[$adulte], 'j' => $cat[$jeune]]);
    }
});
echo 'Animaux : ' . bd_valeur('SELECT COUNT(*) FROM animaux') . "\n";
