<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Authentification par session + contrôle des permissions (RBAC).
 * Les permissions d'un rôle sont mises en cache APCu (tag "perms").
 */
final class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        session_name(App::config('app.session_name', 'APPSESSID'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        session_start();

        $lifetime = (int) App::config('app.session_lifetime', 7200);
        if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > $lifetime) {
            $_SESSION = [];
            session_regenerate_id(true);
        }
        $_SESSION['last_activity'] = time();
    }

    /**
     * @return true|string true si succès, sinon le message d'erreur
     */
    public static function attempt(string $username, string $password): bool|string
    {
        $u = Database::one(
            'SELECT u.*, r.name AS role_name, r.is_super FROM users u JOIN roles r ON r.id = u.role_id WHERE u.username = :u',
            ['u' => $username]
        );
        $generic = 'Identifiant ou mot de passe incorrect.';
        if (!$u) {
            password_verify($password, '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG'); // timing
            Audit::log('login_failed', 'user', null, 'Échec de connexion : ' . $username, null, null, $username);
            return $generic;
        }
        if ($u['locked_until'] && strtotime($u['locked_until']) > time()) {
            return 'Compte temporairement verrouillé suite à trop de tentatives. Réessayez plus tard.';
        }
        if (!(int) $u['is_active']) {
            return 'Ce compte est désactivé.';
        }
        if (!password_verify($password, $u['password_hash'])) {
            $attempts = (int) $u['failed_attempts'] + 1;
            $max = (int) App::config('security.max_login_attempts', 5);
            $lock = $attempts >= $max
                ? date('Y-m-d H:i:s', time() + 60 * (int) App::config('security.lockout_minutes', 15))
                : null;
            Database::query(
                'UPDATE users SET failed_attempts = :a, locked_until = :l WHERE id = :id',
                ['a' => $lock ? 0 : $attempts, 'l' => $lock, 'id' => (int) $u['id']]
            );
            Audit::log('login_failed', 'user', (int) $u['id'], 'Mot de passe incorrect', null, null, $username);
            return $generic;
        }
        if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
            Database::query('UPDATE users SET password_hash = :h WHERE id = :id', [
                'h' => password_hash($password, PASSWORD_DEFAULT), 'id' => (int) $u['id'],
            ]);
        }
        session_regenerate_id(true);
        $_SESSION['uid'] = (int) $u['id'];
        Database::query(
            'UPDATE users SET last_login_at = NOW(), failed_attempts = 0, locked_until = NULL WHERE id = :id',
            ['id' => (int) $u['id']]
        );
        self::$loaded = false;
        Audit::log('login', 'user', (int) $u['id'], 'Connexion');
        return true;
    }

    public static function logout(): void
    {
        if (self::check()) {
            Audit::log('logout', 'user', self::id(), 'Déconnexion');
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        self::$user = null;
    }

    public static function user(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            self::$user = null;
            $uid = $_SESSION['uid'] ?? null;
            if ($uid) {
                $u = Database::one(
                    'SELECT u.id, u.username, u.full_name, u.email, u.role_id, u.is_active, r.name AS role_name, r.is_super
                       FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = :id',
                    ['id' => (int) $uid]
                );
                if ($u && (int) $u['is_active']) {
                    self::$user = $u;
                } else {
                    unset($_SESSION['uid']);
                }
            }
        }
        return self::$user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        return self::user() ? (int) self::$user['id'] : null;
    }

    public static function permissions(int $roleId): array
    {
        return Cache::remember(Cache::tagged(['perms'], 'role:' . $roleId), 3600, static function () use ($roleId) {
            $codes = Database::query(
                'SELECT p.code FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = :r',
                ['r' => $roleId]
            )->fetchAll(\PDO::FETCH_COLUMN);
            return array_fill_keys($codes, true);
        });
    }

    public static function can(string $permission): bool
    {
        $u = self::user();
        if (!$u) {
            return false;
        }
        if ((int) $u['is_super']) {
            return true;
        }
        return isset(self::permissions((int) $u['role_id'])[$permission]);
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            $_SESSION['intended'] = $_SERVER['REQUEST_URI'] ?? null;
            redirect(url('login'));
        }
    }

    public static function authorize(string $permission): void
    {
        self::requireLogin();
        if (!self::can($permission)) {
            Audit::log('forbidden', 'permission', null, 'Accès refusé : ' . $permission);
            abort(403, "Vous n'avez pas la permission « " . $permission . ' ».');
        }
    }
}
