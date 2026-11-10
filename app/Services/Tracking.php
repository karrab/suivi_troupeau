<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Outils partagés pour les modules de suivi déclarés dans app/Config/tracking.php.
 */
final class Tracking
{
    private static ?array $config = null;

    public static function all(): array
    {
        return self::$config ??= require APP_ROOT . '/app/Config/tracking.php';
    }

    public static function get(string $type): array
    {
        $all = self::all();
        if (!isset($all[$type])) {
            abort(404, 'Module de suivi inconnu.');
        }
        return $all[$type] + ['type' => $type, 'filters' => [], 'search' => [], 'animal_sex' => null];
    }

    /** Alias de jointure pour un champ animal/lot (male_id => male_tag, to_lot_id => to_lot_name). */
    public static function alias(string $field, string $type): string
    {
        $base = preg_replace('/_id$/', '', $field);
        return $type === 'lot' ? $base . '_name' : $base . '_tag';
    }

    /** [select, from] avec les jointures nécessaires. */
    public static function sql(array $cfg): array
    {
        $select = ['t.*', 'a.tag AS animal_tag', 'a.name AS animal_name', 'a.species AS animal_species'];
        $from = "FROM `{$cfg['table']}` t JOIN animals a ON a.id = t.animal_id";
        $i = 0;
        foreach ($cfg['fields'] as $f => $def) {
            if ($f === 'animal_id') {
                continue;
            }
            if ($def['type'] === 'animal') {
                $j = 'j' . $i++;
                $select[] = "$j.tag AS " . self::alias($f, 'animal');
                $from .= " LEFT JOIN animals $j ON $j.id = t.$f";
            } elseif ($def['type'] === 'lot') {
                $j = 'j' . $i++;
                $select[] = "$j.name AS " . self::alias($f, 'lot');
                $from .= " LEFT JOIN lots $j ON $j.id = t.$f";
            }
        }
        return [implode(', ', $select), $from];
    }

    /** Valeur affichable d'un champ. */
    public static function format(array $cfg, string $field, array $row, bool $html = true): string
    {
        $def = $cfg['fields'][$field] ?? ['type' => 'text'];
        $v = $row[$field] ?? null;
        $out = match ($def['type']) {
            'animal' => $field === 'animal_id'
                ? trim(($row['animal_tag'] ?? '') . ($row['animal_name'] ? ' (' . $row['animal_name'] . ')' : ''))
                : (string) ($row[self::alias($field, 'animal')] ?? ''),
            'lot'    => (string) ($row[self::alias($field, 'lot')] ?? ''),
            'date'   => fmt_date($v),
            'number' => $v === null ? '' : fmt_num($v, str_contains((string) ($def['step'] ?? '1'), '.') ? 2 : 0),
            'select' => $v === null ? '' : label($def['options'], (string) $v),
            'bool'   => (int) $v ? 'Oui' : 'Non',
            default  => (string) $v,
        };
        if ($html) {
            if ($def['type'] === 'animal' && $out !== '') {
                $id = $row[$field] ?? null;
                return $id ? '<a href="' . e(url('animals/show', ['id' => $id])) . '">' . e($out) . '</a>' : e($out);
            }
            return e($out);
        }
        return $out;
    }

    public static function summary(array $cfg, array $row): string
    {
        try {
            return (string) ($cfg['summary'])($row);
        } catch (\Throwable) {
            return ucfirst($cfg['singular']);
        }
    }
}
