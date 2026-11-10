<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Cache applicatif APCu (mémoire partagée), avec repli sur un cache
 * de requête si APCu n'est pas disponible (CLI, hébergement mutualisé).
 *
 * Invalidation par "tags versionnés" : chaque tag possède un numéro de
 * version ; incrémenter la version rend obsolètes toutes les clés du tag
 * sans avoir à les parcourir.
 */
final class Cache
{
    private static array $local = [];
    private static ?bool $enabled = null;

    public static function enabled(): bool
    {
        if (self::$enabled === null) {
            self::$enabled = function_exists('apcu_enabled') && apcu_enabled();
        }
        return self::$enabled;
    }

    private static function k(string $key): string
    {
        return App::config('cache.prefix', 'app:') . $key;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $k = self::k($key);
        if (array_key_exists($k, self::$local)) {
            return self::$local[$k];
        }
        if (self::enabled()) {
            $ok = false;
            $v = apcu_fetch($k, $ok);
            if ($ok) {
                return self::$local[$k] = $v;
            }
        }
        return $default;
    }

    public static function set(string $key, mixed $value, ?int $ttl = null): void
    {
        $k = self::k($key);
        self::$local[$k] = $value;
        if (self::enabled()) {
            apcu_store($k, $value, $ttl ?? (int) App::config('cache.default_ttl', 300));
        }
    }

    public static function delete(string $key): void
    {
        $k = self::k($key);
        unset(self::$local[$k]);
        if (self::enabled()) {
            apcu_delete($k);
        }
    }

    public static function remember(string $key, int $ttl, callable $fn): mixed
    {
        $sentinel = "\0miss";
        $v = self::get($key, $sentinel);
        if ($v !== $sentinel) {
            return $v;
        }
        $v = $fn();
        self::set($key, $v, $ttl);
        return $v;
    }

    /** Version courante d'un tag. */
    public static function version(string $tag): int
    {
        $v = self::get('v:' . $tag);
        if ($v === null) {
            $v = 1;
            self::set('v:' . $tag, $v, 0);
        }
        return (int) $v;
    }

    /** Invalide toutes les clés d'un tag. */
    public static function bump(string ...$tags): void
    {
        foreach ($tags as $tag) {
            $k = self::k('v:' . $tag);
            if (self::enabled()) {
                if (apcu_inc($k) === false) {
                    apcu_store($k, 2, 0);
                }
                unset(self::$local[$k]);
            } else {
                self::$local[$k] = (self::$local[$k] ?? 1) + 1;
            }
        }
    }

    /** Clé dépendante d'un ou plusieurs tags. */
    public static function tagged(array $tags, string $key): string
    {
        $parts = [];
        foreach ($tags as $t) {
            $parts[] = $t . self::version($t);
        }
        return implode('.', $parts) . ':' . $key;
    }

    public static function clear(): void
    {
        self::$local = [];
        if (self::enabled()) {
            $prefix = App::config('cache.prefix', 'app:');
            if (class_exists('APCUIterator')) {
                apcu_delete(new \APCUIterator('/^' . preg_quote($prefix, '/') . '/'));
            } else {
                apcu_clear_cache();
            }
        }
    }

    public static function stats(): array
    {
        if (!self::enabled()) {
            return ['enabled' => false];
        }
        $info = apcu_cache_info(true);
        $sma = apcu_sma_info(true);
        return [
            'enabled'  => true,
            'entries'  => $info['num_entries'] ?? 0,
            'hits'     => $info['num_hits'] ?? 0,
            'misses'   => $info['num_misses'] ?? 0,
            'memory'   => ($sma['num_seg'] ?? 1) * ($sma['seg_size'] ?? 0),
            'free'     => $sma['avail_mem'] ?? 0,
        ];
    }
}
