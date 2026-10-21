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
            if (session_status() !== PHP_SESSION_ACTIVE) {
                @session_start();
            }
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && !empty($_SERVER['REQUEST_URI'])) {
                $uri = (string)$_SERVER['REQUEST_URI'];
                if (!str_contains($uri, 'login.php')) {
                    $_SESSION['redirect_url'] = self::sanitizeRedirectUrl($uri, BASE_URL . '/index.php');
                }
            }
            if (function_exists('setFlash')) {
                setFlash('error', 'Sesi login diperlukan untuk mengakses sistem ini.');
            }
            header("Location: " . BASE_URL . "/login.php");
            exit;
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

    public static function getClientIp(): string {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $rawFwd = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
            if (!empty($rawFwd)) {
                $candidates = explode(',', $rawFwd);
                foreach ($candidates as $c) {
                    $c = trim($c);
                    if (filter_var($c, FILTER_VALIDATE_IP)) {
                        return $c;
                    }
                }
            }
            return '127.0.0.1';
        }
        return $ip;
    }

    public static function checkLoginThrottle(): ?string {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $now = time();
        $ip = self::getClientIp();

        // 1. Persistent IP-based Database Throttling
        try {
            $db = getDB();
            $db->exec("CREATE TABLE IF NOT EXISTS LoginThrottle (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ip_address VARCHAR(45) NOT NULL,
                attempt_time INT NOT NULL,
                username VARCHAR(100) DEFAULT '',
                KEY idx_ip_time (ip_address, attempt_time)
            ) ENGINE=InnoDB");

            // Clean expired throttle entries (> 15 minutes)
            $cutoff = $now - 900;
            $db->prepare("DELETE FROM LoginThrottle WHERE attempt_time < :cutoff")->execute([':cutoff' => $cutoff]);

            // Count attempts within last 5 minutes (300s)
            $fiveMin = $now - 300;
            $stmt = $db->prepare("SELECT COUNT(*) FROM LoginThrottle WHERE ip_address = :ip AND attempt_time >= :time");
            $stmt->execute([':ip' => $ip, ':time' => $fiveMin]);
            $failedCount = (int)$stmt->fetchColumn();

            if ($failedCount >= 5) {
                $stmtLast = $db->prepare("SELECT MAX(attempt_time) FROM LoginThrottle WHERE ip_address = :ip");
                $stmtLast->execute([':ip' => $ip]);
                $lastTime = (int)$stmtLast->fetchColumn();
                $rem = max(1, 300 - ($now - $lastTime));
                return "Terlalu banyak percobaan login gagal dari IP Anda. Demi keamanan sistem, silakan tunggu {$rem} detik sebelum mencoba kembali.";
            }
        } catch (Throwable $e) {
            // Graceful fallback to session-based throttle if database table unavailable
        }

        // 2. Fallback Session-based Throttling
        $lockUntil = (int)($_SESSION['login_lock_until'] ?? 0);
        if ($lockUntil > $now) {
            $rem = $lockUntil - $now;
            return "Terlalu banyak percobaan login gagal. Demi keamanan, silakan tunggu {$rem} detik sebelum mencoba kembali.";
        }
        return null;
    }

    public static function recordFailedLogin(string $username = ''): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $now = time();
        $ip = self::getClientIp();

        // Record to Persistent Database Throttle
        try {
            $db = getDB();
            $stmt = $db->prepare("INSERT INTO LoginThrottle (ip_address, attempt_time, username) VALUES (:ip, :t, :u)");
            $stmt->execute([':ip' => $ip, ':t' => $now, ':u' => substr($username, 0, 100)]);
        } catch (Throwable $e) {}

        // Record to session fallback
        $attempts = $_SESSION['login_failed_attempts'] ?? [];
        if (!is_array($attempts)) $attempts = [];
        $attempts = array_filter($attempts, fn($t) => is_numeric($t) && ($now - (int)$t) < 300);
        $attempts[] = $now;
        $_SESSION['login_failed_attempts'] = array_values($attempts);

        if (count($_SESSION['login_failed_attempts']) >= 5) {
            $_SESSION['login_lock_until'] = $now + 300;
        }
    }

    public static function resetLoginThrottle(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $ip = self::getClientIp();
        try {
            $db = getDB();
            $stmt = $db->prepare("DELETE FROM LoginThrottle WHERE ip_address = :ip");
            $stmt->execute([':ip' => $ip]);
        } catch (Throwable $e) {}

        unset($_SESSION['login_failed_attempts'], $_SESSION['login_lock_until']);
    }

    public static function login(string $username, string $password): array {
        // Brute-force throttle check
        $throttleErr = self::checkLoginThrottle();
        if ($throttleErr !== null) {
            return [
                'success' => false,
                'error' => $throttleErr,
                'message' => $throttleErr
            ];
        }

        // Auto-sync dynamic Users table
        if (class_exists('UserModel')) {
            UserModel::syncFromAppSettings();
        }

        $cleanUser = trim($username);
        $cleanPass = trim($password);

        // 1. Check dynamic Users table first (Unified User Management)
        if (class_exists('UserModel')) {
            UserModel::ensureTableExists();
            $dbUser = UserModel::getByUsername($cleanUser);
            if ($dbUser && !empty($dbUser['is_active']) && self::verifyPassword($cleanPass, (string)$dbUser['password_hash'])) {
                self::resetLoginThrottle();
                UserModel::updateLastLogin((string)$dbUser['id']);
                $roleUpper = strtoupper((string)($dbUser['role'] ?? 'USER'));
                $user = [
                    'id' => $dbUser['id'],
                    'username' => $dbUser['username'],
                    'nama' => $dbUser['nama'] ?: ($roleUpper === 'ADMIN' ? 'Administrator SAR' : 'Petugas Siaga SAR'),
                    'nip' => $dbUser['nip'] ?: '-',
                    'role' => $roleUpper,
                    'roleTitle' => $roleUpper === 'ADMIN' ? 'Administrator Utama SAR' : 'Operator Siaga SAR & Senkom',
                    'pangkat' => $dbUser['pangkat'] ?: '-',
                    'jabatan' => $dbUser['jabatan'] ?: ($roleUpper === 'ADMIN' ? 'Kepala Kantor KPP Kendari' : 'Petugas Siaga SAR'),
                    'email' => $dbUser['email'] ?: '',
                    'no_hp' => $dbUser['no_hp'] ?: '',
                    'loginAt' => date('Y-m-d H:i:s')
                ];
                self::setSession($user);

                if (class_exists('AuditHelper')) {
                    AuditHelper::log('AUTH_LOGIN_SUCCESS', "Pengguna {$user['nama']} ({$cleanUser}) berhasil masuk ke sistem.", 'AUTH', $user['id']);
                }

                return ['success' => true, 'user' => $user];
            }
        }

        // 2. Fallback check AppSettings row (Legacy/Direct Settings Compatibility)
        $db = getDB();
        $stmt = $db->query("SELECT * FROM AppSettings WHERE id = 1 LIMIT 1");
        $settings = $stmt->fetch() ?: [];

        $adminUser = $settings['admin_username'] ?? 'admin';
        $adminPass = (string)($settings['admin_password'] ?? '');
        $userUser = $settings['user_username'] ?? 'user';
        $userPass = (string)($settings['user_password'] ?? '');

        // Check ADMIN
        if (strcasecmp($cleanUser, $adminUser) === 0 && self::verifyPassword($cleanPass, $adminPass)) {
            self::resetLoginThrottle();

            // Auto-upgrade plaintext to password_hash if not already hashed
            $info = password_get_info($adminPass);
            if (empty($info['algo'])) {
                try {
                    $newHash = password_hash($cleanPass, PASSWORD_DEFAULT);
                    $upStmt = $db->prepare("UPDATE AppSettings SET admin_password = :p WHERE id = 1");
                    $upStmt->execute([':p' => $newHash]);
                    if (class_exists('UserModel')) {
                        UserModel::updatePassword('USR-ADMIN', $cleanPass);
                    }
                } catch (Throwable $e) {}
            }

            if (class_exists('UserModel')) {
                UserModel::updateLastLogin('USR-ADMIN');
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

            if (class_exists('AuditHelper')) {
                AuditHelper::log('AUTH_LOGIN_SUCCESS', "Administrator {$user['nama']} ({$cleanUser}) berhasil masuk ke sistem.", 'AUTH', $user['id']);
            }

            return ['success' => true, 'user' => $user];
        }

        // Check OPERATOR / USER
        if (strcasecmp($cleanUser, $userUser) === 0 && self::verifyPassword($cleanPass, $userPass)) {
            self::resetLoginThrottle();

            // Auto-upgrade plaintext to password_hash if not already hashed
            $info = password_get_info($userPass);
            if (empty($info['algo'])) {
                try {
                    $newHash = password_hash($cleanPass, PASSWORD_DEFAULT);
                    $upStmt = $db->prepare("UPDATE AppSettings SET user_password = :p WHERE id = 1");
                    $upStmt->execute([':p' => $newHash]);
                    if (class_exists('UserModel')) {
                        UserModel::updatePassword('USR-USER', $cleanPass);
                    }
                } catch (Throwable $e) {}
            }

            if (class_exists('UserModel')) {
                UserModel::updateLastLogin('USR-USER');
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

            if (class_exists('AuditHelper')) {
                AuditHelper::log('AUTH_LOGIN_SUCCESS', "Petugas Siaga {$user['nama']} ({$cleanUser}) berhasil masuk ke sistem.", 'AUTH', $user['id']);
            }

            return ['success' => true, 'user' => $user];
        }

        // Rekam kegagalan login
        self::recordFailedLogin($cleanUser);

        if (class_exists('AuditHelper')) {
            AuditHelper::log('AUTH_LOGIN_FAILED', "Percobaan autentikasi gagal dengan nama pengguna: {$cleanUser}", 'AUTH', $cleanUser);
        }

        return [
            'success' => false,
            'error' => 'Nama pengguna atau kata sandi yang Anda masukkan salah. Pastikan menggunakan kredensial yang tepat.',
            'message' => 'Nama pengguna atau kata sandi yang Anda masukkan salah. Pastikan menggunakan kredensial yang tepat.'
        ];
    }

    public static function sanitizeRedirectUrl(?string $url, string $default = ''): string {
        if (empty($default)) {
            $default = BASE_URL . '/index.php';
        }
        if (empty($url)) {
            return $default;
        }
        $url = trim($url);
        // Tolak URL berbasis protocol-relative (//evil.com atau /\evil.com)
        if (str_starts_with($url, '//') || str_starts_with($url, '/\\')) {
            return $default;
        }
        // Tolak skema semu javascript:, data:, vbscript:
        if (preg_match('#^(javascript|data|vbscript):#i', $url)) {
            return $default;
        }
        // Jika URL absolut HTTP/HTTPS, wajib diawali dengan BASE_URL
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            if (!str_starts_with($url, BASE_URL)) {
                return $default;
            }
            return $url;
        }
        // Path relatif internal harus berawalan slash tunggal
        if (str_starts_with($url, '/')) {
            return $url;
        }
        return $default;
    }

    public static function setSession(array $user): void {
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            @session_regenerate_id(true);
        }
        $_SESSION['user_id'] = $user['id'] ?? null;
        $_SESSION['username'] = $user['username'] ?? 'system';
        $_SESSION['nama'] = $user['nama'] ?? 'Petugas SAR';
        $_SESSION['nip'] = $user['nip'] ?? '-';
        $_SESSION['role'] = $user['role'] ?? 'USER';
        $_SESSION['role_title'] = $user['roleTitle'] ?? ($user['role_title'] ?? 'Petugas SAR');
        $_SESSION['pangkat'] = $user['pangkat'] ?? '-';
        $_SESSION['jabatan'] = $user['jabatan'] ?? 'Petugas SAR';
        $_SESSION['email'] = $user['email'] ?? '';
        $_SESSION['no_hp'] = $user['no_hp'] ?? '';
        $_SESSION['login_at'] = $user['loginAt'] ?? ($user['login_at'] ?? date('Y-m-d H:i:s'));
        $_SESSION['user'] = $user;
    }

    public static function logout(): void {
        if (class_exists('AuditHelper') && !empty($_SESSION['username'])) {
            AuditHelper::log('AUTH_LOGOUT', "Pengguna {$_SESSION['username']} keluar dari sesi sistem.", 'AUTH', $_SESSION['user_id'] ?? null);
        }
        $_SESSION = [];
        if (ini_get("session.use_cookies") && !headers_sent()) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_destroy();
        }
    }

    public static function csrfToken(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function getCsrfToken(): string {
        return self::csrfToken();
    }

    public static function validateCsrf(?string $token): bool {
        return !empty($_SESSION['csrf_token']) && !empty($token) && hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function verifyCsrf(?string $token): bool {
        return self::validateCsrf($token);
    }
}
