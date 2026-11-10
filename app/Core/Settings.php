<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Paramètres applicatifs (table settings) mis en cache APCu.
 */
final class Settings
{
    public static function all(): array
    {
        return Cache::remember(Cache::tagged(['settings'], 'all'), 3600, static function () {
            $rows = Database::all('SELECT skey, svalue, stype FROM settings');
            $out = [];
            foreach ($rows as $r) {
                $out[$r['skey']] = match ($r['stype']) {
                    'int'    => (int) $r['svalue'],
                    'number' => (float) $r['svalue'],
                    'bool'   => (bool) (int) $r['svalue'],
                    default  => $r['svalue'],
                };
            }
            return $out;
        });
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }

    public static function set(string $key, string $value): void
    {
        Database::query('UPDATE settings SET svalue = :v WHERE skey = :k', ['v' => $value, 'k' => $key]);
        Cache::bump('settings');
    }
}
