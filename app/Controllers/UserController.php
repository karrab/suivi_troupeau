<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\KeysetPaginator;
use App\Core\Pdf\PdfReport;
use App\Core\Validator;

final class UserController extends Controller
{
    private function buildQuery(): KeysetPaginator
    {
        $where = [];
        $p = [];
        if (($q = trim((string) ($_GET['q'] ?? ''))) !== '') {
            $where[] = '(u.username LIKE :q1 OR u.full_name LIKE :q2 OR u.email LIKE :q3)';
            $p['q1'] = $p['q2'] = $p['q3'] = '%' . $q . '%';
        }
        if (!empty($_GET['role_id']) && ctype_digit((string) $_GET['role_id'])) {
            $where[] = 'u.role_id = :r';
            $p['r'] = (int) $_GET['role_id'];
        }
        if (isset($_GET['active']) && in_array($_GET['active'], ['0', '1'], true)) {
            $where[] = 'u.is_active = :a';
            $p['a'] = (int) $_GET['active'];
        }
        return new KeysetPaginator(
            'u.id, u.username, u.full_name, u.email, u.is_active, u.last_login_at, u.created_at, u.locked_until, r.name AS role_name',
            'FROM users u JOIN roles r ON r.id = u.role_id', $where, $p, 'u.id', 'id', 'ASC', $this->perPage(), 'u.id', 'id'
        );
    }

    public function index(): never
    {
        $this->authorize('users.view');
        $pager = $this->buildQuery();
        $page = $pager->paginate($_GET['cursor'] ?? null, ($_GET['dir_page'] ?? 'after') === 'before' ? 'before' : 'after');
        $this->render('users/index', [
            'page' => $page, 'total' => $pager->count('users'),
            'roles' => Database::pairs('SELECT id, name FROM roles ORDER BY name'),
        ]);
    }

    public function export(): never
    {
        $this->authorize('users.export');
        $rows = $this->buildQuery()->all();
        Audit::log('export', 'user', null, 'Export PDF des utilisateurs');
        $pdf = new PdfReport('Utilisateurs', $this->filterSummary(['q' => 'Recherche', 'role_id' => 'Rôle n°', 'active' => 'Actif']));
        $pdf->addPage();
        $pdf->table([
            ['label' => 'Identifiant', 'key' => 'username'], ['label' => 'Nom complet', 'key' => 'full_name', 'w' => 1.5],
            ['label' => 'E-mail', 'key' => 'email', 'w' => 1.6], ['label' => 'Rôle', 'key' => 'role_name'],
            ['label' => 'Actif', 'key' => 'active', 'w' => 0.5], ['label' => 'Dernière connexion', 'key' => 'last', 'w' => 1.1],
        ], array_map(static fn($r) => $r + ['active' => $r['is_active'] ? 'Oui' : 'Non', 'last' => fmt_date($r['last_login_at'], true)], $rows));
        $pdf->send('utilisateurs.pdf');
    }

    public function create(): never
    {
        $this->authorize('users.create');
        $this->form(['is_active' => 1], false);
    }

    public function edit(): never
    {
        $this->authorize('users.edit');
        $this->form(Database::find('users', query_int('id')) ?? abort(404, 'Utilisateur introuvable.'), true);
    }

    private function form(array $user, bool $isEdit): never
    {
        $this->render('users/form', [
            'user' => $user, 'isEdit' => $isEdit, 'errors' => form_errors(),
            'roles' => Database::pairs('SELECT id, name FROM roles ORDER BY name'),
        ]);
    }

    private function validate(?array $existing): array
    {
        $v = Validator::make($_POST, [
            'username' => 'required|maxlen:60|regex:/^[A-Za-z0-9_.-]+$/', 'full_name' => 'required|maxlen:120',
            'email' => 'email|maxlen:160', 'role_id' => 'required|int', 'is_active' => 'bool',
            'password' => ($existing ? '' : 'required|') . 'minlen:8|maxlen:100',
        ], ['username' => 'Identifiant', 'full_name' => 'Nom complet', 'email' => 'E-mail', 'role_id' => 'Rôle', 'password' => 'Mot de passe']);
        $d = $v->data();
        foreach (['username' => 'Cet identifiant', 'email' => 'Cet e-mail'] as $k => $lbl) {
            if ($d[$k]) {
                $dup = Database::value("SELECT id FROM users WHERE $k = :v", ['v' => $d[$k]]);
                if ($dup && (!$existing || (int) $dup !== (int) $existing['id'])) {
                    $v->addError($k, "$lbl est déjà utilisé.");
                }
            }
        }
        if (!Database::value('SELECT id FROM roles WHERE id = :id', ['id' => (int) $d['role_id']])) {
            $v->addError('role_id', 'Rôle invalide.');
        }
        if ($existing && (int) $existing['id'] === Auth::id() && !(int) $d['is_active']) {
            $v->addError('is_active', 'Vous ne pouvez pas désactiver votre propre compte.');
        }
        if (!empty($d['password']) && $d['password'] !== ($_POST['password_confirm'] ?? '')) {
            $v->addError('password', 'La confirmation du mot de passe ne correspond pas.');
        }
        $data = [
            'username' => $d['username'], 'full_name' => $d['full_name'], 'email' => $d['email'] ?: null,
            'role_id' => (int) $d['role_id'], 'is_active' => (int) $d['is_active'],
        ];
        if (!empty($d['password'])) {
            $data['password_hash'] = password_hash((string) $d['password'], PASSWORD_DEFAULT);
        }
        return [$data, $v->errors()];
    }

    public function store(): never
    {
        $this->authorize('users.create');
        [$d, $errors] = $this->validate(null);
        if ($errors) {
            with_input($_POST, $errors);
            redirect(url('users/create'));
        }
        $id = Database::insert('users', $d);
        Audit::log('create', 'user', $id, 'Création de l\'utilisateur ' . $d['username'], null, $d);
        flash('success', 'Utilisateur créé.');
        redirect(url('users'));
    }

    public function update(): never
    {
        $this->authorize('users.edit');
        $id = (int) ($_POST['id'] ?? 0);
        $old = Database::find('users', $id) ?? abort(404);
        [$d, $errors] = $this->validate($old);
        if ($errors) {
            with_input($_POST, $errors);
            redirect(url('users/edit', ['id' => $id]));
        }
        if (!empty($_POST['unlock'])) {
            $d['locked_until'] = null;
            $d['failed_attempts'] = 0;
        }
        Database::update('users', $d, $id);
        Audit::log('update', 'user', $id, 'Modification de l\'utilisateur ' . $d['username'], $old, $d);
        flash('success', 'Utilisateur modifié.');
        redirect(url('users'));
    }

    public function delete(): never
    {
        $this->authorize('users.delete');
        $id = (int) ($_POST['id'] ?? 0);
        if ($id === Auth::id()) {
            flash('danger', 'Vous ne pouvez pas supprimer votre propre compte.');
            redirect(url('users'));
        }
        $old = Database::find('users', $id) ?? abort(404);
        Database::delete('users', $id);
        Audit::log('delete', 'user', $id, 'Suppression de l\'utilisateur ' . $old['username'], $old, null);
        flash('success', 'Utilisateur supprimé.');
        redirect(url('users'));
    }
}
