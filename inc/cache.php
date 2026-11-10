<?php
defined('INC') || exit; // fichier inclus uniquement
/**
 * Cache APCu (mémoire partagée entre les requêtes), avec repli sur un
 * cache limité à la requête si APCu est absent.
 *
 * Invalidation par « étiquettes versionnées » : chaque étiquette (animaux,
 * suivis, indices, parametres, permissions, referentiels...) a un numéro de
 * version inclus dans les clés. cache_invalider('animaux') incrémente ce
 * numéro, ce qui rend obsolètes toutes les clés liées en une seule opération.
 */

$GLOBALS['CACHE_LOCAL'] = [];

function cache_actif(): bool
{
    static $actif = null;
    return $actif ??= function_exists('apcu_enabled') && apcu_enabled();
}

function cache_cle_brute(string $cle): string
{
    return config('cache.prefixe', 'app:') . $cle;
}

function cache_lire(string $cle, mixed $defaut = null): mixed
{
    $k = cache_cle_brute($cle);
    if (array_key_exists($k, $GLOBALS['CACHE_LOCAL'])) {
        return $GLOBALS['CACHE_LOCAL'][$k];
    }
    if (cache_actif()) {
        $ok = false;
        $v = apcu_fetch($k, $ok);
        if ($ok) {
            return $GLOBALS['CACHE_LOCAL'][$k] = $v;
        }
    }
    return $defaut;
}

function cache_ecrire(string $cle, mixed $valeur, ?int $duree = null): void
{
    $k = cache_cle_brute($cle);
    $GLOBALS['CACHE_LOCAL'][$k] = $valeur;
    if (cache_actif()) {
        apcu_store($k, $valeur, $duree ?? (int) config('cache.duree', 300));
    }
}

/** Retourne la valeur en cache, sinon la calcule avec $fn et la mémorise. */
function cache_memoriser(string $cle, int $duree, callable $fn): mixed
{
    $absent = "\0absent";
    $v = cache_lire($cle, $absent);
    if ($v !== $absent) {
        return $v;
    }
    $v = $fn();
    cache_ecrire($cle, $v, $duree);
    return $v;
}

function cache_version(string $etiquette): int
{
    $v = cache_lire('v:' . $etiquette);
    if ($v === null) {
        $v = 1;
        cache_ecrire('v:' . $etiquette, 1, 0);
    }
    return (int) $v;
}

function cache_invalider(string ...$etiquettes): void
{
    foreach ($etiquettes as $e) {
        $k = cache_cle_brute('v:' . $e);
        if (cache_actif()) {
            if (apcu_inc($k) === false) {
                apcu_store($k, 2, 0);
            }
            unset($GLOBALS['CACHE_LOCAL'][$k]);
        } else {
            $GLOBALS['CACHE_LOCAL'][$k] = ($GLOBALS['CACHE_LOCAL'][$k] ?? 1) + 1;
        }
    }
}

/** Clé dépendant d'étiquettes : cache_cle(['animaux'], 'total'). */
function cache_cle(array $etiquettes, string $cle): string
{
    $p = [];
    foreach ($etiquettes as $e) {
        $p[] = $e . cache_version($e);
    }
    return implode('.', $p) . ':' . $cle;
}

function cache_vider(): void
{
    $GLOBALS['CACHE_LOCAL'] = [];
    if (cache_actif()) {
        class_exists('APCUIterator')
            ? apcu_delete(new APCUIterator('/^' . preg_quote(config('cache.prefixe', 'app:'), '/') . '/'))
            : apcu_clear_cache();
    }
}

function cache_stats(): array
{
    if (!cache_actif()) {
        return ['actif' => false];
    }
    $i = apcu_cache_info(true);
    $m = apcu_sma_info(true);
    return [
        'actif'   => true,
        'entrees' => $i['num_entries'] ?? 0,
        'succes'  => $i['num_hits'] ?? 0,
        'echecs'  => $i['num_misses'] ?? 0,
        'memoire' => ($m['num_seg'] ?? 1) * ($m['seg_size'] ?? 0),
        'libre'   => $m['avail_mem'] ?? 0,
    ];
}
