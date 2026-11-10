<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Validator;

final class AuthController extends Controller
{
    public function login(): never
    {
        if (Auth::check()) {
            redirect(url('dashboard'));
        }
        $error = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $result = $username !== '' ? Auth::attempt($username, $password) : 'Identifiant requis.';
            if ($result === true) {
                $to = $_SESSION['intended'] ?? url('dashboard');
                unset($_SESSION['intended']);
                redirect(is_string($to) && str_starts_with($to, '/') && !str_starts_with($to, '//') ? $to : url('dashboard'));
            }
            $error = $result;
        }
        $this->render('auth/login', ['error' => $error], 'layouts/blank');
    }

    public function logout(): never
    {
        Auth::logout();
        redirect(url('login'));
    }

    public function profile(): never
    {
        $this->render('auth/profile', ['user' => Auth::user(), 'errors' => form_errors()]);
    }

    public function password(): never
    {
        $v = Validator::make($_POST, [
            'current'  => 'required',
            'password' => 'required|minlen:8|maxlen:100',
            'confirm'  => 'required',
        ], ['current' => 'Mot de passe actuel', 'password' => 'Nouveau mot de passe', 'confirm' => 'Confirmation']);
        $hash = (string) Database::value('SELECT password_hash FROM users WHERE id = :id', ['id' => Auth::id()]);
        if (!$v->fails() && !password_verify((string) $_POST['current'], $hash)) {
            $v->addError('current', 'Mot de passe actuel incorrect.');
        }
        if (!$v->fails() && $_POST['password'] !== $_POST['confirm']) {
            $v->addError('confirm', 'La confirmation ne correspond pas.');
        }
        if ($v->fails()) {
            with_input([], $v->errors());
            redirect(url('profile'));
        }
        Database::query('UPDATE users SET password_hash = :h WHERE id = :id', [
            'h' => password_hash((string) $_POST['password'], PASSWORD_DEFAULT), 'id' => Auth::id(),
        ]);
        Audit::log('update', 'user', Auth::id(), 'Changement de son mot de passe');
        flash('success', 'Mot de passe modifié.');
        redirect(url('profile'));
    }
}
