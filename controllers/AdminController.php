<?php
/**
 * QR-SAR - Admin Settings Controller
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Handles Admin Profile & Password, Operational Users, Satker Identity & Logo, Database Backup & Restore, and Audit Trail.
 */

declare(strict_types=1);

class AdminController extends BaseController {
    /**
     * Dispatch and render admin management console.
     */
    public function index(): void {
        $this->requireAdmin();

        $action = $_POST['action'] ?? ($_GET['action'] ?? null);
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $tab = $_GET['tab'] ?? 'admin';
        if ($tab === 'user') {
            $tab = 'admin';
        }
        if (!in_array($tab, ['admin', 'satker', 'ai', 'backup', 'logs'], true)) {
            $tab = 'admin';
        }

        // 1. Handle Export Actions (Direct SQL Download Only)
        if ($action === 'export_sql') {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            $sqlDump = BackupService::generateSQLDump();
            $filename = 'DUMP_DATABASE_SAR_KENDARI_' . date('Y-m-d_His') . '.sql';
            header('Content-Type: application/sql; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');
            echo $sqlDump;
            exit;
        }

        // Direct CSV Export for Audit Trail (Accessible to Admin)
        if ($action === 'export_audit_csv' || (isset($_GET['action']) && $_GET['action'] === 'export_audit_csv')) {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            AuditHelper::exportCsv($_GET['log_action'] ?? null, $_GET['log_search'] ?? null);
            exit;
        }

        // 2. Handle POST Actions
        if ($method === 'POST') {
            if (!$this->validateCsrf($_POST['csrf_token'] ?? null)) {
                $this->setFlash('error', 'Token keamanan CSRF tidak valid atau sesi Anda telah kedaluwarsa. Silakan muat ulang halaman.');
                $this->redirect("/admin.php?tab=" . urlencode($tab));
            }

            // A0. Tambah Akun Baru (Admin atau User + Foto Profil Opsional)
            if ($action === 'create_account') {
                $avatarPath = '';
                if (!empty($_FILES['account_avatar']['name']) && ($_FILES['account_avatar']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $up = UserModel::processAvatarUpload($_FILES['account_avatar']);
                    if (empty($up['success'])) {
                        $this->setFlash('error', (string)($up['error'] ?? 'Gagal mengunggah foto profil.'));
                        $this->redirect('/admin.php?tab=admin');
                    }
                    $avatarPath = (string)($up['path'] ?? '');
                }

                $res = UserModel::create([
                    'nama' => trim((string)($_POST['account_nama'] ?? '')),
                    'username' => trim((string)($_POST['account_username'] ?? '')),
                    'password' => trim((string)($_POST['account_password'] ?? '')),
                    'role' => trim((string)($_POST['account_role'] ?? 'USER')),
                    'nip' => trim((string)($_POST['account_nip'] ?? '')),
                    'pangkat' => trim((string)($_POST['account_pangkat'] ?? '')),
                    'jabatan' => trim((string)($_POST['account_jabatan'] ?? '')),
                    'email' => trim((string)($_POST['account_email'] ?? '')),
                    'no_hp' => trim((string)($_POST['account_no_hp'] ?? '')),
                    'is_active' => !empty($_POST['account_is_active']) ? 1 : 0,
                    'avatar_url' => $avatarPath,
                ]);

                if (!empty($res['success'])) {
                    $this->setFlash('success', (string)($res['message'] ?? 'Akun baru berhasil ditambahkan.'));
                } else {
                    if ($avatarPath !== '') {
                        UserModel::deleteAvatarFile($avatarPath);
                    }
                    $this->setFlash('error', (string)($res['error'] ?? 'Gagal menambahkan akun baru.'));
                }
                $this->redirect('/admin.php?tab=admin');
            }

            // A1. Perbarui Akun (Admin atau User + Foto Profil Opsional)
            if ($action === 'update_account') {
                $targetId = trim((string)($_POST['account_id'] ?? ''));
                $actorId = isset($_SESSION['user']['id']) ? (string)$_SESSION['user']['id'] : (isset($_SESSION['user_id']) ? (string)$_SESSION['user_id'] : null);
                $avatarPath = '';
                $hasUpload = !empty($_FILES['account_avatar']['name']) && ($_FILES['account_avatar']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
                if ($hasUpload) {
                    $up = UserModel::processAvatarUpload($_FILES['account_avatar']);
                    if (empty($up['success'])) {
                        $this->setFlash('error', (string)($up['error'] ?? 'Gagal mengunggah foto profil.'));
                        $this->redirect('/admin.php?tab=admin');
                    }
                    $avatarPath = (string)($up['path'] ?? '');
                }

                $payload = [
                    'nama' => trim((string)($_POST['account_nama'] ?? '')),
                    'username' => trim((string)($_POST['account_username'] ?? '')),
                    'password' => trim((string)($_POST['account_password'] ?? '')),
                    'role' => trim((string)($_POST['account_role'] ?? 'USER')),
                    'nip' => trim((string)($_POST['account_nip'] ?? '')),
                    'pangkat' => trim((string)($_POST['account_pangkat'] ?? '')),
                    'jabatan' => trim((string)($_POST['account_jabatan'] ?? '')),
                    'email' => trim((string)($_POST['account_email'] ?? '')),
                    'no_hp' => trim((string)($_POST['account_no_hp'] ?? '')),
                    'is_active' => !empty($_POST['account_is_active']) ? 1 : 0,
                    'clear_avatar' => !empty($_POST['clear_account_avatar']),
                ];
                if ($avatarPath !== '') {
                    $payload['avatar_url'] = $avatarPath;
                    $payload['clear_avatar'] = false;
                }

                $res = UserModel::update($targetId, $payload, $actorId);
                if (!empty($res['success'])) {
                    $this->setFlash('success', (string)($res['message'] ?? 'Data akun berhasil diperbarui.'));
                } else {
                    if ($avatarPath !== '') {
                        UserModel::deleteAvatarFile($avatarPath);
                    }
                    $this->setFlash('error', (string)($res['error'] ?? 'Gagal memperbarui data akun.'));
                }
                $this->redirect('/admin.php?tab=admin');
            }

            // A2. Hapus Akun (Admin atau User)
            if ($action === 'delete_account') {
                $targetId = trim((string)($_POST['account_id'] ?? ''));
                $actorId = isset($_SESSION['user']['id']) ? (string)$_SESSION['user']['id'] : (isset($_SESSION['user_id']) ? (string)$_SESSION['user_id'] : null);
                $res = UserModel::delete($targetId, $actorId);
                if (!empty($res['success'])) {
                    $this->setFlash('success', (string)($res['message'] ?? 'Akun berhasil dihapus.'));
                } else {
                    $this->setFlash('error', (string)($res['error'] ?? 'Gagal menghapus akun.'));
                }
                $this->redirect('/admin.php?tab=admin');
            }

            // A. Simpan Profil Administrator (kompatibilitas mundur)
            if ($action === 'save_admin') {
                $nama = trim($_POST['admin_nama'] ?? '');
                $username = trim($_POST['admin_username'] ?? 'admin');
                if ($nama === '' || $username === '') {
                    $this->setFlash('error', 'Nama lengkap dan Username Administrator wajib diisi.');
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
                    if (!SettingsModel::updateAdminProfile($data)) {
                        $this->setFlash('error', 'Gagal memperbarui profil Administrator: username mungkin sudah digunakan oleh akun lain.');
                        $this->redirect('/admin.php?tab=admin');
                    }

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

                    $this->setFlash('success', 'Profil Administrator dan kontak dinas berhasil diperbarui.');
                }
                $this->redirect('/admin.php?tab=admin');
            }

            // B. Ubah Kata Sandi Administrator
            if ($action === 'change_admin_pass') {
                $oldPass = trim($_POST['old_password'] ?? '');
                $newPass = trim($_POST['new_password'] ?? '');
                $confirmPass = trim($_POST['confirm_password'] ?? '');

                if ($oldPass === '') {
                    $this->setFlash('error', 'Silakan masukkan kata sandi saat ini / sandi lama.');
                } elseif (strlen($newPass) < 8) {
                    $this->setFlash('error', 'Kata sandi baru minimal harus 8 karakter demi keamanan sistem.');
                } elseif ($newPass !== $confirmPass) {
                    $this->setFlash('error', 'Konfirmasi kata sandi baru tidak cocok.');
                } else {
                    $userId = $_SESSION['user']['id'] ?? null;
                    $res = SettingsModel::changePassword('ADMIN', $oldPass, $newPass, $userId);
                    if ($res['success']) {
                        $this->setFlash('success', 'Kata sandi Administrator berhasil diubah. Gunakan sandi baru untuk login selanjutnya.');
                    } else {
                        $this->setFlash('error', $res['error'] ?? 'Gagal mengubah kata sandi.');
                    }
                }
                $this->redirect('/admin.php?tab=admin');
            }

            // C. Simpan Profil Pengguna Operasional (User)
            if ($action === 'save_user') {
                $nama = trim($_POST['user_nama'] ?? '');
                $username = trim($_POST['user_username'] ?? 'user');
                if ($nama === '' || $username === '') {
                    $this->setFlash('error', 'Nama lengkap dan Username Pengguna wajib diisi.');
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
                            $this->setFlash('error', 'Kata sandi pengguna baru minimal 8 karakter demi keamanan sistem.');
                            $this->redirect('/admin.php?tab=user');
                        }
                        $data['password'] = $newPass;
                    }
                    if (!SettingsModel::updateUserProfile($data)) {
                        $this->setFlash('error', 'Gagal memperbarui akun pengguna: username mungkin sudah digunakan oleh akun lain.');
                    } else {
                        $this->setFlash('success', 'Data identitas, hak akses, dan kredensial pengguna operasional (User) berhasil diperbarui.');
                    }
                }
                $this->redirect('/admin.php?tab=user');
            }

            // D. Reset Akun Pengguna ke Standar (sandi sementara acak sekali pakai)
            if ($action === 'reset_user') {
                $tempPass = 'SAR-' . strtoupper(bin2hex(random_bytes(4)));
                if (!SettingsModel::resetUserToDefault($tempPass)) {
                    $this->setFlash('error', 'Gagal mereset akun Pengguna Operasional: username "user" mungkin sedang dipakai akun lain.');
                } else {
                    $this->setFlash('success', "Akun Pengguna Operasional berhasil direset (Username: user, Sandi sementara: {$tempPass}). Catat sandi ini dan segera ganti setelah login.");
                }
                $this->redirect('/admin.php?tab=user');
            }

            // E. Simpan Pengaturan Satker & Kop
            if ($action === 'save_satker') {
                $satkerKode = trim($_POST['satker_kode'] ?? '');
                $satkerNama = trim($_POST['satker_nama'] ?? '');
                if ($satkerKode === '' || $satkerNama === '') {
                    $this->setFlash('error', 'Kode Satker dan Nama Kantor SAR wajib diisi.');
                } else {
                    $data = [
                        'satker_kode' => $satkerKode,
                        'satker_nama' => $satkerNama,
                        'alamat' => trim($_POST['alamat'] ?? ''),
                        'no_telp' => trim($_POST['no_telp'] ?? ''),
                        'no_wa' => trim($_POST['no_wa'] ?? ''),
                        'email' => trim($_POST['email'] ?? ''),
                        'call_sign' => trim($_POST['call_sign'] ?? ''),
                        'default_theme' => trim($_POST['default_theme'] ?? 'dark'),
                        'ui_theme' => in_array($_POST['ui_theme'] ?? '', ['klasik', 'minimal'], true) ? $_POST['ui_theme'] : 'klasik'
                    ];
                    if (array_key_exists('frekuensi_default', $_POST)) {
                        $data['frekuensi_default'] = trim((string)$_POST['frekuensi_default']);
                    }
                    $newGeminiKey = trim($_POST['gemini_api_key'] ?? '');
                    if (!empty($_POST['clear_gemini_api_key'])) {
                        $data['gemini_api_key'] = '';
                    } elseif ($newGeminiKey !== '') {
                        $data['gemini_api_key'] = $newGeminiKey;
                    }

                    $this->prosesLogoSatker('logo_file', 'logo_url', $data);
                    $this->prosesLogoSatker('logo_file_2', 'logo_url_2', $data);
                    $this->prosesLogoSatker('logo_file_3', 'logo_url_3', $data);

                    if (!SettingsModel::updateSettings($data)) {
                        $this->setFlash('error', 'Gagal menyimpan pengaturan Satuan Kerja ke basis data.');
                    } elseif ((SettingsModel::getSettings()['ui_theme'] ?? null) === null) {
                        $this->setFlash('error', 'Pengaturan satker tersimpan, tetapi Gaya Tampilan belum bisa disimpan karena migrasi database belum dijalankan. Jalankan: php database/migrate.php');
                    } else {
                        $this->setFlash('success', 'Identitas kantor SAR, nomor WhatsApp, call sign radio, kop surat, dan gaya tampilan berhasil diperbarui.');
                    }
                }
                $this->redirect('/admin.php?tab=satker');
            }

            // E2. Simpan Tambah / Edit Profil API Key AI (Multi-Provider: Gemini & DeepSeek)
            if ($action === 'save_ai_key') {
                $res = AiConfigService::saveKey([
                    'id' => trim((string)($_POST['ai_key_id'] ?? '')),
                    'label' => trim((string)($_POST['ai_label'] ?? '')),
                    'provider' => trim((string)($_POST['ai_provider'] ?? '')),
                    'api_key' => trim((string)($_POST['ai_api_key'] ?? '')),
                    'model_preset' => trim((string)($_POST['ai_model_preset'] ?? '')),
                    'model_custom' => trim((string)($_POST['ai_model_custom'] ?? '')),
                    'enabled' => !empty($_POST['ai_enabled']),
                    'set_as_default' => !empty($_POST['ai_set_default']),
                ]);
                if (!empty($res['success'])) {
                    $this->setFlash('success', (string)($res['message'] ?? 'Profil API Key AI berhasil disimpan.'));
                } else {
                    $this->setFlash('error', (string)($res['error'] ?? 'Gagal menyimpan profil API Key AI.'));
                }
                $this->redirect('/admin.php?tab=ai');
            }

            // E3. Hapus Profil API Key AI
            if ($action === 'delete_ai_key') {
                $res = AiConfigService::deleteKey(trim((string)($_POST['ai_key_id'] ?? '')));
                if (!empty($res['success'])) {
                    $this->setFlash('success', (string)($res['message'] ?? 'Profil API Key AI berhasil dihapus.'));
                } else {
                    $this->setFlash('error', (string)($res['error'] ?? 'Gagal menghapus profil API Key AI.'));
                }
                $this->redirect('/admin.php?tab=ai');
            }

            // E4. Jadikan Profil API Key Tertentu Sebagai Default Cepat
            if ($action === 'set_default_ai_key') {
                $curr = AiConfigService::loadRawConfig();
                $res = AiConfigService::savePreferences(
                    trim((string)($_POST['ai_key_id'] ?? '')),
                    (bool)($curr['auto_fallback'] ?? true)
                );
                if (!empty($res['success'])) {
                    $this->setFlash('success', (string)($res['message'] ?? 'API Key default berhasil diubah.'));
                } else {
                    $this->setFlash('error', (string)($res['error'] ?? 'Gagal mengubah API Key default.'));
                }
                $this->redirect('/admin.php?tab=ai');
            }

            // E5. Simpan Preferensi Default & Auto-Fallback AI
            if ($action === 'save_ai_preferences') {
                $res = AiConfigService::savePreferences(
                    trim((string)($_POST['default_key_id'] ?? '')),
                    !empty($_POST['auto_fallback'])
                );
                if (!empty($res['success'])) {
                    $this->setFlash('success', (string)($res['message'] ?? 'Preferensi Asisten AI berhasil disimpan.'));
                } else {
                    $this->setFlash('error', (string)($res['error'] ?? 'Gagal menyimpan preferensi Asisten AI.'));
                }
                $this->redirect('/admin.php?tab=ai');
            }

            // F. Restore Basis Data dari SQL (.sql murni)
            if ($action === 'restore_sql') {
                if (!empty($_FILES['backup_file']['name']) && $_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['backup_file']['name'], PATHINFO_EXTENSION));
                    if ($ext !== 'sql') {
                        $this->setFlash('error', 'Format berkas tidak valid! Hanya berkas dump database SQL (.sql) yang diizinkan untuk pemulihan.');
                    } else {
                        $sqlContent = file_get_contents($_FILES['backup_file']['tmp_name']);
                        if ($sqlContent !== false && trim($sqlContent) !== '') {
                            try {
                                BackupService::restoreFromSQL($sqlContent);
                                $this->setFlash('success', 'Basis data berhasil dipulihkan secara penuh dari berkas SQL Dump.');
                            } catch (Throwable $e) {
                                $this->setFlash('error', 'Gagal memulihkan database dari SQL: ' . $e->getMessage());
                            }
                        } else {
                            $this->setFlash('error', 'Berkas SQL kosong atau tidak dapat dibaca.');
                        }
                    }
                } else {
                    $this->setFlash('error', 'Silakan pilih berkas cadangan SQL (.sql) untuk dipulihkan.');
                }
                $this->redirect('/admin.php?tab=backup');
            }

            // G. Pembersihan Log Audit Berkala & Berkas Sumber Kolase Yatim
            if ($action === 'prune_audit') {
                $days = (int)($_POST['older_than_days'] ?? 180);
                $res = AuditHelper::pruneLogs($days);
                if (class_exists('LogService')) {
                    LogService::cleanOldLogs(30);
                }
                $prunedOrphans = class_exists('OperasiDokumentasiModel')
                    ? OperasiDokumentasiModel::pruneOrphanSourceFiles(86400)
                    : 0;
                $extraOrphanMsg = $prunedOrphans > 0 ? " dan {$prunedOrphans} berkas sumber kolase yatim (> 24 jam)" : '';
                if ($res['deleted'] > 0 || $prunedOrphans > 0) {
                    $this->setFlash('success', "Pembersihan arsip sukses: {$res['deleted']} baris log audit berumur > {$res['days']} hari{$extraOrphanMsg} berhasil dibersihkan.");
                } else {
                    $this->setFlash('info', "Pemeriksaan selesai: Tidak ada log audit yang berumur lebih dari {$res['days']} hari maupun berkas kolase yatim untuk dibersihkan.");
                }
                $this->redirect('/admin.php?tab=logs');
            }
        }

        // 3. Prepare Data for View
        $settings = SettingsModel::getSettings();
        $aiConfig = class_exists('AiConfigService') ? AiConfigService::getPublicConfig() : ['keys' => [], 'providers' => [], 'auto_fallback' => true, 'default_key_id' => '', 'active_default' => null, 'env_fallbacks' => []];
        $dbStats = $tab === 'backup' ? BackupService::getDbStats() : [];

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

        $viewData = [
            'tab' => $tab,
            'currentUser' => AuthHelper::getCurrentUser(),
            'settings' => $settings,
            'aiConfig' => $aiConfig,
            'dbStats' => $dbStats,
            'usersList' => $usersList,
            'auditLogs' => $auditLogs,
            'totalLogs' => $totalLogs,
            'logPage' => $logPage,
            'totalPages' => $totalPages,
            'logLimit' => $logLimit,
            'logActionFilter' => $logActionFilter,
            'logSearch' => $logSearch
        ];

        $this->render('admin/index', $viewData, [
            'title' => 'Pengaturan Admin & Satker',
            'route' => 'admin',
            'navbarTitle' => 'Pengaturan Administrator & Satker',
            'navbarSubtitle' => 'Konfigurasi Akun, Sandi, Identitas Pangkalan SAR, serta Cadangan Database'
        ]);
    }

    /**
     * Unggah/URL logo kop (logo_url = kiri, logo_url_2 = kanan): validasi ketat, kompres GD & bersihkan berkas lama.
     * Hasil ditulis ke $data[$urlField]; galat validasi = flash + redirect.
     */
    private function prosesLogoSatker(string $fileField, string $urlField, array &$data): void {
                // Handle logo URL or File Upload with strict validation, GD auto-compression & orphan cleanup
                if (!empty($_FILES[$fileField]['name']) && $_FILES[$fileField]['error'] === UPLOAD_ERR_OK) {
                    if ($_FILES[$fileField]['size'] > 5 * 1024 * 1024) {
                        $this->setFlash('error', 'Ukuran berkas logo terlalu besar (Maksimal 5 MB).');
                        $this->redirect('/admin.php?tab=satker');
                    }

                    $ext = strtolower(pathinfo($_FILES[$fileField]['name'], PATHINFO_EXTENSION));
                    $allowed = ['png', 'jpg', 'jpeg', 'svg', 'webp'];

                    if (in_array($ext, $allowed, true)) {
                        $tmpPath = $_FILES[$fileField]['tmp_name'];
                        $uploadDir = ROOT_DIR . '/uploads';
                        if (!is_dir($uploadDir)) {
                            @mkdir($uploadDir, 0755, true);
                        }

                        $uploadedUrl = null;

                        // A. Validate SVG against Stored XSS
                        if ($ext === 'svg') {
                            $svgContent = file_get_contents($tmpPath);
                            if ($svgContent !== false && preg_match('/<script|javascript:|data:text\/html|\bon[a-z]+\s*=|<iframe|<embed|<object|<foreignobject/i', $svgContent)) {
                                $this->setFlash('error', 'Berkas SVG ditolak: Terdeteksi skrip atau tag tidak aman.');
                                $this->redirect('/admin.php?tab=satker');
                            }
                            $filename = 'satker_logo_' . time() . '_' . bin2hex(random_bytes(4)) . '.svg';
                            $dest = $uploadDir . '/' . $filename;
                            if (move_uploaded_file($tmpPath, $dest)) {
                                $uploadedUrl = BASE_URL . '/uploads/' . $filename;
                            }
                        } else {
                            // B. Validate Raster Images MIME
                            $finfo = new finfo(FILEINFO_MIME_TYPE);
                            $mime = $finfo->file($tmpPath);
                            $validMimes = ['image/png', 'image/jpeg', 'image/webp'];
                            if (!in_array($mime, $validMimes, true)) {
                                $this->setFlash('error', 'Format gambar tidak valid atau rusak.');
                                $this->redirect('/admin.php?tab=satker');
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

                                unset($dstImg, $srcImg);

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
                            // D. Clean up previous orphan logo file safely
                            $currSettings = SettingsModel::getSettings();
                            $oldLogo = $currSettings[$urlField] ?? '';
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

                            $data[$urlField] = $uploadedUrl;
                        }
                    }
                } elseif (!empty($_POST[$urlField])) {
                    $rawUrlInput = trim((string)$_POST[$urlField]);
                    if ($rawUrlInput !== '') {
                        $safeLogoUrl = qrsar_sanitize_asset_url($rawUrlInput, '');
                        if ($safeLogoUrl === '') {
                            $this->setFlash('error', 'URL logo tidak valid atau menggunakan skema yang tidak diizinkan.');
                            $this->redirect('/admin.php?tab=satker');
                        }
                        $data[$urlField] = $safeLogoUrl;
                    } else {
                        $data[$urlField] = '';
                    }
                }
    }
}
