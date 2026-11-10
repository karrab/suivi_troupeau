<?php
defined('INC') || exit; // fichier inclus uniquement
/**
 * Validation des formulaires.
 * Règles : "obligatoire|date|nombre|entier|min:0|max:5|dans:a,b|maxcar:30|mincar:8|email|motif:/re/|case"
 *
 * @return array [données nettoyées, erreurs]
 */
function valider(array $saisie, array $regles, array $libelles = []): array
{
    $propre = [];
    $erreurs = [];
    foreach ($regles as $champ => $chaine) {
        $brut = $saisie[$champ] ?? null;
        $v = is_string($brut) ? trim($brut) : $brut;
        $liste = is_array($chaine) ? $chaine : array_filter(explode('|', $chaine));
        $lib = $libelles[$champ] ?? $champ;

        if ($v === '' || $v === null) {
            if (in_array('obligatoire', $liste, true)) {
                $erreurs[$champ] = "Le champ « $lib » est obligatoire.";
            }
            $propre[$champ] = in_array('case', $liste, true) ? 0 : null;
            continue;
        }
        foreach ($liste as $r) {
            [$nom, $arg] = array_pad(explode(':', $r, 2), 2, null);
            $num = (float) str_replace(',', '.', (string) $v);
            $err = match ($nom) {
                'date'   => est_date((string) $v) ? null : "« $lib » doit être une date valide.",
                'nombre' => is_numeric(str_replace(',', '.', (string) $v)) ? null : "« $lib » doit être un nombre.",
                'entier' => filter_var($v, FILTER_VALIDATE_INT) !== false ? null : "« $lib » doit être un entier.",
                'min'    => $num >= (float) $arg ? null : "« $lib » doit être supérieur ou égal à $arg.",
                'max'    => $num <= (float) $arg ? null : "« $lib » doit être inférieur ou égal à $arg.",
                'maxcar' => mb_strlen((string) $v) <= (int) $arg ? null : "« $lib » : $arg caractères maximum.",
                'mincar' => mb_strlen((string) $v) >= (int) $arg ? null : "« $lib » : $arg caractères minimum.",
                'dans'   => in_array((string) $v, explode(',', (string) $arg), true) ? null : "Valeur invalide pour « $lib ».",
                'email'  => filter_var($v, FILTER_VALIDATE_EMAIL) ? null : "« $lib » doit être une adresse e-mail valide.",
                'motif'  => preg_match((string) $arg, (string) $v) ? null : "Format invalide pour « $lib ».",
                default  => null,
            };
            if ($err) {
                $erreurs[$champ] = $err;
                break;
            }
        }
        if (in_array('nombre', $liste, true)) {
            $v = str_replace(',', '.', (string) $v);
        }
        if (in_array('case', $liste, true)) {
            $v = $v ? 1 : 0;
        }
        $propre[$champ] = $v;
    }
    return [$propre, $erreurs];
}

function est_date(string $v): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $v);
    return $d !== false && $d->format('Y-m-d') === $v;
}
