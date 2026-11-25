<?php
/**
 * Test: Pengelolaan Multi-Akun Admin & User, Foto Profil (Avatar),
 * Larangan Ubah Sandi Mandiri User, & Reset Sandi Admin 3x Salah via Secret Word.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Akses Ditolak: Skrip pengujian hanya dapat dijalankan melalui CLI.' . PHP_EOL);
}

require_once __DIR__ . '/../config/app.php';

echo "=== TEST AKUN ADMIN & USER, AVATAR, & RESET SECRET WORD ADMIN ===\n\n";

$passed = 0;
$total = 0;

function check(bool $cond, string $label, string $detail = ''): void {
    global $passed, $total;
    $total++;
    if ($cond) {
        $passed++;
        echo " [PASS] {$label}\n";
    } else {
        echo " [FAIL] {$label}" . ($detail !== '' ? " ({$detail})" : '') . "\n";
    }
}

$db = getDB();
UserModel::ensureSchemaUpdated(true);

$createdIds = [];
$createdFiles = [];
$origAdminRow = UserModel::getById('USR-ADMIN');

try {
    // 1. Skema kolom avatar_url & konstanta default password
    echo "--- 1. Skema Database & Konfigurasi ---\n";
    check(UserModel::hasAvatarColumn(true), 'Kolom avatar_url tersedia di tabel Users');
    check(DEFAULT_RESET_PASSWORD === 'Kendari2026#', 'DEFAULT_RESET_PASSWORD bernilai Kendari2026#');
    check(defined('ADMIN_RESET_SECRET_WORD_HASH') && strlen(ADMIN_RESET_SECRET_WORD_HASH) === 64, 'ADMIN_RESET_SECRET_WORD_HASH terdefinisi sebagai hash SHA-256 64 hex');

    // Audit tidak ada secret word plaintext di berkas sumber
    $secretPlain = implode('', ['irf', 'and', 'ydk']);
    $checkedSources = [
        'config/app.php',
        'helpers/AuthHelper.php',
        'controllers/AuthController.php',
        'views/auth/login.php',
        'views/admin/partials/tab_admin.php',
    ];
    foreach ($checkedSources as $relFile) {
        $src = (string)file_get_contents(ROOT_DIR . '/' . $relFile);
        check(stripos($src, $secretPlain) === false, "Berkas {$relFile} bebas dari secret word plaintext");
    }

    // 2. Multi-Akun CRUD (ADMIN & USER) + Safeguards
    echo "\n--- 2. CRUD Akun Admin & User + Safeguard ---\n";
    $suffix = bin2hex(random_bytes(3));
    $testUserUsername = 'usr_uji_' . $suffix;
    $testAdminUsername = 'adm_uji_' . $suffix;

    // Login simulasi sebagai USR-ADMIN
    if ($origAdminRow) {
        AuthHelper::setSession([
            'id' => (string)$origAdminRow['id'],
            'username' => (string)$origAdminRow['username'],
            'nama' => (string)$origAdminRow['nama'],
            'role' => 'ADMIN',
            'roleTitle' => 'Administrator Sistem',
            'avatar_url' => (string)($origAdminRow['avatar_url'] ?? ''),
            'loginAt' => date('Y-m-d H:i:s'),
        ]);
    }

    // Tolak sandi < 8 karakter
    $shortPass = UserModel::create([
        'username' => $testUserUsername,
        'password' => '1234567',
        'nama' => 'Operator Uji Pendek',
        'role' => 'USER',
    ]);
    check(($shortPass['success'] ?? true) === false, 'Create akun dengan kata sandi < 8 karakter ditolak');

    // Tambah akun USER baru
    $resCreateUser = UserModel::create([
        'username' => $testUserUsername,
        'password' => 'OperatorUji2026!',
        'nama' => 'Petugas Uji Kendari',
        'nip' => '199901012026011001',
        'pangkat' => 'Pengatur (II/c)',
        'jabatan' => 'Rescuer Pemula',
        'email' => 'uji.user@basarnas.go.id',
        'no_hp' => '081234500001',
        'role' => 'USER',
        'is_active' => 1,
    ]);
    check(($resCreateUser['success'] ?? false) === true && !empty($resCreateUser['id']), 'Admin dapat menambah akun USER baru');
    $newUserId = (string)($resCreateUser['id'] ?? '');
    if ($newUserId !== '') $createdIds[] = $newUserId;

    // Tolak duplikasi username
    $resDup = UserModel::create([
        'username' => $testUserUsername,
        'password' => 'OperatorUji2026!',
        'nama' => 'Duplikat',
        'role' => 'USER',
    ]);
    check(($resDup['success'] ?? true) === false, 'Username duplikat ditolak');

    // Tambah akun ADMIN baru
    $resCreateAdmin = UserModel::create([
        'username' => $testAdminUsername,
        'password' => 'AdminUji2026#',
        'nama' => 'Admin Uji Kedua',
        'jabatan' => 'Kasubsie Operasi',
        'role' => 'ADMIN',
        'is_active' => 1,
    ]);
    check(($resCreateAdmin['success'] ?? false) === true && !empty($resCreateAdmin['id']), 'Admin dapat menambah akun ADMIN baru');
    $newAdminId = (string)($resCreateAdmin['id'] ?? '');
    if ($newAdminId !== '') $createdIds[] = $newAdminId;

    // Edit akun USER + ganti sandi + promosi role sementara
    $resEditUser = UserModel::update($newUserId, [
        'username' => $testUserUsername,
        'nama' => 'Petugas Uji Diedit',
        'jabatan' => 'Rescuer Mahir',
        'role' => 'USER',
        'is_active' => 1,
        'password' => 'OperatorBaru2026!',
    ], 'USR-ADMIN');
    check(($resEditUser['success'] ?? false) === true, 'Admin dapat mengedit profil dan sandi akun USER');
    $editedUser = UserModel::getById($newUserId);
    check(($editedUser['nama'] ?? '') === 'Petugas Uji Diedit' && password_verify('OperatorBaru2026!', (string)($editedUser['password_hash'] ?? '')), 'Perubahan nama dan hash sandi tersimpan akurat');

    // Safeguard: tidak boleh hapus akun sendiri
    $resSelfDel = UserModel::delete('USR-ADMIN', 'USR-ADMIN');
    check(($resSelfDel['success'] ?? true) === false, 'Penghapusan akun sendiri yang sedang login diblokir');

    // Safeguard: tidak boleh nonaktifkan / turunkan role akun sendiri
    $resSelfDemote = UserModel::update('USR-ADMIN', [
        'username' => (string)($origAdminRow['username'] ?? 'admin'),
        'nama' => (string)($origAdminRow['nama'] ?? 'Administrator'),
        'role' => 'USER',
        'is_active' => 1,
    ], 'USR-ADMIN');
    check(($resSelfDemote['success'] ?? true) === false, 'Penurunan role akun sendiri diblokir');

    // Hapus akun USER & ADMIN uji tambahan
    $resDelUser = UserModel::delete($newUserId, 'USR-ADMIN');
    check(($resDelUser['success'] ?? false) === true && UserModel::getById($newUserId) === null, 'Admin dapat menghapus akun USER');

    // 3. Upload & Cleanup Foto Profil (Avatar)
    echo "\n--- 3. Upload, Validasi & Pembersihan Foto Profil (Avatar) ---\n";
    // Tolak file bukan gambar
    $fakeTxt = sys_get_temp_dir() . '/qrsar_fake_avatar_' . $suffix . '.txt';
    file_put_contents($fakeTxt, '<?php echo "not an image"; ?>');
    $resBadAvatar = UserModel::processAvatarUpload([
        'name' => 'shell.php.jpg',
        'tmp_name' => $fakeTxt,
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($fakeTxt),
    ]);
    @unlink($fakeTxt);
    check(($resBadAvatar['success'] ?? true) === false, 'Upload berkas non-gambar ditolak oleh pemeriksaan MIME finfo');

    // Buat gambar PNG valid 120x80 via GD dan uji pemrosesan avatar
    if (function_exists('imagecreatetruecolor')) {
        $tmpPng = sys_get_temp_dir() . '/qrsar_valid_avatar_' . $suffix . '.png';
        $im = imagecreatetruecolor(120, 80);
        $orange = imagecolorallocate($im, 245, 130, 32);
        imagefilledrectangle($im, 0, 0, 120, 80, $orange);
        imagepng($im, $tmpPng);
        unset($im);

        $resAvatar1 = UserModel::processAvatarUpload([
            'name' => 'foto_profil.png',
            'tmp_name' => $tmpPng,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmpPng),
        ]);
        @unlink($tmpPng);

        $avatarRel1 = (string)($resAvatar1['path'] ?? '');
        check(($resAvatar1['success'] ?? false) === true && str_starts_with($avatarRel1, 'uploads/avatars/'), 'Upload gambar PNG valid berhasil diproses ke uploads/avatars/', $resAvatar1['error'] ?? '');
        if ($avatarRel1 !== '') {
            $abs1 = ROOT_DIR . '/' . $avatarRel1;
            $createdFiles[] = $abs1;
            check(is_file($abs1) && filesize($abs1) > 0, 'Berkas fisik avatar tersimpan di disk');

            // Simpan ke DB untuk akun $newAdminId
            $resSaveAv = UserModel::updateAvatar($newAdminId, $avatarRel1);
            $rowAfterAv = UserModel::getById($newAdminId);
            check(($resSaveAv['success'] ?? false) === true && ($rowAfterAv['avatar_url'] ?? '') === $avatarRel1, 'UserModel::updateAvatar menyimpan path ke database');

            // Hapus avatar (clear) harus membersihkan berkas fisik
            $resClearAv = UserModel::updateAvatar($newAdminId, '');
            check(($resClearAv['success'] ?? false) === true && !is_file($abs1), 'Menghapus avatar membersihkan berkas lama dari disk');
        }
    }

    // 4. Safeguard: Admin terakhir aktif tidak boleh dihapus
    echo "\n--- 4. Safeguard Admin Aktif Terakhir ---\n";
    // Hapus $newAdminId agar tinggal 1 admin aktif (jika di DB test cuma ada USR-ADMIN)
    $resDelAdmin2 = UserModel::delete($newAdminId, 'USR-ADMIN');
    check(($resDelAdmin2['success'] ?? false) === true, 'Admin kedua berhasil dihapus saat masih ada admin lain');

    if (UserModel::countActiveAdmins() === 1) {
        $resDelLast = UserModel::delete('USR-ADMIN', 'SYSTEM-OTHER');
        check(($resDelLast['success'] ?? true) === false, 'Penghapusan admin aktif terakhir ditolak');
    } else {
        check(true, 'Terdapat lebih dari 1 admin aktif di DB test (skip cek single-admin)');
    }

    // 5. Larangan User Mengubah Sandi Sendiri & UI Profil/Admin/Sidebar
    echo "\n--- 5. Larangan Ubah Sandi User & Integritas View ---\n";
    $profileView = (string)file_get_contents(ROOT_DIR . '/views/auth/profile.php');
    $authCtrl = (string)file_get_contents(ROOT_DIR . '/controllers/AuthController.php');
    $adminIndexView = (string)file_get_contents(ROOT_DIR . '/views/admin/index.php');
    $tabAdminView = (string)file_get_contents(ROOT_DIR . '/views/admin/partials/tab_admin.php');
    $sidebarView = (string)file_get_contents(ROOT_DIR . '/views/layout/sidebar.php');
    $navbarView = (string)file_get_contents(ROOT_DIR . '/views/layout/navbar.php');
    $headerProfilePartial = (string)file_get_contents(ROOT_DIR . '/views/layout/partials/header_profile_menu.php');
    $gisView = (string)file_get_contents(ROOT_DIR . '/views/gis.php');
    $loginView = (string)file_get_contents(ROOT_DIR . '/views/auth/login.php');

    check(!str_contains($profileView, 'value="change_password"') && !str_contains($profileView, 'name="new_password"'), 'views/auth/profile.php tidak memuat formulir ubah kata sandi');
    check(str_contains($profileView, 'value="update_avatar"') && str_contains($profileView, 'name="avatar_file"'), 'views/auth/profile.php memuat formulir unggah foto profil');
    check(str_contains($authCtrl, "action === 'change_password'") && str_contains($authCtrl, 'tidak diizinkan mengubah kata sandi'), 'AuthController::profile() menolak POST change_password dari sisi server');
    check((str_contains($adminIndexView, 'Akun &amp; Pengguna') || str_contains($adminIndexView, 'Akun & Pengguna')) && !str_contains($adminIndexView, 'Akun Pengguna'), 'Tab Admin & User tergabung menjadi 1 tab Akun & Pengguna');
    check(str_contains($tabAdminView, 'create_account') && str_contains($tabAdminView, 'update_account') && str_contains($tabAdminView, 'delete_account'), 'tab_admin.php mendukung tambah, edit, dan hapus akun');
    check(
        str_contains($headerProfilePartial, 'resolveAvatarDisplayUrl')
        && str_contains($headerProfilePartial, 'headerProfileBtn')
        && str_contains($headerProfilePartial, 'headerProfilePopover')
        && str_contains($navbarView, 'partials/header_profile_menu.php')
        && str_contains($gisView, 'layout/partials/header_profile_menu.php')
        && !str_contains($sidebarView, 'sidebar-footer-user'),
        'Profil & foto pengguna tampil konsisten di header sudut kanan atas (termasuk GIS, bukan lagi di sidebar)'
    );
    check(str_contains($loginView, 'adminSecretResetCard') && str_contains($loginView, 'admin_secret_reset') && !str_contains($loginView, DEFAULT_RESET_PASSWORD), 'views/auth/login.php memiliki form secret word reset tanpa membocorkan default password');

    // 6. Reset Sandi Admin Setelah 3x Salah Password via Secret Word
    echo "\n--- 6. Reset Sandi Admin 3x Salah Password + Secret Word ---\n";
    // Buat admin khusus uji reset agar tidak mengubah kredensial USR-ADMIN asli
    $resetAdminUsername = 'adm_rst_' . $suffix;
    $resResetAdmin = UserModel::create([
        'username' => $resetAdminUsername,
        'password' => 'SandiAdminAwal99#',
        'nama' => 'Admin Uji Reset',
        'role' => 'ADMIN',
        'is_active' => 1,
    ]);
    $resetAdminId = (string)($resResetAdmin['id'] ?? '');
    if ($resetAdminId !== '') $createdIds[] = $resetAdminId;

    $resetUserUsername = 'usr_rst_' . $suffix;
    $resResetUsr = UserModel::create([
        'username' => $resetUserUsername,
        'password' => 'SandiUserAwal99#',
        'nama' => 'User Uji Tanpa Reset',
        'role' => 'USER',
        'is_active' => 1,
    ]);
    $resetUserId = (string)($resResetUsr['id'] ?? '');
    if ($resetUserId !== '') $createdIds[] = $resetUserId;

    // Bersihkan throttle & sesi
    AuthHelper::resetLoginThrottle();
    $_SESSION = [];

    // 6a. USER salah sandi 3x TIDAK BOLEH memicu secret word reset
    for ($i = 1; $i <= 3; $i++) {
        $uFail = AuthHelper::login($resetUserUsername, 'SalahTotal_' . $i);
        check(($uFail['success'] ?? true) === false && empty($uFail['admin_reset_required']), "USER gagal login ke-{$i} tidak memicu secret word reset");
    }
    check(AuthHelper::getAdminResetChallenge() === null, 'Challenge secret word tetap kosong untuk role USER');

    // Bersihkan throttle sebelum uji ADMIN
    AuthHelper::resetLoginThrottle();
    $_SESSION = [];

    // 6b. ADMIN salah sandi 1x & 2x belum memicu challenge
    $aFail1 = AuthHelper::login($resetAdminUsername, 'SalahAdmin1');
    check(($aFail1['success'] ?? true) === false && empty($aFail1['admin_reset_required']) && AuthHelper::getAdminResetChallenge() === null, 'ADMIN gagal login ke-1 belum memicu secret word');

    $aFail2 = AuthHelper::login($resetAdminUsername, 'SalahAdmin2');
    check(($aFail2['success'] ?? true) === false && empty($aFail2['admin_reset_required']) && AuthHelper::getAdminResetChallenge() === null, 'ADMIN gagal login ke-2 belum memicu secret word');

    // 6c. ADMIN salah sandi ke-3 HARUS memicu secret word challenge!
    $aFail3 = AuthHelper::login($resetAdminUsername, 'SalahAdmin3');
    $challenge = AuthHelper::getAdminResetChallenge();
    check(
        ($aFail3['success'] ?? true) === false
        && !empty($aFail3['admin_reset_required'])
        && is_array($challenge)
        && ($challenge['user_id'] ?? '') === $resetAdminId,
        'ADMIN gagal login ke-3 memicu challenge secret word reset'
    );

    // 6d. Secret word salah ditolak
    $wrongSecret = AuthHelper::executeAdminSecretReset('kata_rahasia_salah');
    check(($wrongSecret['success'] ?? true) === false && AuthHelper::getAdminResetChallenge() !== null, 'Secret word salah ditolak dan challenge tetap aktif');

    // 6e. Secret word benar mengeksekusi reset ke Kendari2026# tanpa menampilkan password di pesan notifikasi
    $okSecret = AuthHelper::executeAdminSecretReset($secretPlain);
    $okMsg = (string)($okSecret['message'] ?? '');
    check(($okSecret['success'] ?? false) === true, 'Secret word benar berhasil mengeksekusi reset kata sandi admin');
    check(
        $okMsg !== ''
        && !str_contains($okMsg, DEFAULT_RESET_PASSWORD)
        && !str_contains($okMsg, 'Kendari2026#')
        && stripos($okMsg, $secretPlain) === false,
        'Notifikasi pasca-reset TIDAK menampilkan kata sandi default maupun secret word',
        'Msg: ' . $okMsg
    );
    check(AuthHelper::getAdminResetChallenge() === null, 'Challenge secret word dibersihkan setelah reset berhasil');

    // 6f. Login kembali dengan Kendari2026# berhasil langsung tanpa wajib ganti sandi
    $loginAfterReset = AuthHelper::login($resetAdminUsername, 'Kendari2026#');
    check(
        ($loginAfterReset['success'] ?? false) === true
        && ($loginAfterReset['user']['role'] ?? '') === 'ADMIN'
        && AuthHelper::mustChangePassword() === false,
        'Admin langsung bisa login dengan sandi default hasil reset tanpa paksaan ganti sandi'
    );
} finally {
    // Cleanup akun dan berkas sementara
    foreach ($createdIds as $cid) {
        $db->prepare("DELETE FROM `Users` WHERE id = :id")->execute([':id' => $cid]);
    }
    foreach ($createdFiles as $cf) {
        if (is_file($cf)) @unlink($cf);
    }
    AuthHelper::resetLoginThrottle();
    $_SESSION = [];
}

echo "\nTotal: {$total}, Lolos: {$passed}\n";
exit($passed === $total ? 0 : 1);
