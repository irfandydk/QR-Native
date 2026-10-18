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
                $redirect = $_SESSION['redirect_url'] ?? (BASE_URL . '/index.php');
                unset($_SESSION['redirect_url']);

                // Sanity check against open redirect vulnerabilities
                if (str_starts_with($redirect, 'http') && !str_starts_with($redirect, BASE_URL)) {
                    $redirect = BASE_URL . '/index.php';
                }
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
        $this->setFlash('success', 'Anda telah berhasil keluar dari sesi sistem.');
        $this->redirect('/login.php');
    }

    /**
     * Direct user to appropriate profile management based on privileges.
     */
    public function profile(): void {
        $this->requireLogin();

        if (AuthHelper::isAdmin()) {
            $this->redirect('/admin.php?tab=admin');
        }

        $this->redirect('/master.php?tab=alut');
    }
}
