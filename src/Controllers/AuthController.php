<?php
declare(strict_types=1);

namespace Apollon\Controllers;

use Apollon\Request;
use Apollon\Language;
use Apollon\Response;
use Apollon\Security;
use Apollon\Session;
use PDO;

final class AuthController
{
    public function __construct(private PDO $db)
    {
    }

    public function getSession(): void
    {
        $userId = Session::userId();
        if ($userId) {
            $stmt = $this->db->prepare("SELECT id, username, theme FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            if ($user) {
                Response::json(['status' => 'loggedin', 'user' => $user, 'csrf_token' => Session::csrfToken()]);
            }
        }
        Response::json(['status' => 'loggedout', 'csrf_token' => Session::csrfToken()]);
    }

    public function register(): void
    {
        if (!ALLOW_REGISTRATION) {
            Response::json(['status' => 'error', 'message' => Language::t('api.registration_disabled')], 403);
        }
        Security::verifyCsrf();
        $data = Request::jsonBody();
        $username = trim((string)($data['username'] ?? ''));
        $password = $data['password'] ?? '';

        if ($username === '' || strlen($password) < 6) {
            Response::json(['status' => 'error', 'message' => Language::t('api.valid_username_password')], 400);
        }
        $stmt = $this->db->prepare("SELECT id FROM users WHERE LOWER(username) = LOWER(?)");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            Response::json(['status' => 'error', 'message' => Language::t('api.username_taken')], 409);
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
        $stmt->execute([$username, $hash]);
        Response::json(['status' => 'success', 'message' => Language::t('api.registered')]);
    }

    public function login(): void
    {
        Security::verifyCsrf();
        $data = Request::jsonBody();
        $username = trim((string)($data['username'] ?? ''));
        $password = $data['password'] ?? '';

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        if ($username === '' || $password === '') {
            Response::json(['status' => 'error', 'message' => Language::t('api.username_password_required')], 400);
        }

        $window = 900;
        $maxAttempts = 5;
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM login_attempts WHERE username = ? AND attempted_at > ?");
        $stmt->execute([strtolower($username), time() - $window]);
        if ((int)$stmt->fetchColumn() >= $maxAttempts) {
            Response::json(['status' => 'error', 'message' => Language::t('api.too_many_attempts')], 429);
        }

        $stmt = $this->db->prepare("SELECT * FROM users WHERE LOWER(username) = LOWER(?)");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            $this->db->prepare("DELETE FROM login_attempts WHERE username = ?")->execute([strtolower($username)]);
            Session::regenerate();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_username'] = $user['username'];
            unset($user['password_hash']);
            Response::json(['status' => 'success', 'user' => $user, 'csrf_token' => Session::csrfToken()]);
        } else {
            $this->db->prepare("INSERT INTO login_attempts (username, ip, attempted_at) VALUES (?, ?, ?)")->execute([strtolower($username), $ip, time()]);
            Response::json(['status' => 'error', 'message' => Language::t('api.invalid_credentials')], 401);
        }
    }

    public function logout(): void
    {
        Security::verifyCsrf();
        Session::destroy();
        Response::json(['status' => 'success']);
    }

    public function changeUsername(): void
    {
        Security::verifyCsrf();
        $userId = Session::userId();
        if (!$userId) {
            Response::json(['status' => 'error', 'message' => Language::t('api.unauthorized')], 403);
        }
        $data = Request::jsonBody();
        $username = trim((string)($data['username'] ?? ''));
        if ($username === '') {
            Response::json(['status' => 'error', 'message' => Language::t('api.username_empty')], 400);
        }
        $stmt = $this->db->prepare("SELECT id FROM users WHERE LOWER(username) = LOWER(?) AND id != ?");
        $stmt->execute([$username, $userId]);
        if ($stmt->fetch()) {
            Response::json(['status' => 'error', 'message' => Language::t('api.username_taken')], 409);
        }
        $stmt = $this->db->prepare("UPDATE users SET username = ? WHERE id = ?");
        $stmt->execute([$username, $userId]);
        $_SESSION['user_username'] = $username;
        Response::json(['status' => 'success', 'message' => Language::t('api.username_updated'), 'username' => $username]);
    }

    public function changePassword(): void
    {
        Security::verifyCsrf();
        $userId = Session::userId();
        if (!$userId) {
            Response::json(['status' => 'error', 'message' => Language::t('api.unauthorized')], 403);
        }
        $data = Request::jsonBody();
        $newPassword = $data['new_password'] ?? '';
        if (strlen($newPassword) < 6) {
            Response::json(['status' => 'error', 'message' => Language::t('api.password_min')], 400);
        }
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$hash, $userId]);
        Response::json(['status' => 'success', 'message' => Language::t('api.password_changed')]);
    }

    public function setTheme(): void
    {
        Security::verifyCsrf();
        $userId = Session::userId();
        if (!$userId) {
            Response::json(['status' => 'error', 'message' => Language::t('api.unauthorized')], 403);
        }
        $data = Request::jsonBody();
        $theme = (string)($data['theme'] ?? '');
        if (!in_array($theme, ['light', 'dark'], true)) {
            Response::json(['status' => 'error', 'message' => Language::t('api.invalid_theme')], 400);
        }
        $this->db->prepare("UPDATE users SET theme = ? WHERE id = ?")->execute([$theme, $userId]);
        Response::json(['status' => 'success', 'message' => Language::t('api.theme_updated'), 'theme' => $theme]);
    }

    public function deleteAccount(): void
    {
        Security::verifyCsrf();
        $userId = Session::userId();
        if (!$userId) {
            Response::json(['status' => 'error', 'message' => Language::t('api.unauthorized')], 403);
        }
        $this->db->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
        Session::destroy();
        Response::json(['status' => 'success', 'message' => Language::t('api.account_deleted')]);
    }
}
