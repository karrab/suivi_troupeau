<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Cache;
use App\Core\Database;
use App\Core\Validator;
use App\Services\PermissionSync;

final class RoleController extends Controller
{
    public function index(): never
    {
        $this->authorize('roles.view');
        $roles = Database::all(
            'SELECT r.*, (SELECT COUNT(*) FROM users u WHERE u.role_id = r.id) AS nb_users,
                    (SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = r.id) AS nb_perms
             FROM roles r ORDER BY r.is_super DESC, r.name'
        );
        $this->render('roles/index', ['roles' => $roles]);
    }

    public function form(): never
    {
        $this->authorize('roles.manage');
        $id = query_int('id');
        $role = $id ? (Database::find('roles', $id) ?? abort(404)) : ['is_super' => 0];
        $granted = $id ? array_flip(Database::query(
            'SELECT p.code FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = :r', ['r' => $id]
        )->fetchAll(\PDO::FETCH_COLUMN)) : [];
        $this->render('roles/form', ['role' => $role, 'granted' => $granted, 'catalog' => PermissionSync::catalog(), 'errors' => form_errors()]);
    }

    public function save(): never
    {
        $this->authorize('roles.manage');
        $id = (int) ($_POST['id'] ?? 0);
        $old = $id ? (Database::find('roles', $id) ?? abort(404)) : null;
        $v = Validator::make($_POST, ['name' => 'required|maxlen:60', 'description' => 'maxlen:255', 'is_super' => 'bool'],
            ['name' => 'Nom', 'description' => 'Description']);
        $d = $v->data();
        $dup = Database::value('SELECT id FROM roles WHERE name = :n', ['n' => (string) $d['name']]);
        if ($dup && (int) $dup !== $id) {
            $v->addError('name', 'Ce nom de rôle existe déjà.');
        }
        // On ne peut pas retirer le statut super-admin de son propre rôle (éviter le blocage)
        if ($old && (int) $old['is_super'] && !(int) $d['is_super'] && (int) Auth::user()['role_id'] === $id) {
            $v->addError('is_super', 'Vous ne pouvez pas retirer l\'accès complet à votre propre rôle.');
        }
        if ($v->fails()) {
            with_input($_POST, $v->errors());
            redirect(url('roles/form', $id ? ['id' => $id] : []));
        }
        $perms = array_values(array_filter((array) ($_POST['perms'] ?? []), 'is_string'));
        $oldPerms = $id ? Database::query('SELECT p.code FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = :r',
            ['r' => $id])->fetchAll(\PDO::FETCH_COLUMN) : [];

        Database::transaction(static function () use (&$id, $d, $perms) {
            $data = ['name' => $d['name'], 'description' => $d['description'] ?: null, 'is_super' => (int) $d['is_super']];
            if ($id) {
                Database::update('roles', $data, $id);
            } else {
                $id = Database::insert('roles', $data);
            }
            Database::query('DELETE FROM role_permissions WHERE role_id = :r', ['r' => $id]);
            if ($perms) {
                $ids = Database::query('SELECT id FROM permissions WHERE code IN (' . implode(',', array_fill(0, count($perms), '?')) . ')', $perms)
                    ->fetchAll(\PDO::FETCH_COLUMN);
                foreach ($ids as $pid) {
                    Database::query('INSERT INTO role_permissions (role_id, permission_id) VALUES (:r, :p)', ['r' => $id, 'p' => (int) $pid]);
                }
            }
        });
        sort($oldPerms);
        sort($perms);
        Audit::log($old ? 'update' : 'create', 'role', $id, 'Rôle ' . $d['name'],
            ($old ?? []) + ['permissions' => implode(', ', $oldPerms)],
            ['name' => $d['name'], 'description' => $d['description'], 'is_super' => (int) $d['is_super'], 'permissions' => implode(', ', $perms)]);
        Cache::bump('perms');
        flash('success', 'Rôle enregistré.');
        redirect(url('roles'));
    }

    public function delete(): never
    {
        $this->authorize('roles.manage');
        $id = (int) ($_POST['id'] ?? 0);
        $old = Database::find('roles', $id) ?? abort(404);
        if ((int) Database::value('SELECT COUNT(*) FROM users WHERE role_id = :r', ['r' => $id]) > 0) {
            flash('danger', 'Impossible de supprimer un rôle attribué à des utilisateurs.');
            redirect(url('roles'));
        }
        Database::delete('roles', $id);
        Audit::log('delete', 'role', $id, 'Suppression du rôle ' . $old['name'], $old, null);
        Cache::bump('perms');
        flash('success', 'Rôle supprimé.');
        redirect(url('roles'));
    }
}
