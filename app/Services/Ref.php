<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Cache;
use App\Core\Database;

/**
 * Référentiels (catégories, races, lots) mis en cache APCu (tag "refs").
 */
final class Ref
{
    public static function categories(): array
    {
        return Cache::remember(Cache::tagged(['refs'], 'categories'), 3600, static fn() =>
            array_column(Database::all('SELECT * FROM categories ORDER BY sort_order, id'), null, 'id'));
    }

    public static function breeds(): array
    {
        return Cache::remember(Cache::tagged(['refs'], 'breeds'), 3600, static fn() =>
            array_column(Database::all('SELECT * FROM breeds ORDER BY species, name'), null, 'id'));
    }

    public static function lots(): array
    {
        return Cache::remember(Cache::tagged(['refs'], 'lots'), 3600, static fn() =>
            array_column(Database::all('SELECT * FROM lots ORDER BY name'), null, 'id'));
    }

    public static function categoryLabel(?int $id): string
    {
        return $id ? (self::categories()[$id]['label'] ?? '') : '';
    }

    public static function breedName(?int $id): string
    {
        return $id ? (self::breeds()[$id]['name'] ?? '') : '';
    }

    public static function lotName(?int $id): string
    {
        return $id ? (self::lots()[$id]['name'] ?? '') : '';
    }
}
