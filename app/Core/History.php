<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Historique (chronologie) d'un animal.
 */
final class History
{
    public static function add(
        int $animalId,
        string $date,
        string $type,
        string $summary,
        ?string $refTable = null,
        ?int $refId = null,
        ?array $changes = null
    ): void {
        Database::insert('animal_history', [
            'animal_id'  => $animalId,
            'event_date' => $date ?: date('Y-m-d'),
            'event_type' => $type,
            'ref_table'  => $refTable,
            'ref_id'     => $refId,
            'summary'    => mb_substr($summary, 0, 500),
            'changes'    => $changes ? json_encode($changes, JSON_UNESCAPED_UNICODE) : null,
            'user_id'    => Auth::id(),
        ]);
    }

    /** Met à jour le résumé d'une entrée liée à un enregistrement de suivi. */
    public static function syncRef(string $refTable, int $refId, int $animalId, string $date, string $summary): void
    {
        Database::query(
            'UPDATE animal_history SET animal_id = :a, event_date = :d, summary = :s WHERE ref_table = :t AND ref_id = :r AND event_type <> :x',
            ['a' => $animalId, 'd' => $date, 's' => mb_substr($summary, 0, 500), 't' => $refTable, 'r' => $refId, 'x' => 'suppression']
        );
    }
}
