<?php
declare(strict_types=1);

use App\Core\App;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Settings;
use App\Core\View;

function e(mixed $v): string
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $route = '', array $params = []): string
{
    $base = rtrim((string) App::config('app.base_url', ''), '/');
    $q = $route !== '' ? ['r' => $route] + $params : $params;
    $params = array_filter($q, static fn($v) => $v !== null && $v !== '');
    return $base . '/index.php' . ($params ? '?' . http_build_query($params) : '');
}

function asset(string $path): string
{
    $base = rtrim((string) App::config('app.base_url', ''), '/');
    $file = APP_ROOT . '/public/assets/' . $path;
    $v = is_file($file) ? filemtime($file) : 0;
    return $base . '/assets/' . $path . '?v=' . $v;
}

function redirect(string $to): never
{
    header('Location: ' . $to, true, 302);
    exit;
}

function back(string $fallback = ''): never
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    redirect($ref !== '' ? $ref : ($fallback ?: url('dashboard')));
}

function abort(int $code, string $message = ''): never
{
    http_response_code($code);
    if (PHP_SAPI === 'cli') {
        throw new RuntimeException("HTTP $code: $message");
    }
    echo View::render('errors/error', ['code' => $code, 'message' => $message], Auth::check() ? 'layouts/main' : 'layouts/blank');
    exit;
}

function json_response(mixed $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function flashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

/** Mémorise les saisies et erreurs pour réafficher un formulaire. */
function with_input(array $input, array $errors = []): void
{
    unset($input['_csrf']);
    $_SESSION['_old'] = $input;
    $_SESSION['_errors'] = $errors;
}

function old(string $key, mixed $default = ''): mixed
{
    return $_SESSION['_old'][$key] ?? $default;
}

function form_errors(): array
{
    $e = $_SESSION['_errors'] ?? [];
    return $e;
}

function clear_old(): void
{
    unset($_SESSION['_old'], $_SESSION['_errors']);
}

function csrf_field(): string
{
    return Csrf::field();
}

function can(string $permission): bool
{
    return Auth::can($permission);
}

function input(string $key, mixed $default = null): mixed
{
    $v = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

function query_int(string $key, int $default = 0): int
{
    return isset($_GET[$key]) && is_numeric($_GET[$key]) ? (int) $_GET[$key] : $default;
}

function setting(string $key, mixed $default = null): mixed
{
    return Settings::get($key, $default);
}

function fmt_date(?string $d, bool $withTime = false): string
{
    if (!$d) {
        return '';
    }
    $ts = strtotime($d);
    return $ts ? date($withTime ? 'd/m/Y H:i' : 'd/m/Y', $ts) : $d;
}

function fmt_num(mixed $n, int $dec = 2): string
{
    if ($n === null || $n === '') {
        return '';
    }
    return number_format((float) $n, $dec, ',', ' ');
}

function fmt_money(mixed $n): string
{
    return $n === null || $n === '' ? '' : fmt_num($n, 2) . ' ' . setting('devise', 'DH');
}

/** Âge lisible à partir d'une date de naissance. */
function fmt_age(?string $birth, ?string $until = null): string
{
    if (!$birth) {
        return '';
    }
    $a = new DateTime($birth);
    $b = new DateTime($until ?: 'today');
    if ($b < $a) {
        return '';
    }
    $diff = $a->diff($b);
    if ($diff->y > 0) {
        return $diff->y . ' an' . ($diff->y > 1 ? 's' : '') . ($diff->m ? ' ' . $diff->m . ' mois' : '');
    }
    if ($diff->m > 0) {
        return $diff->m . ' mois';
    }
    return $diff->days . ' j';
}

function labels(string $group): array
{
    static $labels = null;
    $labels ??= require APP_ROOT . '/app/Config/labels.php';
    return $labels[$group] ?? [];
}

function label(string $group, ?string $key): string
{
    return labels($group)[$key ?? ''] ?? (string) $key;
}

function current_route(): string
{
    return (string) ($_GET['r'] ?? 'dashboard');
}

function route_is(string ...$prefixes): bool
{
    $r = current_route();
    foreach ($prefixes as $p) {
        if ($r === $p || str_starts_with($r, $p . '/')) {
            return true;
        }
    }
    return false;
}

/** Query string courante modifiée (pour la pagination / tri / export). */
function query_with(array $changes, array $drop = []): array
{
    $q = $_GET;
    foreach ($drop as $d) {
        unset($q[$d]);
    }
    foreach ($changes as $k => $v) {
        if ($v === null) {
            unset($q[$k]);
        } else {
            $q[$k] = $v;
        }
    }
    return $q;
}

function url_with(array $changes, array $drop = []): string
{
    $q = query_with($changes, $drop);
    $r = $q['r'] ?? 'dashboard';
    unset($q['r']);
    return url($r, $q);
}

function level_badge(?string $level): string
{
    $map = ['bon' => 'success', 'moyen' => 'warning', 'faible' => 'danger', 'na' => 'secondary'];
    $txt = ['bon' => 'Bon', 'moyen' => 'Moyen', 'faible' => 'Faible', 'na' => 'N/D'];
    $l = $level ?? 'na';
    return '<span class="badge text-bg-' . ($map[$l] ?? 'secondary') . '">' . ($txt[$l] ?? e($l)) . '</span>';
}

function status_badge(string $status): string
{
    $map = ['actif' => 'success', 'vendu' => 'info', 'mort' => 'dark', 'reforme' => 'warning', 'perdu' => 'secondary'];
    return '<span class="badge text-bg-' . ($map[$status] ?? 'secondary') . '">' . e(label('status', $status)) . '</span>';
}

/** Classe Bootstrap d'erreur + message pour un champ. */
function invalid(array $errors, string $field): string
{
    return isset($errors[$field]) ? ' is-invalid' : '';
}

function field_error(array $errors, string $field): string
{
    return isset($errors[$field]) ? '<div class="invalid-feedback d-block">' . e($errors[$field]) . '</div>' : '';
}

/** Valeur d'un champ de formulaire : saisie précédente (après erreur) sinon valeur de l'objet. */
function fv(array $obj, string $field, mixed $default = ''): string
{
    if (isset($_SESSION['_old']) && array_key_exists($field, $_SESSION['_old'])) {
        $v = $_SESSION['_old'][$field];
        return is_array($v) ? '' : (string) $v;
    }
    return (string) ($obj[$field] ?? $default);
}

function selected(mixed $a, mixed $b): string
{
    return (string) $a === (string) $b ? ' selected' : '';
}

function checked(mixed $v): string
{
    return $v ? ' checked' : '';
}

/** En-tête de colonne triable (tri keyset). */
function sort_link(string $key, string $label): string
{
    $cur = $_GET['sort'] ?? 'id';
    $dir = strtolower((string) ($_GET['dir'] ?? 'desc'));
    $newDir = $cur === $key && $dir === 'asc' ? 'desc' : 'asc';
    $icon = $cur === $key ? ($dir === 'asc' ? ' <i class="bi bi-caret-up-fill"></i>' : ' <i class="bi bi-caret-down-fill"></i>') : '';
    return '<a class="text-reset text-decoration-none" href="' . e(url_with(['sort' => $key, 'dir' => $newDir], ['cursor', 'dir_page'])) . '">' . e($label) . $icon . '</a>';
}
