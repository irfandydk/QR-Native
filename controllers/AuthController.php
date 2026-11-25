<?php
/**
 * QR-SAR - Authentication Controller
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Handles User Login, Admin 3x-Wrong-Password Secret Word Reset, Logout,
 * and Self-Service Profile (Avatar only for USER; password changes restricted to ADMIN).
 */

declare(strict_types=1);

class AuthController extends BaseController {
    /**
     * Handle login display, authentication submission, and Admin secret-word reset flow.
     */
    public function login(): void {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if (AuthHelper::isLoggedIn() && $method === 'GET') {
            $this->redirect('/index.php');
        }

        if ($method === 'GET' && !empty($_GET['cancel_admin_reset'])) {
            AuthHelper::clearAdminResetChallenge();
            $this->redirect('/login.php');
        }

        if ($method === 'POST') {
            if (!$this->validateCsrf($_POST['csrf_token'] ?? null)) {
                $this->setFlash('error', 'Token keamanan CSRF tidak valid atau sesi Anda telah kedaluwarsa. Silakan muat ulang halaman.');
                $this->redirect('/login.php');
            }

            $action = trim((string)($_POST['action'] ?? 'login'));

            // Alur Reset Password Admin dengan Secret Word (setelah 3x salah password)
            if ($action === 'admin_secret_reset') {
                $secretWord = (string)($_POST['secret_word'] ?? '');
                $resetRes = AuthHelper::executeAdminSecretReset($secretWord);
                if (!empty($resetRes['success'])) {
                    // Notifikasi sengaja TIDAK menampilkan isi password default
                    $this->setFlash('success', (string)$resetRes['message']);
                } else {
                    $this->setFlash('error', (string)($resetRes['error'] ?? 'Reset kata sandi gagal diproses.'));
                }
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

        // GET: render login view (termasuk state tantangan secret word Admin bila aktif)
        $this->render('auth/login', [
            'adminResetChallenge' => AuthHelper::getAdminResetChallenge(),
        ], ['layout' => 'none']);
    }

    /**
     * Invalidate user session and redirect to login page.
     */
    public function logout(): void {
        $token = $_POST['csrf_token'] ?? null;

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
     * Admin is redirected to central admin panel, while operational USERs can view
     * their identity and update their profile photo ONLY (password changes blocked for USER).
     */
    public function profile(): void {
        $this->requireLogin();

        if (AuthHelper::isAdmin()) {
            $this->redirect('/admin.php?tab=admin');
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $currentUser = AuthHelper::getCurrentUser();

        if ($method === 'POST') {
            if (!$this->validateCsrf($_POST['csrf_token'] ?? null)) {
                $this->setFlash('error', 'Token keamanan CSRF tidak valid atau sesi Anda telah kedaluwarsa. Silakan muat ulang halaman.');
                $this->redirect('/profile.php');
            }

            $action = trim((string)($_POST['action'] ?? ''));

            // Tolak keras upaya ubah password mandiri oleh akun USER
            if ($action === 'change_password') {
                $this->setFlash('error', 'Akses ditolak: Akun User tidak diizinkan mengubah kata sandi sendiri. Hubungi Administrator.');
                $this->redirect('/profile.php');
            }

            // Unggah / hapus foto profil mandiri untuk akun User
            if ($action === 'update_avatar') {
                $userId = (string)($currentUser['id'] ?? '');
                if (!empty($_POST['clear_avatar'])) {
                    $res = UserModel::updateAvatar($userId, '');
                    if (!empty($res['success'])) {
                        $this->setFlash('success', (string)$res['message']);
                    } else {
                        $this->setFlash('error', (string)($res['error'] ?? 'Gagal menghapus foto profil.'));
                    }
                    $this->redirect('/profile.php');
                }

                if (empty($_FILES['avatar_file']['name'])) {
                    $this->setFlash('error', 'Silakan pilih berkas foto terlebih dahulu.');
                    $this->redirect('/profile.php');
                }

                $up = UserModel::processAvatarUpload($_FILES['avatar_file']);
                if (empty($up['success'])) {
                    $this->setFlash('error', (string)($up['error'] ?? 'Gagal memproses foto profil.'));
                    $this->redirect('/profile.php');
                }

                $res = UserModel::updateAvatar($userId, (string)$up['path']);
                if (!empty($res['success'])) {
                    $this->setFlash('success', (string)$res['message']);
                } else {
                    UserModel::deleteAvatarFile((string)$up['path']);
                    $this->setFlash('error', (string)($res['error'] ?? 'Gagal memperbarui foto profil.'));
                }
                $this->redirect('/profile.php');
            }
        }

        // Refresh user row terbaru agar foto profil langsung up-to-date
        $freshRow = !empty($currentUser['id']) ? UserModel::getById((string)$currentUser['id']) : null;
        if ($freshRow) {
            $currentUser['avatar_url'] = (string)($freshRow['avatar_url'] ?? '');
        }

        $this->render('auth/profile', [
            'currentUser' => $currentUser,
            'pageTitle' => 'Profil Pengguna',
            'navbarTitle' => 'Profil Pengguna',
            'navbarSubtitle' => 'Informasi identitas kedinasan dan pengaturan foto profil akun',
            'currentRoute' => 'profile'
        ], [
            'layout' => 'default',
            'title' => 'Profil Pengguna - QR SAR',
            'route' => 'profile'
        ]);
    }
}
