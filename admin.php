<?php
/**
 * QR-SAR - Pengaturan Admin & Konfigurasi Satker
 * KPP Kendari - Basarnas
 * Sub-Menu: 1. Profil & Sandi Admin, 2. Akun Pengguna (User), 3. Identitas Satker & Kop, 4. Backup & Restore Database
 * 100% Faithful to Reference AdminSettingsPage.tsx
 */

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

AuthHelper::requireAdmin();

$action = $_POST['action'] ?? ($_GET['action'] ?? null);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$tab = $_GET['tab'] ?? 'admin';
if (!in_array($tab, ['admin', 'user', 'satker', 'backup', 'logs'], true)) {
    $tab = 'admin';
}

// 1. Handle Export Actions (Direct SQL Download Only)
if ($action === 'export_sql') {
    $sqlDump = SettingsModel::generateSQLDump();
    $filename = 'DUMP_DATABASE_SAR_KENDARI_' . date('Y-m-d_His') . '.sql';
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo $sqlDump;
    exit;
}

// 2. Handle POST Actions
if ($method === 'POST') {
    if (!validateCsrf($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Token keamanan CSRF tidak valid atau sesi Anda telah kedaluwarsa. Silakan muat ulang halaman.');
        header("Location: " . BASE_URL . "/admin.php?tab=" . urlencode($tab));
        exit;
    }

    // A. Simpan Profil Administrator
    if ($action === 'save_admin') {
        $nama = trim($_POST['admin_nama'] ?? '');
        $username = trim($_POST['admin_username'] ?? 'admin');
        if ($nama === '' || $username === '') {
            setFlash('error', 'Nama lengkap dan Username Administrator wajib diisi.');
        } else {
            $data = [
                'nama' => $nama,
                'username' => $username,
                'nip' => trim($_POST['admin_nip'] ?? ''),
                'pangkat' => trim($_POST['admin_pangkat'] ?? ''),
                'jabatan' => trim($_POST['admin_jabatan'] ?? ''),
                'email' => trim($_POST['admin_email'] ?? ''),
                'no_hp' => trim($_POST['admin_no_hp'] ?? '')
            ];
            SettingsModel::updateAdminProfile($data);

            // Sync current session if role is admin
            if (isset($_SESSION['user']) && ($_SESSION['user']['role'] ?? '') === 'ADMIN') {
                $_SESSION['user']['nama'] = $data['nama'];
                $_SESSION['user']['username'] = $data['username'];
                $_SESSION['user']['nip'] = $data['nip'];
                $_SESSION['user']['pangkat'] = $data['pangkat'];
                $_SESSION['user']['jabatan'] = $data['jabatan'];
                $_SESSION['user']['email'] = $data['email'];
                $_SESSION['user']['no_hp'] = $data['no_hp'];
                $_SESSION['nama'] = $data['nama'];
                $_SESSION['username'] = $data['username'];
            }

            setFlash('success', 'Profil Administrator dan kontak dinas berhasil diperbarui.');
        }
        header("Location: " . BASE_URL . "/admin.php?tab=admin");
        exit;
    }

    // B. Ubah Kata Sandi Administrator
    if ($action === 'change_admin_pass') {
        $oldPass = trim($_POST['old_password'] ?? '');
        $newPass = trim($_POST['new_password'] ?? '');
        $confirmPass = trim($_POST['confirm_password'] ?? '');

        if ($oldPass === '') {
            setFlash('error', 'Silakan masukkan kata sandi saat ini / sandi lama.');
        } elseif (strlen($newPass) < 8) {
            setFlash('error', 'Kata sandi baru minimal harus 8 karakter demi keamanan sistem.');
        } elseif ($newPass !== $confirmPass) {
            setFlash('error', 'Konfirmasi kata sandi baru tidak cocok.');
        } else {
            $res = SettingsModel::changePassword('ADMIN', $oldPass, $newPass);
            if ($res['success']) {
                setFlash('success', 'Kata sandi Administrator berhasil diubah. Gunakan sandi baru untuk login selanjutnya.');
            } else {
                setFlash('error', $res['error'] ?? 'Gagal mengubah kata sandi.');
            }
        }
        header("Location: " . BASE_URL . "/admin.php?tab=admin");
        exit;
    }

    // C. Simpan Profil Pengguna Operasional (User)
    if ($action === 'save_user') {
        $nama = trim($_POST['user_nama'] ?? '');
        $username = trim($_POST['user_username'] ?? 'user');
        if ($nama === '' || $username === '') {
            setFlash('error', 'Nama lengkap dan Username Pengguna wajib diisi.');
        } else {
            $data = [
                'nama' => $nama,
                'username' => $username,
                'nip' => trim($_POST['user_nip'] ?? ''),
                'pangkat' => trim($_POST['user_pangkat'] ?? ''),
                'jabatan' => trim($_POST['user_jabatan'] ?? ''),
                'email' => trim($_POST['user_email'] ?? ''),
                'no_hp' => trim($_POST['user_no_hp'] ?? '')
            ];
            $newPass = trim($_POST['user_password'] ?? '');
            if ($newPass !== '') {
                if (strlen($newPass) < 8) {
                    setFlash('error', 'Kata sandi pengguna baru minimal 8 karakter demi keamanan sistem.');
                    header("Location: " . BASE_URL . "/admin.php?tab=user");
                    exit;
                }
                $data['password'] = $newPass;
            }
            SettingsModel::updateUserProfile($data);
            setFlash('success', 'Data identitas, hak akses, dan kredensial pengguna operasional (User) berhasil diperbarui.');
        }
        header("Location: " . BASE_URL . "/admin.php?tab=user");
        exit;
    }

    // D. Reset Akun Pengguna ke Standar
    if ($action === 'reset_user') {
        SettingsModel::resetUserToDefault();
        setFlash('success', 'Akun Pengguna Operasional berhasil direset ke standar (Username: user, Sandi: Kendari304).');
        header("Location: " . BASE_URL . "/admin.php?tab=user");
        exit;
    }

    // E. Simpan Pengaturan Satker & Kop
    if ($action === 'save_satker') {
        $satkerKode = trim($_POST['satker_kode'] ?? '');
        $satkerNama = trim($_POST['satker_nama'] ?? '');
        if ($satkerKode === '' || $satkerNama === '') {
            setFlash('error', 'Kode Satker dan Nama Kantor SAR wajib diisi.');
        } else {
            $data = [
                'satker_kode' => $satkerKode,
                'satker_nama' => $satkerNama,
                'alamat' => trim($_POST['alamat'] ?? ''),
                'no_telp' => trim($_POST['no_telp'] ?? ''),
                'email' => trim($_POST['email'] ?? ''),
                'call_sign' => trim($_POST['call_sign'] ?? ''),
                'frekuensi_default' => trim($_POST['frekuensi_default'] ?? ''),
                'gemini_api_key' => trim($_POST['gemini_api_key'] ?? ''),
                'default_theme' => trim($_POST['default_theme'] ?? 'dark')
            ];

            // Handle logo URL or File Upload with strict validation, GD auto-compression & orphan cleanup
            if (!empty($_FILES['logo_file']['name']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
                if ($_FILES['logo_file']['size'] > 5 * 1024 * 1024) {
                    setFlash('error', 'Ukuran berkas logo terlalu besar (Maksimal 5 MB).');
                    header("Location: " . BASE_URL . "/admin.php?tab=satker");
                    exit;
                }

                $ext = strtolower(pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION));
                $allowed = ['png', 'jpg', 'jpeg', 'svg', 'webp'];
                
                if (in_array($ext, $allowed, true)) {
                    $tmpPath = $_FILES['logo_file']['tmp_name'];
                    $uploadDir = __DIR__ . '/uploads';
                    if (!is_dir($uploadDir)) {
                        @mkdir($uploadDir, 0755, true);
                    }

                    $uploadedUrl = null;

                    // A. Validate SVG against Stored XSS
                    if ($ext === 'svg') {
                        $svgContent = file_get_contents($tmpPath);
                        if ($svgContent !== false && preg_match('/<script|javascript:|onload|onerror|onclick|onmouseover|<iframe|<embed|<object|<foreignobject/i', $svgContent)) {
                            setFlash('error', 'Berkas SVG ditolak: Terdeteksi skrip atau tag tidak aman.');
                            header("Location: " . BASE_URL . "/admin.php?tab=satker");
                            exit;
                        }
                        $filename = 'satker_logo_' . time() . '_' . bin2hex(random_bytes(4)) . '.svg';
                        $dest = $uploadDir . '/' . $filename;
                        if (move_uploaded_file($tmpPath, $dest)) {
                            $uploadedUrl = BASE_URL . '/uploads/' . $filename;
                        }
                    } else {
                        // B. Validate Raster Images MIME
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mime = finfo_file($finfo, $tmpPath);
                        finfo_close($finfo);
                        $validMimes = ['image/png', 'image/jpeg', 'image/webp'];
                        if (!in_array($mime, $validMimes, true)) {
                            setFlash('error', 'Format gambar tidak valid atau rusak.');
                            header("Location: " . BASE_URL . "/admin.php?tab=satker");
                            exit;
                        }

                        // C. GD Auto-Resize & WebP Compression
                        $srcImg = null;
                        if ($mime === 'image/jpeg') {
                            $srcImg = @imagecreatefromjpeg($tmpPath);
                        } elseif ($mime === 'image/png') {
                            $srcImg = @imagecreatefrompng($tmpPath);
                        } elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
                            $srcImg = @imagecreatefromwebp($tmpPath);
                        }

                        $savedOptimized = false;
                        if ($srcImg) {
                            $origW = imagesx($srcImg);
                            $origH = imagesy($srcImg);
                            $maxDim = 400; // Optimal bounding box for Basarnas letterhead & mobile

                            if ($origW > $maxDim || $origH > $maxDim) {
                                if ($origW >= $origH) {
                                    $targetW = $maxDim;
                                    $targetH = (int)round(($origH / $origW) * $maxDim);
                                } else {
                                    $targetH = $maxDim;
                                    $targetW = (int)round(($origW / $origH) * $maxDim);
                                }
                            } else {
                                $targetW = $origW;
                                $targetH = $origH;
                            }

                            $dstImg = imagecreatetruecolor($targetW, $targetH);
                            imagealphablending($dstImg, false);
                            imagesavealpha($dstImg, true);
                            $transCol = imagecolorallocatealpha($dstImg, 0, 0, 0, 127);
                            imagefilledrectangle($dstImg, 0, 0, $targetW, $targetH, $transCol);
                            imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $targetW, $targetH, $origW, $origH);

                            if (function_exists('imagewebp')) {
                                $filename = 'satker_logo_' . time() . '_' . bin2hex(random_bytes(4)) . '.webp';
                                $dest = $uploadDir . '/' . $filename;
                                $savedOptimized = imagewebp($dstImg, $dest, 90);
                            } else {
                                $filename = 'satker_logo_' . time() . '_' . bin2hex(random_bytes(4)) . '.png';
                                $dest = $uploadDir . '/' . $filename;
                                $savedOptimized = imagepng($dstImg, $dest, 8);
                            }

                            if (PHP_VERSION_ID < 80500) {
                                @imagedestroy($dstImg);
                                @imagedestroy($srcImg);
                            }

                            if ($savedOptimized) {
                                $uploadedUrl = BASE_URL . '/uploads/' . $filename;
                            }
                        }

                        // Fallback if GD is unavailable or fails
                        if (!$savedOptimized) {
                            $filename = 'satker_logo_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                            $dest = $uploadDir . '/' . $filename;
                            if (move_uploaded_file($tmpPath, $dest)) {
                                $uploadedUrl = BASE_URL . '/uploads/' . $filename;
                            }
                        }
                    }

                    if ($uploadedUrl) {
                        // D. Clean up previous orphan logo file safely (preserving files still referenced in BraSar)
                        $currSettings = SettingsModel::getSettings();
                        $oldLogo = $currSettings['logo_url'] ?? '';
                        if (!empty($oldLogo) && str_contains($oldLogo, '/uploads/satker_logo_')) {
                            $oldBase = basename($oldLogo);
                            $oldPath = $uploadDir . '/' . $oldBase;
                            
                            $db = getDB();
                            $chk = $db->prepare("SELECT COUNT(*) FROM BraSar WHERE logo_url LIKE :oldF");
                            $chk->execute([':oldF' => "%{$oldBase}%"]);
                            $inUse = (int)$chk->fetchColumn();

                            if ($inUse === 0 && file_exists($oldPath) && is_file($oldPath)) {
                                @unlink($oldPath);
                            }
                        }

                        $data['logo_url'] = $uploadedUrl;
                    }
                }
            } elseif (!empty($_POST['logo_url'])) {
                $data['logo_url'] = trim($_POST['logo_url']);
            }

            SettingsModel::updateSettings($data);
            setFlash('success', 'Identitas kantor SAR, call sign radio, frekuensi, dan kop surat berhasil diperbarui.');
        }
        header("Location: " . BASE_URL . "/admin.php?tab=satker");
        exit;
    }

    // F. Restore Basis Data dari SQL (.sql murni)
    if ($action === 'restore_sql') {
        if (!empty($_FILES['backup_file']['name']) && $_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['backup_file']['name'], PATHINFO_EXTENSION));
            if ($ext !== 'sql') {
                setFlash('error', 'Format berkas tidak valid! Hanya berkas dump database SQL (.sql) yang diizinkan untuk pemulihan.');
            } else {
                $sqlContent = file_get_contents($_FILES['backup_file']['tmp_name']);
                if ($sqlContent !== false && trim($sqlContent) !== '') {
                    try {
                        SettingsModel::restoreFromSQL($sqlContent);
                        setFlash('success', 'Basis data berhasil dipulihkan secara penuh dari berkas SQL Dump.');
                    } catch (Throwable $e) {
                        setFlash('error', 'Gagal memulihkan database dari SQL: ' . $e->getMessage());
                    }
                } else {
                    setFlash('error', 'Berkas SQL kosong atau tidak dapat dibaca.');
                }
            }
        } else {
            setFlash('error', 'Silakan pilih berkas cadangan SQL (.sql) untuk dipulihkan.');
        }
        header("Location: " . BASE_URL . "/admin.php?tab=backup");
        exit;
    }

    // G. Pembersihan Log Audit Berkala (Retention Policy & Pruning)
    if ($action === 'prune_audit') {
        $days = (int)($_POST['older_than_days'] ?? 180);
        $res = AuditHelper::pruneLogs($days);
        if ($res['deleted'] > 0) {
            setFlash('success', "Pembersihan arsip sukses: {$res['deleted']} baris log audit berumur > {$res['days']} hari berhasil dibersihkan.");
        } else {
            setFlash('info', "Pemeriksaan selesai: Tidak ada log audit yang berumur lebih dari {$res['days']} hari untuk dibersihkan.");
        }
        header("Location: " . BASE_URL . "/admin.php?tab=logs");
        exit;
    }
}

// Direct CSV Export for Audit Trail (Accessible to Admin)
if ($action === 'export_audit_csv' || (isset($_GET['action']) && $_GET['action'] === 'export_audit_csv')) {
    AuditHelper::exportCsv($_GET['log_action'] ?? null, $_GET['log_search'] ?? null);
    exit;
}

// 3. Prepare Data for View
$settings = SettingsModel::getSettings();
$dbStats = SettingsModel::getDbStats();

$usersList = class_exists('UserModel') ? UserModel::getAll() : [];

$auditLogs = [];
$totalLogs = 0;
$logPage = 1;
$totalPages = 1;
$logLimit = 25;
$logActionFilter = !empty($_GET['log_action']) ? trim($_GET['log_action']) : null;
$logSearch = !empty($_GET['log_search']) ? trim($_GET['log_search']) : null;

if ($tab === 'logs') {
    $logPage = max(1, (int)($_GET['page'] ?? 1));
    $logOffset = ($logPage - 1) * $logLimit;
    $totalLogs = AuditHelper::countLogs($logActionFilter, $logSearch);
    $auditLogs = AuditHelper::getLogs($logLimit, $logOffset, $logActionFilter, $logSearch);
    $totalPages = max(1, (int)ceil($totalLogs / $logLimit));
}

$pageTitle = 'Pengaturan Admin & Satker';
$currentRoute = 'admin';
$navbarTitle = 'Pengaturan Administrator & Satker';
$navbarSubtitle = 'Konfigurasi Akun, Sandi, Identitas Pangkalan SAR, serta Cadangan Database';

require_once VIEWS_DIR . '/layout/header.php';
require_once VIEWS_DIR . '/layout/sidebar.php';
require_once VIEWS_DIR . '/layout/navbar.php';
require_once VIEWS_DIR . '/admin.php';
require_once VIEWS_DIR . '/layout/footer.php';
