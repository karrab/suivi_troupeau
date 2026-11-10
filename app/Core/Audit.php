<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Journal des traces : toute action (création, modification, suppression,
 * connexion, export, calcul...) est enregistrée avec l'avant / après.
 */
final class Audit
{
    private const HIDDEN = ['password_hash', 'password', '_csrf'];

    public static function log(
        string $action,
        string $entity,
        ?int $entityId = null,
        ?string $summary = null,
        ?array $old = null,
        ?array $new = null,
        ?string $username = null
    ): void {
        try {
            if ($old !== null && $new !== null) {
                [$old, $new] = self::diff($old, $new);
                if (!$new && !$old && $action === 'update') {
                    return; // aucune modification réelle
                }
            }
            $user = Auth::user();
            Database::insert('audit_logs', [
                'user_id'    => $user['id'] ?? null,
                'username'   => $user['username'] ?? $username,
                'action'     => $action,
                'entity'     => $entity,
                'entity_id'  => $entityId,
                'summary'    => $summary !== null ? mb_substr($summary, 0, 255) : null,
                'old_values' => $old !== null ? json_encode(self::clean($old), JSON_UNESCAPED_UNICODE) : null,
                'new_values' => $new !== null ? json_encode(self::clean($new), JSON_UNESCAPED_UNICODE) : null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? (PHP_SAPI === 'cli' ? 'cli' : null),
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null,
            ]);
        } catch (\Throwable $e) {
            error_log('Audit failure: ' . $e->getMessage());
        }
    }

    /** Ne garde que les champs modifiés. */
    public static function diff(array $old, array $new): array
    {
        $o = $n = [];
        foreach ($new as $k => $v) {
            if (in_array($k, ['updated_at', 'created_at'], true)) {
                continue;
            }
            $before = $old[$k] ?? null;
            if ((string) $before !== (string) $v) {
                $o[$k] = $before;
                $n[$k] = $v;
            }
        }
        return [$o, $n];
    }

    private static function clean(array $data): array
    {
        foreach (self::HIDDEN as $h) {
            if (array_key_exists($h, $data)) {
                $data[$h] = '***';
            }
        }
        return $data;
    }
}
