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

$action = $_GET['action'] ?? null;
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$tab = $_GET['tab'] ?? 'admin';
if (!in_array($tab, ['admin', 'user', 'satker', 'backup'], true)) {
    $tab = 'admin';
}

// 1. Handle Export Actions (Direct Downloads)
if ($action === 'export_json') {
    $backupData = SettingsModel::exportJsonBackup();
    $filename = 'BACKUP_LENGKAP_SAR_KENDARI_' . date('Y-m-d_His') . '.json';
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo json_encode($backupData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

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
        } elseif (strlen($newPass) < 4) {
            setFlash('error', 'Kata sandi baru minimal harus 4 karakter.');
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
                if (strlen($newPass) < 4) {
                    setFlash('error', 'Kata sandi pengguna baru minimal 4 karakter.');
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

            // Handle logo URL or File Upload with strict validation & SVG sanitization
            if (!empty($_FILES['logo_file']['name']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION));
                $allowed = ['png', 'jpg', 'jpeg', 'svg', 'webp'];
                
                if (in_array($ext, $allowed, true)) {
                    $tmpPath = $_FILES['logo_file']['tmp_name'];
                    
                    // Validate SVG against Stored XSS
                    if ($ext === 'svg') {
                        $svgContent = file_get_contents($tmpPath);
                        if ($svgContent !== false && preg_match('/<script|javascript:|onload|onerror|onclick|onmouseover|<iframe|<embed|<object|<foreignobject/i', $svgContent)) {
                            setFlash('error', 'Berkas SVG ditolak: Terdeteksi skrip atau tag tidak aman.');
                            header("Location: " . BASE_URL . "/admin.php?tab=satker");
                            exit;
                        }
                    } else {
                        // Validate Raster Images MIME
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mime = finfo_file($finfo, $tmpPath);
                        finfo_close($finfo);
                        $validMimes = ['image/png', 'image/jpeg', 'image/webp'];
                        if (!in_array($mime, $validMimes, true)) {
                            setFlash('error', 'Format gambar tidak valid atau rusak.');
                            header("Location: " . BASE_URL . "/admin.php?tab=satker");
                            exit;
                        }
                    }

                    $uploadDir = __DIR__ . '/uploads';
                    if (!is_dir($uploadDir)) {
                        @mkdir($uploadDir, 0755, true);
                    }
                    $filename = 'satker_logo_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $dest = $uploadDir . '/' . $filename;
                    if (move_uploaded_file($tmpPath, $dest)) {
                        $data['logo_url'] = BASE_URL . '/uploads/' . $filename;
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

    // F. Restore Basis Data dari JSON
    if ($action === 'restore_json') {
        if (!empty($_FILES['backup_file']['name']) && $_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
            $content = file_get_contents($_FILES['backup_file']['tmp_name']);
            $parsed = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                SettingsModel::restoreFromJson($parsed);
                setFlash('success', 'Basis data berhasil dipulihkan secara penuh dari berkas cadangan JSON.');
            } else {
                setFlash('error', 'Berkas cadangan tidak valid atau rusak (Bukan format JSON yang benar).');
            }
        } else {
            setFlash('error', 'Silakan pilih berkas cadangan JSON (.json) untuk dipulihkan.');
        }
        header("Location: " . BASE_URL . "/admin.php?tab=backup");
        exit;
    }
}

// 3. Prepare Data for View
$settings = SettingsModel::getSettings();
$dbStats = SettingsModel::getDbStats();

$pageTitle = 'Pengaturan Admin & Satker';
$currentRoute = 'admin';
$navbarTitle = 'Pengaturan Administrator & Satker';
$navbarSubtitle = 'Konfigurasi Akun, Sandi, Identitas Pangkalan SAR, serta Cadangan Database';

require_once VIEWS_DIR . '/layout/header.php';
require_once VIEWS_DIR . '/layout/sidebar.php';
require_once VIEWS_DIR . '/layout/navbar.php';
require_once VIEWS_DIR . '/admin.php';
require_once VIEWS_DIR . '/layout/footer.php';
