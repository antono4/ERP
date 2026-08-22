<?php
// -----------------------------------------------------
// Auth - Manajemen Sesi & Login
// -----------------------------------------------------

class Auth
{
    public static function attempt(string $username, string $password): bool
    {
        $user = Database::row('SELECT * FROM users WHERE username = ? AND status = 1', [$username]);
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user'] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'full_name' => $user['full_name'],
                'role' => $user['role'],
            ];
            return true;
        }
        return false;
    }

    public static function check(): bool
    {
        return isset($_SESSION['user']);
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            redirect('index.php?page=login');
        }
    }

    public static function requireRole(array $roles): void
    {
        self::requireLogin();
        if (!in_array(self::user()['role'], $roles, true)) {
            setFlash('danger', 'Anda tidak memiliki akses ke halaman ini.');
            redirect('index.php?page=dashboard');
        }
    }

    public static function logout(): void
    {
        session_destroy();
        redirect('index.php?page=login');
    }
}
