<?php
/**
 * QR-SAR - Authentication & Security Helper
 * KPP Kendari - Basarnas
 */

declare(strict_types=1);

class AuthHelper {
    public static function isLoggedIn(): bool {
        return !empty($_SESSION['user_id']);
    }

    public static function requireLogin(): void {
        if (!self::isLoggedIn()) {
            // If user session does not exist, initialize or redirect
            if (empty($_SESSION['user'])) {
                header("Location: " . BASE_URL . "/login.php");
                exit;
            }
        }
    }

    public static function requireAdmin(): void {
        self::requireLogin();
        if (!self::isAdmin()) {
            if (function_exists('setFlash')) {
                setFlash('error', 'Akses ditolak: Halaman Pengaturan Admin hanya dapat diakses oleh Administrator.');
            }
            header("Location: " . BASE_URL . "/index.php");
            exit;
        }
    }

    public static function getCurrentUser(): array {
        if (!self::isLoggedIn()) {
            return [
                'id' => null,
                'username' => 'guest',
                'nama' => 'Tamu / Belum Login',
                'nip' => '-',
                'role' => 'GUEST',
                'roleTitle' => 'Pengunjung',
                'pangkat' => '-',
                'jabatan' => 'Tamu',
                'email' => '',
                'no_hp' => '',
                'loginAt' => null
            ];
        }

        return $_SESSION['user'] ?? [
            'id' => $_SESSION['user_id'] ?? 'USR-USER',
            'username' => $_SESSION['username'] ?? 'user',
            'nama' => $_SESSION['nama'] ?? 'Petugas SAR',
            'nip' => $_SESSION['nip'] ?? '-',
            'role' => $_SESSION['role'] ?? 'USER',
            'roleTitle' => $_SESSION['role_title'] ?? 'Operator Siaga SAR',
            'pangkat' => $_SESSION['pangkat'] ?? '-',
            'jabatan' => $_SESSION['jabatan'] ?? 'Petugas Siaga SAR',
            'email' => $_SESSION['email'] ?? '',
            'no_hp' => $_SESSION['no_hp'] ?? '',
            'loginAt' => $_SESSION['login_at'] ?? date('Y-m-d H:i:s')
        ];
    }

    public static function isAdmin(): bool {
        if (!self::isLoggedIn()) {
            return false;
        }
        $user = self::getCurrentUser();
        return strtoupper((string)($user['role'] ?? '')) === 'ADMIN';
    }

    public static function canDelete(string $category = ''): bool {
        if (!self::isLoggedIn()) {
            return false;
        }
        if (self::isAdmin()) return true;
        // Normal user can only delete on GIS / Map plotting
        return strtolower($category) === 'gis';
    }

    public static function verifyPassword(string $inputPassword, string $storedPassword): bool {
        if ($storedPassword === '' || $inputPassword === '') return false;
        // 1. If stored as modern password_hash (bcrypt/argon2)
        $info = password_get_info($storedPassword);
        if (!empty($info['algo'])) {
            return password_verify($inputPassword, $storedPassword);
        }
        // 2. Fallback check for legacy plaintext password
        return hash_equals($storedPassword, $inputPassword);
    }

    public static function login(string $username, string $password): array {
        $db = getDB();
        $stmt = $db->query("SELECT * FROM AppSettings WHERE id = 1 LIMIT 1");
        $settings = $stmt->fetch() ?: [];

        $adminUser = $settings['admin_username'] ?? 'admin';
        $adminPass = (string)($settings['admin_password'] ?? 'Kendari304');
        $userUser = $settings['user_username'] ?? 'user';
        $userPass = (string)($settings['user_password'] ?? 'Kendari304');

        $cleanUser = trim($username);
        $cleanPass = trim($password);

        // Check ADMIN
        if (strcasecmp($cleanUser, $adminUser) === 0 && self::verifyPassword($cleanPass, $adminPass)) {
            // Auto-upgrade plaintext to password_hash if not already hashed
            $info = password_get_info($adminPass);
            if (empty($info['algo'])) {
                try {
                    $newHash = password_hash($cleanPass, PASSWORD_DEFAULT);
                    $upStmt = $db->prepare("UPDATE AppSettings SET admin_password = :p WHERE id = 1");
                    $upStmt->execute([':p' => $newHash]);
                } catch (Throwable $e) {}
            }

            $user = [
                'id' => 'USR-ADMIN',
                'username' => $adminUser,
                'nama' => $settings['admin_nama'] ?? 'AMIRUDDIN AS, S.Sos.',
                'nip' => $settings['admin_nip'] ?? '197405101998031002',
                'role' => 'ADMIN',
                'roleTitle' => 'Administrator Utama SAR',
                'pangkat' => $settings['admin_pangkat'] ?? 'PEMBINA (IV/a)',
                'jabatan' => $settings['admin_jabatan'] ?? 'Kepala Kantor KPP Kendari',
                'email' => $settings['admin_email'] ?? 'irfandydk@gmail.com',
                'no_hp' => $settings['admin_no_hp'] ?? '0811-4091-115',
                'loginAt' => date('Y-m-d H:i:s')
            ];
            self::setSession($user);
            return ['success' => true, 'user' => $user];
        }

        // Check OPERATOR / USER
        if (strcasecmp($cleanUser, $userUser) === 0 && self::verifyPassword($cleanPass, $userPass)) {
            // Auto-upgrade plaintext to password_hash if not already hashed
            $info = password_get_info($userPass);
            if (empty($info['algo'])) {
                try {
                    $newHash = password_hash($cleanPass, PASSWORD_DEFAULT);
                    $upStmt = $db->prepare("UPDATE AppSettings SET user_password = :p WHERE id = 1");
                    $upStmt->execute([':p' => $newHash]);
                } catch (Throwable $e) {}
            }

            $user = [
                'id' => 'USR-PETUGAS',
                'username' => $userUser,
                'nama' => $settings['user_nama'] ?? 'Petugas Siaga SAR',
                'nip' => $settings['user_nip'] ?? '199501012020121001',
                'role' => 'USER',
                'roleTitle' => 'Operator Siaga SAR & Senkom',
                'pangkat' => $settings['user_pangkat'] ?? 'PENGATUR (II/c)',
                'jabatan' => $settings['user_jabatan'] ?? 'Operator Komunikasi / Senkom KPP Kendari',
                'email' => $settings['user_email'] ?? 'operator.kendari@basarnas.go.id',
                'no_hp' => $settings['user_no_hp'] ?? '0811-4091-115',
                'loginAt' => date('Y-m-d H:i:s')
            ];
            self::setSession($user);
            return ['success' => true, 'user' => $user];
        }

        return [
            'success' => false,
            'error' => 'Nama pengguna atau kata sandi yang Anda masukkan salah. Pastikan menggunakan kredensial yang tepat.'
        ];
    }

    public static function setSession(array $user): void {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['nama'] = $user['nama'];
        $_SESSION['nip'] = $user['nip'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['role_title'] = $user['roleTitle'];
        $_SESSION['pangkat'] = $user['pangkat'];
        $_SESSION['jabatan'] = $user['jabatan'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['no_hp'] = $user['no_hp'];
        $_SESSION['login_at'] = $user['loginAt'];
        $_SESSION['user'] = $user;
    }

    public static function logout(): void {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }

    public static function csrfToken(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function validateCsrf(string $token): bool {
        return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}
