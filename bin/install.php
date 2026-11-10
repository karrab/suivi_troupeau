<?php
/**
 * Installation en ligne de commande.
 *
 *   php bin/install.php --fresh --admin-pass=MotDePasse [--admin-user=admin] [--demo]
 *
 *   --fresh       (re)crée toutes les tables (SUPPRIME les données existantes)
 *   --demo        génère un troupeau de démonstration (3 ans d'historique)
 *   --admin-user  identifiant de l'administrateur (défaut : admin)
 *   --admin-pass  mot de passe de l'administrateur (obligatoire à la 1re installation)
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("CLI uniquement.\n");
}
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\App;
use App\Core\Database;
use App\Services\PermissionSync;

$opts = getopt('', ['fresh', 'demo', 'admin-user:', 'admin-pass:']);
$cfg = App::config('db');

// Création de la base si nécessaire
try {
    Database::pdo();
} catch (PDOException $e) {
    echo "Base inaccessible ({$e->getMessage()}), tentative de création...\n";
    $pdo = new PDO("mysql:host={$cfg['host']};port={$cfg['port']};charset=utf8mb4", $cfg['user'], $cfg['pass']);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$cfg['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
}

$runSqlFile = static function (string $file): void {
    $sql = file_get_contents($file);
    foreach (preg_split('/;\s*\R/', $sql) as $stmt) {
        $clean = trim(preg_replace('/^\s*--.*$/m', '', $stmt));
        if ($clean !== '') {
            Database::pdo()->exec($clean);
        }
    }
};

$hasTables = (bool) Database::value("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'animals'");
if (isset($opts['fresh']) || !$hasTables) {
    echo "Création du schéma...\n";
    $runSqlFile(APP_ROOT . '/database/schema.sql');
    echo "Données de référence...\n";
    $runSqlFile(APP_ROOT . '/database/seed.sql');
}

echo "Synchronisation des permissions...\n";
PermissionSync::sync();

// Rôles par défaut
$roles = [
    ['Administrateur', 'Accès complet', 1, ['*']],
    ['Gestionnaire', 'Gestion du troupeau et des indices', 0, [
        'animals.*', 'weighings.*', 'health.*', 'matings.*', 'births.*', 'milk.*', 'movements.*',
        'indices.view', 'indices.compute', 'indices.export', 'references.*', 'audit.view',
    ]],
    ['Vétérinaire', 'Suivi sanitaire et reproduction', 0, [
        '*.view', 'health.*', 'matings.create', 'matings.edit', 'births.create', 'births.edit',
        'animals.export', 'weighings.create', 'indices.export',
    ]],
    ['Consultant', 'Lecture seule et impressions', 0, ['*.view', '*.export']],
];
foreach ($roles as [$name, $desc, $super, $perms]) {
    $id = Database::value('SELECT id FROM roles WHERE name = :n', ['n' => $name]);
    if (!$id) {
        $id = Database::insert('roles', ['name' => $name, 'description' => $desc, 'is_super' => $super]);
        PermissionSync::grant((int) $id, $perms);
        echo "Rôle créé : $name\n";
    }
}

// Administrateur
$adminUser = $opts['admin-user'] ?? 'admin';
$existing = Database::value('SELECT id FROM users WHERE username = :u', ['u' => $adminUser]);
if (!$existing) {
    $pass = $opts['admin-pass'] ?? null;
    if (!$pass || strlen($pass) < 8) {
        exit("Indiquez --admin-pass=... (8 caractères minimum) pour créer l'administrateur.\n");
    }
    Database::insert('users', [
        'role_id' => (int) Database::value("SELECT id FROM roles WHERE name = 'Administrateur'"),
        'username' => $adminUser,
        'full_name' => 'Administrateur',
        'password_hash' => password_hash($pass, PASSWORD_DEFAULT),
    ]);
    echo "Administrateur créé : $adminUser\n";
}

if (isset($opts['demo'])) {
    require __DIR__ . '/demo_data.php';
}
echo "Installation terminée.\n";
