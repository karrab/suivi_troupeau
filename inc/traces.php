<?php
defined('INC') || exit; // fichier inclus uniquement
/**
 * Traces (journal d'audit) et historique des animaux.
 */

/**
 * Enregistre une trace. Si $avant et $apres sont fournis, seuls les champs
 * réellement modifiés sont conservés.
 */
function tracer(string $action, string $objet, ?int $objetId = null, ?string $resume = null,
                ?array $avant = null, ?array $apres = null, ?string $identifiant = null): void
{
    try {
        if ($avant !== null && $apres !== null) {
            [$avant, $apres] = differences($avant, $apres);
            if (!$apres && $action === 'modification') {
                return;
            }
        }
        $masquer = static function (?array $d): ?string {
            if ($d === null) {
                return null;
            }
            foreach (['mot_de_passe', '_csrf'] as $k) {
                if (array_key_exists($k, $d)) {
                    $d[$k] = '***';
                }
            }
            return json_encode($d, JSON_UNESCAPED_UNICODE);
        };
        $u = PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE ? utilisateur() : null;
        bd_inserer('traces', [
            'utilisateur_id'    => $u['id'] ?? null,
            'identifiant'       => $u['identifiant'] ?? $identifiant,
            'action'            => $action,
            'objet'             => $objet,
            'objet_id'          => $objetId,
            'resume'            => $resume !== null ? mb_substr($resume, 0, 255) : null,
            'anciennes_valeurs' => $masquer($avant),
            'nouvelles_valeurs' => $masquer($apres),
            'adresse_ip'        => $_SERVER['REMOTE_ADDR'] ?? (PHP_SAPI === 'cli' ? 'cli' : null),
            'navigateur'        => isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null,
        ]);
    } catch (Throwable $e) {
        error_log('Échec de la trace : ' . $e->getMessage());
    }
}

/** Ne garde que les champs modifiés : [avant, après]. */
function differences(array $avant, array $apres): array
{
    $a = $n = [];
    foreach ($apres as $k => $v) {
        if (in_array($k, ['cree_le', 'modifie_le'], true)) {
            continue;
        }
        $old = $avant[$k] ?? null;
        if ((string) $old !== (string) $v) {
            $a[$k] = $old;
            $n[$k] = $v;
        }
    }
    return [$a, $n];
}

/** Ajoute un événement dans la chronologie d'un animal. */
function historique_ajouter(int $animalId, ?string $date, string $type, string $resume,
                            ?string $tableRef = null, ?int $refId = null, ?array $changements = null): void
{
    bd_inserer('historique_animaux', [
        'animal_id'      => $animalId,
        'date_evenement' => $date ?: date('Y-m-d'),
        'type_evenement' => $type,
        'table_ref'      => $tableRef,
        'ref_id'         => $refId,
        'resume'         => mb_substr($resume, 0, 500),
        'changements'    => $changements ? json_encode($changements, JSON_UNESCAPED_UNICODE) : null,
        'utilisateur_id' => utilisateur_id(),
    ]);
}

/** Met à jour l'entrée d'historique (de l'animal principal) liée à un enregistrement de suivi modifié. */
function historique_synchroniser(string $tableRef, int $refId, int $ancienAnimalId, int $animalId, string $date, string $resume): void
{
    bd_requete(
        "UPDATE historique_animaux SET animal_id = :a, date_evenement = :d, resume = :r
         WHERE table_ref = :t AND ref_id = :i AND animal_id = :ancien AND type_evenement NOT IN ('suppression', 'naissance')",
        ['a' => $animalId, 'd' => $date, 'r' => mb_substr($resume, 0, 500), 't' => $tableRef, 'i' => $refId, 'ancien' => $ancienAnimalId]
    );
}
