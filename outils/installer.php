<?php
/**
 * Installation en ligne de commande :
 *
 *   php outils/installer.php --neuf --mdp-admin=MotDePasse [--admin=admin] [--demo]
 *
 *   --neuf       (re)crée toutes les tables (SUPPRIME les données existantes)
 *   --demo       génère un troupeau de démonstration (3 ans d'historique)
 *   --admin      identifiant de l'administrateur (défaut : admin)
 *   --mdp-admin  mot de passe de l'administrateur (8 caractères minimum)
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("À lancer en ligne de commande.\n");
}
require dirname(__DIR__) . '/inc/init.php';

$opts = getopt('', ['neuf', 'demo', 'admin:', 'mdp-admin:']);
$c = config('bd');

try {
    bd();
} catch (PDOException $e) {
    echo "Base inaccessible ({$e->getMessage()}) : tentative de création...\n";
    (new PDO("mysql:host={$c['hote']};port={$c['port']};charset=utf8mb4", $c['utilisateur'], $c['mot_de_passe']))
        ->exec("CREATE DATABASE IF NOT EXISTS `{$c['nom']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
}

$executerFichier = static function (string $fichier): void {
    foreach (preg_split('/;\s*\R/', (string) file_get_contents($fichier)) as $instr) {
        $instr = trim((string) preg_replace('/^\s*--.*$/m', '', $instr));
        if ($instr !== '') {
            bd()->exec($instr);
        }
    }
};

$existe = (bool) bd_valeur("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'animaux'");
if (isset($opts['neuf']) || !$existe) {
    echo "Création des tables...\n";
    $executerFichier(RACINE . '/sql/schema.sql');
    echo "Données de référence...\n";
    $executerFichier(RACINE . '/sql/donnees.sql');
}

echo "Permissions...\n";
synchroniser_permissions();

$roles = [
    ['Administrateur', 'Accès complet', 1, ['*']],
    ['Gestionnaire', 'Gestion du troupeau et des indices', 0, [
        'animaux.*', 'pesees.*', 'soins.*', 'saillies.*', 'mises_bas.*', 'lait.*', 'mouvements.*',
        'indices.voir', 'indices.calculer', 'indices.exporter', 'referentiels.*', 'traces.voir',
    ]],
    ['Vétérinaire', 'Suivi sanitaire et reproduction', 0, [
        'animaux.voir', 'animaux.exporter', 'pesees.voir', 'pesees.ajouter', 'soins.*', 'saillies.voir', 'saillies.ajouter',
        'saillies.modifier', 'mises_bas.voir', 'mises_bas.ajouter', 'mises_bas.modifier', 'lait.voir', 'mouvements.voir',
        'indices.voir', 'indices.exporter', 'referentiels.voir',
    ]],
    ['Consultant', 'Lecture seule et impressions', 0, [
        'animaux.voir', 'animaux.exporter', 'pesees.voir', 'pesees.exporter', 'soins.voir', 'soins.exporter',
        'saillies.voir', 'saillies.exporter', 'mises_bas.voir', 'mises_bas.exporter', 'lait.voir', 'lait.exporter',
        'mouvements.voir', 'mouvements.exporter', 'indices.voir', 'indices.exporter', 'referentiels.voir',
    ]],
];
foreach ($roles as [$nom, $desc, $complet, $motifs]) {
    if (!bd_valeur('SELECT id FROM roles WHERE nom = :n', ['n' => $nom])) {
        $id = bd_inserer('roles', ['nom' => $nom, 'description' => $desc, 'acces_complet' => $complet]);
        accorder_permissions($id, $motifs);
        echo "Rôle créé : $nom\n";
    }
}
$admin = $opts['admin'] ?? 'admin';
if (!bd_valeur('SELECT id FROM utilisateurs WHERE identifiant = :i', ['i' => $admin])) {
    $mdp = $opts['mdp-admin'] ?? '';
    if (strlen($mdp) < 8) {
        exit("Indiquez --mdp-admin=... (8 caractères minimum) pour créer l'administrateur.\n");
    }
    bd_inserer('utilisateurs', [
        'role_id' => (int) bd_valeur("SELECT id FROM roles WHERE nom = 'Administrateur'"),
        'identifiant' => $admin, 'nom_complet' => 'Administrateur',
        'mot_de_passe' => password_hash($mdp, PASSWORD_DEFAULT),
    ]);
    echo "Administrateur créé : $admin\n";
}

if (isset($opts['demo'])) {
    require __DIR__ . '/demo.php';
}
echo "Installation terminée.\n";
