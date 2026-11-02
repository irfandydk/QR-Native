<?php
/**
 * QR-SAR - Authentication Controller
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Handles User Login, Session Invalidation (Logout), and Profile Redirection.
 */

declare(strict_types=1);

class AuthController extends BaseController {
    /**
     * Handle login display and authentication submission.
     */
    public function login(): void {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if (AuthHelper::isLoggedIn() && $method === 'GET') {
            $this->redirect('/index.php');
        }

        if ($method === 'POST') {
            if (!$this->validateCsrf($_POST['csrf_token'] ?? null)) {
                $this->setFlash('error', 'Token keamanan CSRF tidak valid atau sesi Anda telah kedaluwarsa. Silakan muat ulang halaman.');
                $this->redirect('/login.php');
            }

            $username = trim((string)($_POST['username'] ?? ''));
            $password = (string)($_POST['password'] ?? '');

            $res = AuthHelper::login($username, $password);
            if ($res['success']) {
                $this->setFlash('success', 'Selamat datang kembali, ' . ($res['user']['nama'] ?? 'Petugas SAR') . '!');
                $rawRedirect = $_SESSION['redirect_url'] ?? (BASE_URL . '/index.php');
                unset($_SESSION['redirect_url']);

                // Sanity check against open redirect vulnerabilities (CWE-601) via AuthHelper
                $redirect = AuthHelper::sanitizeRedirectUrl($rawRedirect, BASE_URL . '/index.php');
                header("Location: {$redirect}");
                exit;
            } else {
                $this->setFlash('error', $res['error'] ?? $res['message'] ?? 'Nama pengguna atau kata sandi tidak valid.');
                $this->redirect('/login.php');
            }
        }

        // GET: render login view
        $this->render('auth/login', [], ['layout' => 'none']);
    }

    /**
     * Invalidate user session and redirect to login page.
     */
    public function logout(): void {
        $token = $_POST['csrf_token'] ?? ($_GET['csrf_token'] ?? null);

        if (!$this->validateCsrf($token)) {
            $this->setFlash('error', 'Permintaan keluar tidak valid atau token keamanan kedaluwarsa. Silakan gunakan tombol keluar resmi.');
            $this->redirect('/index.php');
        }

        AuthHelper::logout();
        if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
            @session_start();
            @session_regenerate_id(true);
        }
        $this->setFlash('success', 'Anda telah berhasil keluar dari sesi sistem.');
        $this->redirect('/login.php?logout=1');
    }

    /**
     * Direct user to appropriate profile management based on privileges.
     * Admin is redirected to the central admin panel, while regular operators
     * manage their personal credentials through a dedicated self-service profile page.
     */
    public function profile(): void {
        $this->requireLogin();

        if (AuthHelper::isAdmin()) {
            $this->redirect('/admin.php?tab=admin');
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $currentUser = AuthHelper::getCurrentUser();

        // Handle POST: Self-service password change for operational users
        if ($method === 'POST') {
            if (!$this->validateCsrf($_POST['csrf_token'] ?? null)) {
                $this->setFlash('error', 'Token keamanan CSRF tidak valid atau sesi Anda telah kedaluwarsa. Silakan muat ulang halaman.');
                $this->redirect('/profile.php');
            }

            $action = $_POST['action'] ?? '';
            if ($action === 'change_password') {
                $oldPass = (string)($_POST['old_password'] ?? '');
                $newPass = (string)($_POST['new_password'] ?? '');
                $confirmPass = (string)($_POST['confirm_password'] ?? '');

                if ($oldPass === '') {
                    $this->setFlash('error', 'Silakan masukkan kata sandi saat ini / sandi lama.');
                } elseif (strlen($newPass) < 8) {
                    $this->setFlash('error', 'Kata sandi baru minimal harus 8 karakter demi standar keamanan akun.');
                } elseif ($newPass !== $confirmPass) {
                    $this->setFlash('error', 'Konfirmasi kata sandi baru tidak cocok.');
                } else {
                    $role = $currentUser['role'] ?? 'USER';
                    $userId = $currentUser['id'] ?? null;
                    $res = SettingsModel::changePassword($role, $oldPass, $newPass, $userId);
                    if ($res['success']) {
                        $this->setFlash('success', 'Kata sandi akun Anda berhasil diperbarui! Gunakan sandi baru untuk login selanjutnya.');
                    } else {
                        $this->setFlash('error', $res['error'] ?? 'Gagal mengubah kata sandi: Kredensial lama tidak cocok.');
                    }
                }
                $this->redirect('/profile.php');
            }
        }

        // GET: Render dedicated profile view
        $this->render('auth/profile', [
            'currentUser' => $currentUser,
            'pageTitle' => 'Profil Pengguna & Keamanan Akun',
            'navbarTitle' => 'PROFIL PENGGUNA OPERASIONAL SAR',
            'navbarSubtitle' => 'INFORMASI AKUN & PENGATURAN KATA SANDI OPERATOR SIAGA',
            'currentRoute' => 'profile'
        ], [
            'layout' => 'default',
            'title' => 'Profil Pengguna - QR SAR',
            'route' => 'profile'
        ]);
    }
}
