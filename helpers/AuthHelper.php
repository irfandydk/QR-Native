<?php
/**
 * QR-SAR - Authentication & Security Helper
 * KPP Kendari - Basarnas
 */

declare(strict_types=1);

class AuthHelper {
    /** Umur mutlak sesi sejak login (detik), terlepas dari aktivitas */
    private const SESSION_MAX_AGE = 43200;

    /** Cache verifikasi sesi per HTTP request agar tidak query Users berulang kali */
    private static ?string $verifiedSessionKey = null;

    /**
     * Sesi sah hanya bila akun masih aktif, sandinya belum berubah sejak login (sidik kredensial cocok)
     * dan belum melewati umur mutlak. Sesi yang tidak sah dibersihkan.
     */
    public static function isLoggedIn(): bool {
        if (empty($_SESSION['user_id'])) {
            self::$verifiedSessionKey = null;
            return false;
        }
        if (!class_exists('UserModel')) {
            return true;
        }
        $sessKey = (string)$_SESSION['user_id'] . '|' . (string)($_SESSION['cred_fp'] ?? '') . '|' . (string)($_SESSION['auth_at'] ?? 0);
        if (PHP_SAPI !== 'cli' && !empty($_SESSION['cred_fp']) && self::$verifiedSessionKey === $sessKey) {
            return true;
        }
        try {
            $row = UserModel::getById((string)$_SESSION['user_id']);
        } catch (Throwable $e) {
            self::$verifiedSessionKey = null;
            return false;
        }
        $fp = $row ? hash('sha256', (string)($row['password_hash'] ?? '')) : '';
        if ($row && empty($_SESSION['cred_fp'])) {
            // Sesi dari sebelum fitur ini: adopsi sidik kredensial saat ini
            $_SESSION['cred_fp'] = $fp;
            $_SESSION['auth_at'] = $_SESSION['auth_at'] ?? time();
        }
        $ok = $row && !empty($row['is_active'])
            && hash_equals($fp, (string)($_SESSION['cred_fp'] ?? ''))
            && (time() - (int)($_SESSION['auth_at'] ?? 0)) < self::SESSION_MAX_AGE;
        if (!$ok) {
            self::$verifiedSessionKey = null;
            $_SESSION = [];
            return false;
        }
        // Sandi bawaan dicek sekali per perubahan kredensial (password_verify mahal)
        if (($_SESSION['pw_check_fp'] ?? '') !== $fp) {
            $_SESSION['must_change_password'] = defined('DEFAULT_RESET_PASSWORD')
                && password_verify(DEFAULT_RESET_PASSWORD, (string)($row['password_hash'] ?? ''));
            $_SESSION['pw_check_fp'] = $fp;
        }
        if (PHP_SAPI !== 'cli') {
            self::$verifiedSessionKey = (string)$_SESSION['user_id'] . '|' . (string)($_SESSION['cred_fp'] ?? '') . '|' . (string)($_SESSION['auth_at'] ?? 0);
        }
        return true;
    }

    /**
     * Nama skrip halaman tanpa .php dari URL permintaan, untuk gerbang wajib-ganti-sandi.
     * Permintaan ber-PATH_INFO (/operasi.php/profile) dapat nama kosong agar tidak menyamar sebagai halaman pengecualian.
     */
    public static function currentPageName(array $server): string {
        if (($server['PATH_INFO'] ?? '') !== '') {
            return '';
        }
        return preg_replace('/\.php$/', '', basename((string)parse_url((string)($server['REQUEST_URI'] ?? ''), PHP_URL_PATH)));
    }

    /** Akun masih memakai sandi bawaan dan wajib menggantinya sebelum memakai aplikasi */
    public static function mustChangePassword(): bool {
        return self::isLoggedIn() && !empty($_SESSION['must_change_password']);
    }

    /**
     * Halaman tujuan ganti sandi bila permintaan ini tidak termasuk alur ganti sandi, null bila boleh lanjut.
     * $page: nama skrip tanpa .php (mis. 'profile', 'admin').
     */
    public static function passwordChangeRedirect(string $role, string $page, array $get, string $method, array $post): ?string {
        if (in_array($page, ['logout', 'login'], true)) {
            return null;
        }
        if (strtoupper($role) === 'ADMIN') {
            $isChangeForm = $page === 'admin' && (
                (strtoupper($method) === 'GET' && ($get['tab'] ?? 'admin') === 'admin' && empty($get['action']))
                || (strtoupper($method) === 'POST' && ($post['action'] ?? ($get['action'] ?? '')) === 'change_admin_pass')
            );
            return $isChangeForm ? null : BASE_URL . '/admin.php?tab=admin';
        }
        return $page === 'profile' ? null : BASE_URL . '/profile.php';
    }

    /** Sandi akun sesi ini baru saja diganti oleh pemiliknya: perbarui sidik agar sesi ini tetap sah */
    public static function refreshCredentialFingerprint(string $userId, string $newHash): void {
        self::$verifiedSessionKey = null;
        if (($_SESSION['user_id'] ?? null) === $userId) {
            $_SESSION['cred_fp'] = hash('sha256', $newHash);
        }
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
        // Sandi bawaan wajib diganti dulu (CLI dikecualikan: tidak ada halaman tujuan)
        if (PHP_SAPI !== 'cli' && self::mustChangePassword()) {
            $page = self::currentPageName($_SERVER);
            $to = self::passwordChangeRedirect((string)($_SESSION['role'] ?? 'USER'), $page, $_GET, (string)($_SERVER['REQUEST_METHOD'] ?? 'GET'), $_POST);
            if ($to !== null) {
                if (function_exists('setFlash')) {
                    setFlash('warning', 'Akun Anda masih memakai kata sandi bawaan. Ganti kata sandi terlebih dahulu untuk melanjutkan.');
                }
                header('Location: ' . $to);
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
        // Hanya hash modern (bcrypt/argon2); sandi plaintext lama di-hash oleh database/migrate.php
        return password_verify($inputPassword, $storedPassword);
    }

    public static function getClientIp(): string {
        // Header CF-Connecting-IP / X-Forwarded-For bisa diisi bebas oleh klien, jadi tidak dipakai untuk throttle
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '127.0.0.1';
    }

    public static function checkLoginThrottle(string $username = ''): ?string {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $now = time();
        $ip = self::getClientIp();
        $cleanUser = trim($username);

        // 1. Persistent IP + Username Database Throttling (tanpa DDL runtime; tabel dibuat oleh migrasi)
        try {
            $db = getDB();

            // Clean expired throttle entries (> 15 minutes)
            $cutoff = $now - 900;
            $db->prepare("DELETE FROM LoginThrottle WHERE attempt_time < :cutoff")->execute([':cutoff' => $cutoff]);

            $fiveMin = $now - 300;
            if ($cleanUser !== '') {
                // Kunci per kombinasi (IP, username) pada 5 gagal, atau banjir global per IP pada 15 gagal
                $stmtUser = $db->prepare("SELECT COUNT(*), MAX(attempt_time) FROM LoginThrottle WHERE ip_address = :ip AND LOWER(username) = LOWER(:u) AND attempt_time >= :time");
                $stmtUser->execute([':ip' => $ip, ':u' => $cleanUser, ':time' => $fiveMin]);
                [$userFailed, $userLast] = array_map('intval', $stmtUser->fetch(PDO::FETCH_NUM) ?: [0, 0]);
                if ($userFailed >= 5) {
                    $rem = max(1, 300 - ($now - $userLast));
                    return "Terlalu banyak percobaan login gagal untuk akun tersebut. Demi keamanan sistem, silakan tunggu {$rem} detik sebelum mencoba kembali.";
                }

                $stmtIp = $db->prepare("SELECT COUNT(*), MAX(attempt_time) FROM LoginThrottle WHERE ip_address = :ip AND attempt_time >= :time");
                $stmtIp->execute([':ip' => $ip, ':time' => $fiveMin]);
                [$ipFailed, $ipLast] = array_map('intval', $stmtIp->fetch(PDO::FETCH_NUM) ?: [0, 0]);
                if ($ipFailed >= 15) {
                    $rem = max(1, 300 - ($now - $ipLast));
                    return "Terlalu banyak percobaan login gagal dari IP Anda. Demi keamanan sistem, silakan tunggu {$rem} detik sebelum mencoba kembali.";
                }
            } else {
                $stmt = $db->prepare("SELECT COUNT(*), MAX(attempt_time) FROM LoginThrottle WHERE ip_address = :ip AND attempt_time >= :time");
                $stmt->execute([':ip' => $ip, ':time' => $fiveMin]);
                [$failedCount, $lastTime] = array_map('intval', $stmt->fetch(PDO::FETCH_NUM) ?: [0, 0]);
                if ($failedCount >= 5) {
                    $rem = max(1, 300 - ($now - $lastTime));
                    return "Terlalu banyak percobaan login gagal dari IP Anda. Demi keamanan sistem, silakan tunggu {$rem} detik sebelum mencoba kembali.";
                }
            }
        } catch (Throwable $e) {
            // Graceful fallback to session-based throttle if database table unavailable
            $lockUntil = (int)($_SESSION['login_lock_until'] ?? 0);
            if ($lockUntil > $now) {
                $rem = $lockUntil - $now;
                return "Terlalu banyak percobaan login gagal. Demi keamanan, silakan tunggu {$rem} detik sebelum mencoba kembali.";
            }
            return null;
        }

        // 2. Fallback Session-based Throttling (hanya bila panggilan tanpa filter username spesifik)
        if ($cleanUser === '') {
            $lockUntil = (int)($_SESSION['login_lock_until'] ?? 0);
            if ($lockUntil > $now) {
                $rem = $lockUntil - $now;
                return "Terlalu banyak percobaan login gagal. Demi keamanan, silakan tunggu {$rem} detik sebelum mencoba kembali.";
            }
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
            $stmt->execute([':ip' => $ip, ':t' => $now, ':u' => substr(trim($username), 0, 100)]);
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

    public static function resetLoginThrottle(?string $username = null): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $ip = self::getClientIp();
        try {
            $db = getDB();
            if ($username !== null && trim($username) !== '') {
                $stmt = $db->prepare("DELETE FROM LoginThrottle WHERE ip_address = :ip AND LOWER(username) = LOWER(:u)");
                $stmt->execute([':ip' => $ip, ':u' => trim($username)]);
            } else {
                $stmt = $db->prepare("DELETE FROM LoginThrottle WHERE ip_address = :ip");
                $stmt->execute([':ip' => $ip]);
            }
        } catch (Throwable $e) {}

        unset($_SESSION['login_failed_attempts'], $_SESSION['login_lock_until']);
    }

    public static function login(string $username, string $password): array {
        $cleanUser = trim($username);
        $cleanPass = trim($password);

        // Brute-force throttle check (terikat pada IP + username sasaran)
        $throttleErr = self::checkLoginThrottle($cleanUser);
        if ($throttleErr !== null) {
            return [
                'success' => false,
                'error' => $throttleErr,
                'message' => $throttleErr
            ];
        }

        // Tabel Users adalah satu-satunya sumber kredensial (AppSettings disinkronkan oleh migrate.php)
        if (class_exists('UserModel')) {
            $dbUser = UserModel::getByUsername($cleanUser);
            if ($dbUser && !empty($dbUser['is_active']) && self::verifyPassword($cleanPass, (string)$dbUser['password_hash'])) {
                self::resetLoginThrottle($cleanUser);
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
        if (preg_match('/[\r\n\0]/', $url)) {
            return $default;
        }
        // Tolak URL berbasis protocol-relative (//evil.com atau /\evil.com)
        if (str_starts_with($url, '//') || str_starts_with($url, '/\\')) {
            return $default;
        }
        // Tolak skema semu javascript:, data:, vbscript:
        if (preg_match('#^(javascript|data|vbscript):#i', $url)) {
            return $default;
        }
        // Jika URL absolut HTTP/HTTPS, wajib sama host/port dan berbatas tepat pada BASE_URL
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            $base = rtrim((string)BASE_URL, '/');
            if ($base === '') {
                return $default;
            }
            $validPrefix = ($url === $base)
                || str_starts_with($url, $base . '/')
                || str_starts_with($url, $base . '?')
                || str_starts_with($url, $base . '#');
            if (!$validPrefix) {
                return $default;
            }
            $uParts = parse_url($url);
            $bParts = parse_url($base);
            if (
                !is_array($uParts) || !is_array($bParts)
                || isset($uParts['user']) || isset($uParts['pass'])
                || strtolower((string)($uParts['host'] ?? '')) !== strtolower((string)($bParts['host'] ?? ''))
                || (int)($uParts['port'] ?? 0) !== (int)($bParts['port'] ?? 0)
            ) {
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
        self::$verifiedSessionKey = null;
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
        $_SESSION['auth_at'] = time();
        $row = class_exists('UserModel') && !empty($user['id']) ? UserModel::getById((string)$user['id']) : null;
        $_SESSION['cred_fp'] = $row ? hash('sha256', (string)($row['password_hash'] ?? '')) : '';
    }

    public static function logout(): void {
        self::$verifiedSessionKey = null;
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

    public static function validateCsrf(?string $token): bool {
        return !empty($_SESSION['csrf_token']) && !empty($token) && hash_equals($_SESSION['csrf_token'], $token);
    }
}
