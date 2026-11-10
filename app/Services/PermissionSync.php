<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Cache;
use App\Core\Database;

/**
 * Synchronise le catalogue app/Config/permissions.php avec la table permissions.
 */
final class PermissionSync
{
    public static function catalog(): array
    {
        return require APP_ROOT . '/app/Config/permissions.php';
    }

    public static function sync(): int
    {
        $n = 0;
        foreach (self::catalog() as $module => $def) {
            foreach ($def['actions'] as $action => $label) {
                $n += Database::query(
                    'INSERT INTO permissions (code, module, label) VALUES (:c, :m, :l)
                     ON DUPLICATE KEY UPDATE module = VALUES(module), label = VALUES(label)',
                    ['c' => "$module.$action", 'm' => $module, 'l' => $label]
                )->rowCount() === 1 ? 1 : 0;
            }
        }
        Cache::bump('perms');
        return $n;
    }

    /** Attribue à un rôle toutes les permissions correspondant aux motifs (ex. "*.view", "health.*"). */
    public static function grant(int $roleId, array $patterns): void
    {
        $codes = Database::query('SELECT id, code FROM permissions')->fetchAll(\PDO::FETCH_KEY_PAIR);
        foreach ($codes as $id => $code) {
            foreach ($patterns as $p) {
                if (fnmatch($p, $code)) {
                    Database::query('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (:r, :p)', ['r' => $roleId, 'p' => (int) $id]);
                    break;
                }
            }
        }
        Cache::bump('perms');
    }
}
