<?php
/**
 * QR-SAR - Operasi SAR Controller
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 * 
 * Handles Operasi SAR lifecycle, detail operational views, RENOPS & SITREP forms, and print actions.
 */

declare(strict_types=1);

class OperasiController extends BaseController {
    /**
     * Handle operation list, actions (create, update, close, rollback, delete), and official printouts.
     */
    public function index(): void {
        $action = $_GET['action'] ?? null;
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($method === 'POST') {
            $this->requireLogin();

            if (!$this->validateCsrf($_POST['csrf_token'] ?? null)) {
                $this->setFlash('error', 'Token keamanan CSRF tidak valid atau sesi Anda telah kedaluwarsa. Silakan muat ulang halaman.');
                $this->redirect('/index.php');
            }

            if (!empty($_POST['detail_alut_json']) && is_string($_POST['detail_alut_json'])) {
                $decodedA = json_decode($_POST['detail_alut_json'], true);
                if (is_array($decodedA)) {
                    $_POST['detail_alut'] = $decodedA;
                }
            }
            if (!empty($_POST['detail_unsur_json']) && is_string($_POST['detail_unsur_json'])) {
                $decodedU = json_decode($_POST['detail_unsur_json'], true);
                if (is_array($decodedU)) {
                    $_POST['detail_unsur'] = $decodedU;
                }
            }

            if ($action === 'create' || $action === 'update') {
                $errors = [];
                if (empty(trim($_POST['nama_operasi'] ?? ''))) {
                    $errors[] = 'Nama Operasi SAR wajib diisi.';
                }
                if (empty(trim($_POST['nama_pelapor'] ?? ''))) {
                    $errors[] = 'Nama Pelapor wajib diisi.';
                }
                if (empty(trim($_POST['lokasi_kejadian'] ?? ''))) {
                    $errors[] = 'Lokasi Kejadian wajib diisi.';
                }
                if (empty(trim($_POST['waktu_lapor'] ?? ''))) {
                    $errors[] = 'Waktu Lapor Kejadian wajib diisi.';
                }
                if (empty(trim($_POST['waktu_berangkat'] ?? ''))) {
                    $errors[] = 'Waktu Tim Berangkat wajib diisi.';
                }

                if (!empty($errors)) {
                    $_SESSION['old_input'] = $_POST;
                    $this->setFlash('error', implode(' ', $errors));
                    if ($action === 'update') {
                        $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
                        $this->redirect("/operasi_form.php?id={$id}");
                    } else {
                        $this->redirect('/operasi_form.php');
                    }
                }
            }

            if ($action === 'create') {
                try {
                    $newId = OperasiModel::create($_POST);
                    unset($_SESSION['old_input']);
                    $this->setFlash('success', 'Laporan Awal Operasi SAR berhasil diterbitkan!');
                    $this->redirect("/operasi_detail.php?id={$newId}");
                } catch (Throwable $e) {
                    $_SESSION['old_input'] = $_POST;
                    $this->setFlash('error', 'Gagal membuat operasi SAR: ' . $e->getMessage());
                    $this->redirect('/operasi_form.php');
                }
            }

            if ($action === 'update') {
                $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
                try {
                    OperasiModel::update($id, $_POST);
                    unset($_SESSION['old_input']);
                    $this->setFlash('success', 'Data Operasi SAR berhasil diperbarui!');
                    $this->redirect("/operasi_detail.php?id={$id}");
                } catch (Throwable $e) {
                    $_SESSION['old_input'] = $_POST;
                    $this->setFlash('error', 'Gagal memperbarui operasi SAR: ' . $e->getMessage());
                    $this->redirect("/operasi_form.php?id={$id}");
                }
            }

            if ($action === 'close') {
                $id = (int)($_POST['id_operasi'] ?? $_POST['id'] ?? 0);
                if ($id <= 0) {
                    $this->setFlash('error', 'Gagal menutup operasi: ID Operasi tidak valid.');
                    $this->redirect('/operasi.php');
                }

                $waktu = $_POST['waktu_selesai'] ?? date('Y-m-d H:i:s');
                try {
                    $ok = OperasiModel::closeOperation($id, $waktu);
                    if ($ok) {
                        $this->setFlash('success', 'Operasi SAR resmi dinyatakan DITUTUP.');
                    } else {
                        $this->setFlash('error', 'Gagal menutup operasi SAR: Sistem basis data gagal memperbarui status.');
                    }
                } catch (Throwable $e) {
                    $this->setFlash('error', 'Gagal menutup operasi SAR: ' . $e->getMessage());
                }

                $this->redirect("/operasi_detail.php?id={$id}");
            }

            if ($action === 'rollback') {
                $id = (int)($_POST['id_operasi'] ?? $_POST['id'] ?? 0);
                if ($id <= 0) {
                    $this->setFlash('error', 'Gagal membuka kembali operasi: ID Operasi tidak ditemukan.');
                    $this->redirect('/operasi.php');
                }

                $waktuReopen = $_POST['waktu_reopen'] ?? date('Y-m-d H:i:s');
                try {
                    $ok = OperasiModel::rollbackPenutupan($id, $waktuReopen);
                    if ($ok) {
                        $this->setFlash('success', 'Operasi SAR berhasil dibuka kembali (Status: OPEN). Perhitungan hari operasional lanjutan (misal: H7 + 1, dst.) dihitung sejak tanggal pembukaan kembali.');
                    } else {
                        $this->setFlash('error', 'Gagal membuka kembali operasi SAR: Sistem basis data gagal memperbarui status.');
                    }
                } catch (Throwable $e) {
                    $this->setFlash('error', 'Gagal membuka kembali operasi: ' . $e->getMessage());
                }

                $this->redirect("/operasi_detail.php?id={$id}");
            }

            // Delete action (POST Only with CSRF)
            if ($action === 'delete') {
                if (!AuthHelper::canDelete()) {
                    $this->setFlash('error', 'Akses ditolak: Hanya administrator yang dapat menghapus data operasi.');
                    $this->redirect('/index.php');
                }

                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) {
                    try {
                        OperasiModel::delete($id);
                        $this->setFlash('success', 'Laporan Awal Operasi SAR berhasil dihapus secara permanen.');
                    } catch (Throwable $e) {
                        $this->setFlash('error', 'Gagal menghapus operasi: ' . $e->getMessage());
                    }
                }
                $this->redirect('/index.php');
            }
        }

        // Reject any unauthenticated or GET-based delete attempt
        if ($action === 'delete' && $method !== 'POST') {
            $this->setFlash('error', 'Metode tidak diizinkan: Penghapusan data harus melalui metode POST dengan token keamanan.');
            $this->redirect('/index.php');
        }

        // Editor draf Dokumen RENOPS (format SMEAC)
        if ($action === 'renops_dokumen') {
            $this->renopsDokumen();
            return;
        }

        // Dokumen RENOPS terbit, publik read-only (K12): hanya lewat token versi, tanpa login
        if ($action === 'renops_publik') {
            $this->renopsPublik();
            return;
        }

        // Lembar cetak memuat data pribadi korban & pelapor: wajib login (publik cukup lewat portal verifikasi)
        if (str_starts_with((string)$action, 'print_') || str_starts_with((string)$action, 'cetak_')) {
            $this->requireLogin();
        }

        // Lembar Berkas Cetak Resmi Rencana Operasi SAR (RENOPS)
        if ($action === 'print_renops' || $action === 'cetak_renops') {
            $this->printRenops();
            return;
        }

        // Portal Verifikasi Keabsahan Dokumen Operasi SAR (Akses Publik / Scanner Barcode)
        if ($action === 'verify' || $action === 'verify_doc') {
            $this->verifyDocument();
            return;
        }

        // Default: show list
        $this->requireLogin();
        $this->render('dashboard', [], [
            'title' => 'Daftar Operasi SAR',
            'route' => 'operasi',
            'navbarTitle' => 'Daftar Operasi SAR Kendari'
        ]);
    }

    /**
     * Editor draf Dokumen RENOPS (edit langsung di kertas). Draf, foto, terbit tetap boleh pada operasi CLOSED (K16).
     */
    protected function renopsDokumen(): void {
        $this->requireLogin();
        $idRenops = (int)($_GET['id_renops'] ?? 0);
        $renops = $idRenops > 0 ? RenopsModel::getById($idRenops) : null;
        $doc = $renops ? RenopsDokumenService::resolve($idRenops) : null;
        if (!$renops || !$doc) {
            $this->setFlash('error', 'Data Rencana Operasi (RENOPS) tidak ditemukan.');
            $this->redirect('/index.php');
        }
        $draft = RenopsDokumenModel::getDraft($idRenops);
        $this->render('operasi/renops_dokumen', [
            'idRenops' => $idRenops,
            'idOperasi' => (int)$renops['id_operasi'],
            'doc' => $doc,
            'rev' => (int)($draft['rev'] ?? 0),
            'terbit' => RenopsDokumenModel::getLatestTerbit($idRenops),
            'belumTerbit' => RenopsDokumenPublisher::hasUnpublishedChanges($idRenops),
        ], [
            'title' => 'Editor Dokumen RENOPS',
            'route' => 'operasi_detail',
            'navbarTitle' => 'EDITOR DOKUMEN RENOPS',
        ]);
    }

    /**
     * Cetak internal Dokumen RENOPS: versi TERBIT terakhir (tanpa id_renops: RENOPS terakhir operasi).
     * Belum terbit, atau ?draf=1: pratinjau draf (watermark DRAF, tanpa QR) agar tetap bisa dicetak/diunduh.
     */
    protected function printRenops(): void {
        $idRenops = (int)($_GET['id_renops'] ?? 0);
        if ($idRenops <= 0) {
            $list = RenopsModel::getByOperasiId((int)($_GET['id'] ?? 0));
            $idRenops = $list ? (int)end($list)['id_renops'] : 0;
        }
        $editorUrl = BASE_URL . '/operasi.php?action=renops_dokumen&id_renops=' . $idRenops;
        $versi = RenopsDokumenModel::getLatestTerbit($idRenops);
        if (!$versi || !empty($_GET['draf'])) {
            $this->renderRenopsDraf($idRenops, $editorUrl);
            return;
        }
        $this->renderRenopsVersi($versi, false, $editorUrl);
    }

    /** Pratinjau cetak draf (belum/tanpa QR): isi terkini hasil resolve(), hanya untuk pengguna login. */
    private function renderRenopsDraf(int $idRenops, string $editorUrl): void {
        $this->requireLogin();
        $doc = $idRenops > 0 ? RenopsDokumenService::resolve($idRenops) : null;
        if (!$doc) {
            $this->setFlash('error', 'Data Rencana Operasi (RENOPS) tidak ditemukan.');
            $this->redirect('/index.php');
        }
        $mode = 'pratinjau';
        $qrUrl = null;
        $versiLabel = 'DRAF';
        $isPublic = false;
        $backUrl = $editorUrl;
        $digantikanOleh = null;
        require VIEWS_DIR . '/operasi/renops_smeac_print.php';
    }

    /**
     * Dokumen RENOPS publik read-only (K12): versi TERBIT/DIGANTIKAN pemilik token. Tidak pernah draf.
     */
    protected function renopsPublik(): void {
        // Memuat data pribadi korban & pelapor: jangan diindeks, jangan disimpan cache
        header('X-Robots-Tag: noindex, nofollow');
        header('Cache-Control: no-store');
        $token = trim((string)($_GET['token'] ?? ''));
        $versi = preg_match('/^[0-9A-F]{16}$/', $token) ? RenopsDokumenModel::findByToken($token) : null;
        if (!$versi || !in_array($versi['status'], ['TERBIT', 'DIGANTIKAN'], true)) {
            $this->renderVerifyInvalid('RENOPS [publik]', $token);
            return;
        }
        $terbaru = $versi['status'] === 'DIGANTIKAN' ? RenopsDokumenModel::getLatestTerbit((int)$versi['id_renops']) : null;
        $this->renderRenopsVersi($versi, true, null, $terbaru);
    }

    /** Halaman cetak SMEAC (konten beku) untuk satu versi terbit. */
    private function renderRenopsVersi(array $versi, bool $isPublic, ?string $backUrl, ?array $terbaru = null): void {
        $doc = $versi['konten'];
        $qrUrl = RenopsDokumenPublisher::verifyUrl($versi);
        $versiLabel = 'v' . (int)$versi['versi'];
        $digantikanOleh = $terbaru
            ? ['versi' => (int)$terbaru['versi'], 'url' => RenopsDokumenPublisher::publicUrl((string)$terbaru['token'])]
            : null;
        require VIEWS_DIR . '/operasi/renops_smeac_print.php';
    }

    /**
     * Hasil verifikasi token Dokumen RENOPS format baru, hanya bila token milik RENOPS $refId pada operasi $idOps.
     * null = token tidak dikenal (lanjut ke jalur QR lama / tidak valid).
     * @return array{status:'SAH'|'DIUBAH', versi:int, latest_versi:int, open_url:string, issued_at:string}|null
     */
    public static function resolveRenopsVerification(string $token, int $idOps, int $refId): ?array {
        $token = strtoupper(trim($token));
        $versi = preg_match('/^[0-9A-F]{16}$/', $token) ? RenopsDokumenModel::findByToken($token) : null;
        if (!$versi || (int)$versi['id_renops'] !== $refId || (int)$versi['id_operasi'] !== $idOps) {
            return null;
        }
        $terbaru = match ($versi['status']) {
            'TERBIT' => $versi,
            'DIGANTIKAN' => RenopsDokumenModel::getLatestTerbit($refId),
            default => null,
        };
        if (!$terbaru) {
            return null;
        }
        // Penandatangan beku dari versi yang dipindai (DIUBAH: versi lama itu), bukan SMC default saat ini.
        $p = $versi['konten']['pengesahan'] ?? [];
        $pengesahan = [];
        foreach (['kota', 'tanggal', 'smc_nama', 'smc_pangkat', 'smc_nip'] as $k) {
            $pengesahan[$k] = (string)($p[$k] ?? '');
        }
        return [
            'status' => $versi['status'] === 'TERBIT' ? 'SAH' : 'DIUBAH',
            'versi' => (int)$versi['versi'],
            'latest_versi' => (int)$terbaru['versi'],
            'open_url' => RenopsDokumenPublisher::publicUrl((string)$terbaru['token']),
            'issued_at' => (string)$versi['issued_at'],
            'pengesahan' => $pengesahan,
        ];
    }

    private function renderVerifyInvalid(string $invalidRef, string $token): void {
        http_response_code(404);
        $this->render('bra/verify_invalid', [
            'invalidTitle' => 'Naskah Dokumen Operasi SAR Tidak Terverifikasi',
            'invalidRef' => $invalidRef,
            'invalidToken' => htmlspecialchars($token, ENT_QUOTES, 'UTF-8'),
            'verificationStatus' => 'INVALID_TOKEN',
            'verificationMessage' => 'Tanda tangan digital atau token verifikasi tidak valid. Dokumen operasi SAR ini diduga mengalami manipulasi URL atau tidak terdaftar dalam pangkalan data resmi Basarnas KPP Kendari.'
        ], [
            'layout' => 'public',
            'title' => 'Verifikasi Dokumen Tidak Sah'
        ]);
    }

    /**
     * Portal Verifikasi Publik Resmi Keabsahan Dokumen Operasi SAR
     */
    protected function verifyDocument(): void {
        // Hanya dokumen RENOPS yang ber-QR; tipe lain (Laporan Awal/LAPBANG/Penutupan) tidak diterbitkan lagi → tidak valid.
        $type = DocVerifyHelper::normalizeType($_GET['type'] ?? '');
        $idOps = (int)($_GET['id'] ?? 0);
        $refId = (int)($_GET['ref'] ?? 0);
        $token = trim((string)($_GET['token'] ?? ''));

        $operasi = ($type !== null && $idOps > 0) ? OperasiModel::getById($idOps) : null;
        $docData = null;
        if ($operasi) {
            if ($refId > 0) {
                $docData = RenopsModel::getById($refId);
            } else {
                $list = RenopsModel::getByOperasiId($idOps);
                $docData = !empty($list) ? end($list) : null;
            }
        }

        $isValid = false;
        $renopsVerify = null;
        if ($operasi && $token !== '') {
            $renopsVerify = self::resolveRenopsVerification($token, $idOps, $refId);
            $isValid = $renopsVerify !== null || DocVerifyHelper::verifyToken($operasi, $docData, $token);
            // QR RENOPS portrait lama yang sah, tetapi RENOPS itu sudah punya versi TERBIT format baru
            $terbaru = $isValid && $renopsVerify === null
                ? RenopsDokumenModel::getLatestTerbit((int)($docData['id_renops'] ?? 0)) : null;
            if ($terbaru) {
                $renopsVerify = [
                    'status' => 'DIUBAH', 'versi' => 0, 'latest_versi' => (int)$terbaru['versi'],
                    'open_url' => RenopsDokumenPublisher::publicUrl((string)$terbaru['token']), 'issued_at' => '',
                ];
            }
        }

        if (!$isValid) {
            $this->renderVerifyInvalid("OPS-{$idOps}" . ($refId > 0 ? "/REF-{$refId}" : "") . ' [' . ($type ?? 'tidak dikenal') . ']', $token);
            return;
        }

        $settings = [];
        try {
            if (class_exists('SettingsModel')) {
                $settings = SettingsModel::get();
            }
        } catch (Throwable) {
            $settings = [];
        }
        $satkerKode = $settings['satker_kode'] ?? 'SAR-304';

        $defaultSmc = null;
        try {
            if (class_exists('MasterModel')) {
                $defaultSmc = MasterModel::getDefaultSmc();
            }
        } catch (Throwable) {
            $defaultSmc = null;
        }

        $docTitle = DocVerifyHelper::getDocTitle($docData);
        $docNumber = DocVerifyHelper::getDocNumber($operasi, $docData, $satkerKode);
        $docTimestamp = ($renopsVerify['issued_at'] ?? '') !== '' ? $renopsVerify['issued_at'] : DocVerifyHelper::getDocTimestamp($docData);
        $smcName = isset($renopsVerify['pengesahan'])
            ? (mb_strtoupper(trim($renopsVerify['pengesahan']['smc_nama']), 'UTF-8') ?: '-')
            : DocVerifyHelper::getSmcName($defaultSmc, $docData);
        $printUrl = DocVerifyHelper::getPrintUrl($idOps, (int)($docData['id_renops'] ?? 0));

        $this->render('operasi/verify_public', [
            'type' => $type,
            'idOps' => $idOps,
            'refId' => $refId,
            'token' => $token,
            'operasi' => $operasi,
            'docData' => $docData,
            'docTitle' => $docTitle,
            'docNumber' => $docNumber,
            'docTimestamp' => $docTimestamp,
            'smcName' => $smcName,
            'defaultSmc' => $defaultSmc,
            'satkerKode' => $satkerKode,
            'printUrl' => $printUrl,
            'renopsVerify' => $renopsVerify
        ], [
            'layout' => 'public',
            'title' => "Verifikasi Keabsahan {$docTitle}"
        ]);
    }

    /**
     * Display or prepare Operasi SAR creation/edit form.
     */
    public function form(): void {
        $this->requireLogin();

        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
        $operasi = $id ? OperasiModel::getById($id) : null;

        $oldInput = $_SESSION['old_input'] ?? null;
        unset($_SESSION['old_input']);

        if ($oldInput && is_array($oldInput)) {
            if (!$operasi) {
                $operasi = $oldInput;
            } else {
                $operasi = array_merge($operasi, $oldInput);
            }
        }

        $this->render('operasi/form', [
            'operasi' => $operasi
        ], [
            'title' => $operasi ? "Edit Operasi - {$operasi['nama_operasi']}" : "Inisiasi Laporan Awal Operasi SAR",
            'route' => 'operasi_form',
            'navbarTitle' => $operasi ? "Edit Operasi SAR" : "Inisiasi Laporan Awal"
        ]);
    }

    /**
     * Display full operational control center / detail view of an Operasi SAR.
     */
    public function detail(): void {
        $this->requireLogin();

        $id = (int)($_GET['id'] ?? 0);

        if (!$id) {
            $this->setFlash('error', 'ID Operasi tidak valid.');
            $this->redirect('/index.php');
        }

        $operasi = OperasiModel::getById($id);

        if (!$operasi) {
            $this->setFlash('error', 'Operasi SAR tidak ditemukan atau telah dihapus.');
            $this->redirect('/index.php');
        }

        $gisBundle = GisModel::getOperationBundle($id, true);

        $this->render('operasi/detail', [
            'id' => $id,
            'operasi' => $operasi,
            'gisBundle' => $gisBundle
        ], [
            'title' => 'Operasi: ' . htmlspecialchars($operasi['nama_operasi']),
            'route' => 'operasi_detail',
            'useGis' => true,
            'navbarTitle' => 'PUSAT KENDALI OPERASI SAR',
            'navbarSubtitle' => 'KANTOR PENCARIAN DAN PERTOLONGAN (KPP) KELAS A KENDARI - BASARNAS'
        ]);
    }

    /**
     * Handle RENOPS form view and submission.
     */
    public function renopsForm(): void {
        $this->requireLogin();

        $idOperasi = isset($_GET['id_operasi']) ? (int)$_GET['id_operasi'] : (int)($_POST['id_operasi'] ?? 0);
        if ($idOperasi <= 0) {
            $this->setFlash('error', 'ID Operasi SAR tidak valid.');
            $this->redirect('/index.php');
        }

        $operasi = OperasiModel::getById($idOperasi);
        if (!$operasi) {
            $this->setFlash('error', 'Data Operasi SAR tidak ditemukan.');
            $this->redirect('/index.php');
        }

        $idRenops = isset($_GET['id_renops']) ? (int)$_GET['id_renops'] : (int)($_POST['id_renops'] ?? 0);
        $editMode = ($idRenops > 0);
        $renops = $editMode ? RenopsModel::getById($idRenops) : null;

        // RENOPS wajib milik operasi pada URL, agar tidak bisa diedit lewat operasi lain
        if ($editMode && (!$renops || (int)($renops['id_operasi'] ?? 0) !== $idOperasi)) {
            $this->setFlash('error', 'Data Rencana Operasi (RENOPS) tidak ditemukan.');
            $this->redirect("/operasi_detail.php?id={$idOperasi}&tab=renops");
        }

        // Check if operation is CLOSED
        if ($operasi['status'] === 'CLOSED') {
            $this->setFlash('error', 'Operasi SAR telah berstatus DITUTUP (CLOSED). Data RENOPS tidak dapat ditambahkan atau diubah kecuali operasi dibuka kembali (Rollback).');
            $this->redirect("/operasi_detail.php?id={$idOperasi}&tab=renops");
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'POST') {
            if (!$this->validateCsrf($_POST['csrf_token'] ?? null)) {
                $this->setFlash('error', 'Token keamanan CSRF tidak valid. Silakan coba lagi.');
                $this->redirect("/renops_form.php?id_operasi={$idOperasi}" . ($editMode ? "&id_renops={$idRenops}" : ""));
            }

            if (!empty($_POST['detail_alut_json']) && is_string($_POST['detail_alut_json'])) {
                $decodedA = json_decode($_POST['detail_alut_json'], true);
                if (is_array($decodedA)) {
                    $_POST['detail_alut'] = $decodedA;
                }
            }
            if (!empty($_POST['detail_unsur_json']) && is_string($_POST['detail_unsur_json'])) {
                $decodedU = json_decode($_POST['detail_unsur_json'], true);
                if (is_array($decodedU)) {
                    $_POST['detail_unsur'] = $decodedU;
                }
            }

            // Process SRUs from input arrays if submitted
            $_POST['srus'] = RenopsModel::normalizeInputSrus(is_array($_POST['srus'] ?? null) ? $_POST['srus'] : []);

            $satuanJarak = in_array(strtoupper(trim((string)($_POST['satuan_jarak'] ?? 'NM'))), ['KM', 'M', 'NM']) ? strtoupper(trim((string)$_POST['satuan_jarak'])) : 'NM';
            $_POST['satuan_jarak'] = $satuanJarak;

            $tglInput = $_POST['tanggal_laporan'] ?? date('Y-m-d');
            $_POST['hari_ke'] = TemplateHelper::hitungHariKe($operasi, $tglInput);

            try {
                if ($editMode) {
                    RenopsModel::update($idRenops, $_POST);
                    $this->setFlash('success', 'Rencana Operasi SAR (RENOPS) berhasil diperbarui!');
                } else {
                    $_POST['id_operasi'] = $idOperasi;
                    RenopsModel::create($_POST);
                    $this->setFlash('success', 'Rencana Operasi SAR (RENOPS) berhasil diterbitkan!');
                }
                $this->redirect("/operasi_detail.php?id={$idOperasi}&tab=renops");
            } catch (Throwable $e) {
                $this->setFlash('error', 'Gagal menyimpan RENOPS: ' . $e->getMessage());
                $this->redirect("/renops_form.php?id_operasi={$idOperasi}" . ($editMode ? "&id_renops={$idRenops}" : ""));
            }
        }

        // Master data & bundle
        $masterTim = MasterModel::getTim();
        $masterAlut = MasterModel::getAlut();
        $gisBundle = GisModel::getOperationBundle($idOperasi, true);
        $baseDateOps = TemplateHelper::getWaktuMulaiAktual($operasi);

        if ($editMode) {
            $tanggalLaporan = $renops['tanggal_laporan'] ?? date('Y-m-d');
            $hariKe = TemplateHelper::hitungHariKe($operasi, $tanggalLaporan);
            $waktuBriefing = !empty($renops['waktu_briefing']) ? date('Y-m-d\TH:i', strtotime($renops['waktu_briefing'])) : date('Y-m-d\T07:00');
            $waktuMulaiOps = !empty($renops['waktu_mulai_ops']) ? date('Y-m-d\TH:i', strtotime($renops['waktu_mulai_ops'])) : date('Y-m-d\T07:30');
            $cuaca = $renops['cuaca'] ?? ($operasi['cuaca'] ?: 'Cerah Berawan');
            $initialUnsurData = !empty($renops['detail_unsur']) ? $renops['detail_unsur'] : ($operasi['detail_unsur'] ?? []);
            $initialAlutData = !empty($renops['detail_alut']) ? $renops['detail_alut'] : ($operasi['detail_alut'] ?? []);
            $initialSrus = $renops['srus'] ?? [];
        } else {
            $nextRenops = HierarchyHelper::getNextRenopsDefaults($operasi);
            $tanggalLaporan = $nextRenops['target_date_str'] ?? date('Y-m-d');
            $hariKe = $nextRenops['hari_ke'] ?? TemplateHelper::hitungHariKe($operasi, $tanggalLaporan);
            $waktuBriefing = $tanggalLaporan . 'T07:00';
            $waktuMulaiOps = $tanggalLaporan . 'T07:30';
            $cuaca = $nextRenops['inherited_cuaca'] ?? ($operasi['cuaca'] ?: 'Cerah Berawan');
            $initialUnsurData = !empty($nextRenops['inherited_unsur']) ? $nextRenops['inherited_unsur'] : (!empty($operasi['detail_unsur']) ? $operasi['detail_unsur'] : []);
            $initialAlutData = !empty($nextRenops['inherited_alut']) ? $nextRenops['inherited_alut'] : (!empty($operasi['detail_alut']) ? $operasi['detail_alut'] : []);

            // Auto-detect initial SRUs from GIS plotting if available
            $plottingPolys = !empty($gisBundle['polygons']) ? $gisBundle['polygons'] : [];
            $plottingPatterns = !empty($gisBundle['searchPatterns']) ? $gisBundle['searchPatterns'] : [];

            if (!empty($plottingPolys)) {
                $initialSrus = [];
                $idx = 0;
                foreach ($plottingPolys as $poly) {
                    $idx++;
                    $name = !empty($poly['name']) ? $poly['name'] : ("Sektor " . chr(64 + $idx));
                    $shapeType = $poly['shapeType'] ?? 'polygon';
                    $areaNm2 = 0.0;
                    $coordsText = '';

                    if ($shapeType === 'circle' && !empty($poly['radiusNm'])) {
                        $r = (float)$poly['radiusNm'];
                        $areaNm2 = M_PI * $r * $r;
                        $cLat = isset($poly['centerLat']) ? (float)$poly['centerLat'] : (float)($poly['points'][0]['lat'] ?? 0);
                        $cLng = isset($poly['centerLng']) ? (float)$poly['centerLng'] : (float)($poly['points'][0]['lng'] ?? 0);
                        $dmsCenter = TemplateHelper::formatDmsPair($cLat, $cLng);
                        $rKm = number_format($r * 1.852, 2, '.', '');
                        $coordsText = "Pusat Datum (LKP): {$dmsCenter}\nRadius Sektor: {$r} NM ({$rKm} km)\nLuas Area: " . number_format($areaNm2, 2, '.', '') . " Nm²";
                    } elseif (($shapeType === 'rectangle' || $shapeType === 'box') && !empty($poly['boxWidthNm']) && !empty($poly['boxHeightNm'])) {
                        $w = (float)$poly['boxWidthNm'];
                        $h = (float)$poly['boxHeightNm'];
                        $areaNm2 = $w * $h;
                        $coordLines = [];
                        if (!empty($poly['points']) && is_array($poly['points'])) {
                            foreach ($poly['points'] as $pIdx => $pt) {
                                $lbl = !empty($pt['label']) ? $pt['label'] : ("Titik " . chr(65 + $pIdx));
                                $dms = TemplateHelper::formatDmsPair($pt['lat'] ?? 0, $pt['lng'] ?? 0);
                                $coordLines[] = "{$lbl}: {$dms}";
                            }
                        }
                        $coordsText = implode("\n", $coordLines);
                    } else {
                        $areaNm2 = !empty($poly['areaNm2']) ? (float)$poly['areaNm2'] : GisModel::calculatePolygonAreaNm2($poly['points'] ?? []);
                        $coordLines = [];
                        if (!empty($poly['points']) && is_array($poly['points'])) {
                            foreach ($poly['points'] as $pIdx => $pt) {
                                $lbl = !empty($pt['label']) ? $pt['label'] : ("Titik " . chr(65 + $pIdx));
                                $dms = TemplateHelper::formatDmsPair($pt['lat'] ?? 0, $pt['lng'] ?? 0);
                                $coordLines[] = "{$lbl}: {$dms}";
                            }
                        }
                        $coordsText = implode("\n", $coordLines);
                    }

                    $areaFormatted = $areaNm2 > 0 ? (number_format($areaNm2, 2, '.', '') . ' Nm²') : '';

                    // Match pattern
                    $matchedPattern = null;
                    foreach ($plottingPatterns as $pat) {
                        if (($pat['polygonId'] ?? '') === ($poly['id'] ?? '') || (!empty($pat['polygonName']) && !empty($poly['name']) && $pat['polygonName'] === $poly['name'])) {
                            $matchedPattern = $pat;
                            break;
                        }
                    }

                    $pola = 'Parallel Track Search';
                    $totalSapuan = '';
                    if ($matchedPattern) {
                        $ptType = $matchedPattern['patternType'] ?? 'parallel_track';
                        if ($ptType === 'creeping_line') $pola = 'Creeping Line Search';
                        elseif ($ptType === 'e_sar' || $ptType === 'sector_search') $pola = 'Pola Pencarian E-SAR';
                        elseif ($ptType === 'track_line') $pola = 'Track Line Search';
                        else $pola = 'Parallel Track Search';

                        $legs = !empty($matchedPattern['legsCount']) ? (int)$matchedPattern['legsCount'] : 6;
                        $sp = !empty($matchedPattern['trackSpacingNm']) ? number_format((float)$matchedPattern['trackSpacingNm'], 2, '.', '') : '1.00';
                        $dist = !empty($matchedPattern['totalDistanceNm']) ? number_format((float)$matchedPattern['totalDistanceNm'], 1, '.', '') : '-';
                        if ($ptType === 'e_sar' || $ptType === 'sector_search') {
                            $totalSapuan = "{$legs} Personel E-SAR (Spasi: {$sp} Nm, Total Lintasan: {$dist} Nm)";
                        } else {
                            $totalSapuan = "{$legs} Sapuan / Lintasan (Spasi: {$sp} Nm, Total Lintasan: {$dist} Nm)";
                        }
                    }

                    $taskDetail = "Sektor: {$name}" . ($areaFormatted ? " ({$areaFormatted})" : "");
                    if ($coordsText) {
                        $taskDetail .= "\nBatas Koordinat:\n" . $coordsText;
                    }

                    $initialSrus[] = [
                        'nama_sru' => "SRU {$idx} - {$name}",
                        'koordinat_area' => $taskDetail,
                        'luas_area' => $areaFormatted,
                        'pola_pencarian' => $pola,
                        'total_sapuan' => $totalSapuan
                    ];
                }
            } else {
                $lkpDesc = $operasi['lokasi_kejadian'] ?: 'LKP';
                $koorDesc = $operasi['koordinat'] ?: '';
                $initialSrus = [
                    [
                        'nama_sru' => 'SRU 1 Laut / Darat',
                        'koordinat_area' => "Penyisiran di sekitar LKP ({$lkpDesc})\nKoordinat: {$koorDesc}",
                        'luas_area' => '',
                        'pola_pencarian' => 'Parallel Track Search',
                        'total_sapuan' => ''
                    ]
                ];
            }
        }

        $pageTitle = ($editMode ? "Edit RENOPS {$hariKe}" : "Buat RENOPS {$hariKe}") . " - {$operasi['nama_operasi']}";
        $navbarTitle = $editMode ? "Edit Rencana Operasi ({$hariKe})" : "Penerbitan Rencana Operasi ({$hariKe})";

        $viewData = [
            'idOperasi' => $idOperasi,
            'operasi' => $operasi,
            'idRenops' => $idRenops,
            'editMode' => $editMode,
            'renops' => $renops,
            'masterTim' => $masterTim,
            'masterAlut' => $masterAlut,
            'gisBundle' => $gisBundle,
            'baseDateOps' => $baseDateOps,
            'tanggalLaporan' => $tanggalLaporan,
            'hariKe' => $hariKe,
            'waktuBriefing' => $waktuBriefing,
            'waktuMulaiOps' => $waktuMulaiOps,
            'cuaca' => $cuaca,
            'initialUnsurData' => $initialUnsurData,
            'initialAlutData' => $initialAlutData,
            'initialSrus' => $initialSrus,
            'pageTitle' => $pageTitle,
            'navbarTitle' => $navbarTitle
        ];

        $this->render('operasi/renops_form', $viewData, [
            'title' => $pageTitle,
            'route' => 'operasi_detail',
            'navbarTitle' => $navbarTitle
        ]);
    }

    /**
     * Handle SITREP (Lapbang) form view and submission.
     */
    public function sitrepForm(): void {
        $this->requireLogin();

        $idOperasi = isset($_GET['id_operasi']) ? (int)$_GET['id_operasi'] : (int)($_POST['id_operasi'] ?? 0);
        if ($idOperasi <= 0) {
            $this->setFlash('error', 'ID Operasi SAR tidak valid.');
            $this->redirect('/index.php');
        }

        $operasi = OperasiModel::getById($idOperasi);
        if (!$operasi) {
            $this->setFlash('error', 'Data Operasi SAR tidak ditemukan.');
            $this->redirect('/index.php');
        }

        $idLaporan = isset($_GET['id_laporan']) ? (int)$_GET['id_laporan'] : (int)($_POST['id_laporan'] ?? 0);
        $editMode = ($idLaporan > 0);
        $laporan = $editMode ? LaporanModel::getById($idLaporan) : null;

        // Laporan wajib milik operasi pada URL, agar tidak bisa diedit lewat operasi lain
        if ($editMode && (!$laporan || (int)($laporan['id_operasi'] ?? 0) !== $idOperasi)) {
            $this->setFlash('error', 'Data Laporan Perkembangan tidak ditemukan.');
            $this->redirect("/operasi_detail.php?id={$idOperasi}&tab=sitrep");
        }

        // Check if operation is CLOSED (allow editing the PENUTUPAN report itself)
        $isExistingPenutupan = ($editMode && $laporan && strtoupper((string)($laporan['jenis_laporan'] ?? '')) === 'PENUTUPAN');
        if ($operasi['status'] === 'CLOSED' && !$isExistingPenutupan) {
            $this->setFlash('error', 'Operasi SAR telah berstatus DITUTUP (CLOSED). Laporan Perkembangan tidak dapat ditambahkan atau diubah kecuali operasi dibuka kembali (Rollback).');
            $this->redirect("/operasi_detail.php?id={$idOperasi}&tab=sitrep");
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'POST') {
            if (!$this->validateCsrf($_POST['csrf_token'] ?? null)) {
                $this->setFlash('error', 'Token keamanan CSRF tidak valid. Silakan coba lagi.');
                $this->redirect("/sitrep_form.php?id_operasi={$idOperasi}" . ($editMode ? "&id_laporan={$idLaporan}" : ""));
            }

            if (!empty($_POST['detail_alut_json']) && is_string($_POST['detail_alut_json'])) {
                $decodedA = json_decode($_POST['detail_alut_json'], true);
                if (is_array($decodedA)) {
                    $_POST['detail_alut'] = $decodedA;
                }
            }
            if (!empty($_POST['detail_unsur_json']) && is_string($_POST['detail_unsur_json'])) {
                $decodedU = json_decode($_POST['detail_unsur_json'], true);
                if (is_array($decodedU)) {
                    $_POST['detail_unsur'] = $decodedU;
                }
            }

            $waktuLap = $_POST['waktu_laporan'] ?? date('Y-m-d H:i:s');
            $tglLap = date('Y-m-d', strtotime($waktuLap));
            $_POST['hari_ke'] = TemplateHelper::hitungHariKe($operasi, $tglLap);

            try {
                if ($editMode) {
                    LaporanModel::update($idLaporan, $_POST);
                    $this->setFlash('success', 'Laporan Perkembangan SAR (SITREP) berhasil diperbarui!');
                } else {
                    $_POST['id_operasi'] = $idOperasi;
                    LaporanModel::create($_POST);
                    $this->setFlash('success', 'Laporan Perkembangan SAR (SITREP) berhasil diterbitkan!');
                }
                $this->redirect("/operasi_detail.php?id={$idOperasi}&tab=sitrep");
            } catch (Throwable $e) {
                $this->setFlash('error', 'Gagal menyimpan SITREP: ' . $e->getMessage());
                $this->redirect("/sitrep_form.php?id_operasi={$idOperasi}" . ($editMode ? "&id_laporan={$idLaporan}" : ""));
            }
        }

        // Master data
        $masterTim = MasterModel::getTim();
        $masterAlut = MasterModel::getAlut();
        $baseDateOps = TemplateHelper::getWaktuMulaiAktual($operasi);

        if ($editMode) {
            $waktuLaporan = !empty($laporan['waktu_laporan']) ? date('Y-m-d\TH:i', strtotime($laporan['waktu_laporan'])) : date('Y-m-d\TH:i');
            $tglLap = date('Y-m-d', strtotime($waktuLaporan));
            $hariKe = TemplateHelper::hitungHariKe($operasi, $tglLap);
            $jenisLaporan = $laporan['jenis_laporan'] ?? 'PERKEMBANGAN';
            $waktuDebrief = !empty($laporan['waktu_debrief']) ? date('Y-m-d\TH:i', strtotime($laporan['waktu_debrief'])) : date('Y-m-d\TH:i');
            $cuaca = $laporan['cuaca'] ?? ($operasi['cuaca'] ?: 'Cerah Berawan');
            $isiLaporan = $laporan['isi_laporan'] ?? '';
            $initialUnsurData = !empty($laporan['detail_unsur']) ? $laporan['detail_unsur'] : ($operasi['detail_unsur'] ?? []);
            $initialAlutData = !empty($laporan['detail_alut']) ? $laporan['detail_alut'] : ($operasi['detail_alut'] ?? []);
        } else {
            $nextLapbang = HierarchyHelper::getNextLapbangDefaults($operasi);
            $waktuLaporan = date('Y-m-d\TH:i');
            $tglLap = date('Y-m-d', strtotime($waktuLaporan));
            $hariKe = $nextLapbang['hari_ke'] ?? TemplateHelper::hitungHariKe($operasi, $tglLap);
            $jenisLaporan = 'PERKEMBANGAN';
            $waktuDebrief = date('Y-m-d\TH:i');
            $cuaca = $nextLapbang['inherited_cuaca'] ?? ($operasi['cuaca'] ?: 'Cerah Berawan');
            $isiLaporan = "Pada pelaksanaan operasi SAR hari ini, tim gabungan telah melaksanakan pencarian sesuai rencana operasi (RENOPS). Hingga saat laporan ini disusun, hasil pencarian masih nihil / korban belum ditemukan. Operasi SAR dihentikan sementara dan akan dilanjutkan esok hari.";
            $initialUnsurData = !empty($nextLapbang['inherited_unsur']) ? $nextLapbang['inherited_unsur'] : (!empty($operasi['detail_unsur']) ? $operasi['detail_unsur'] : []);
            $initialAlutData = !empty($nextLapbang['inherited_alut']) ? $nextLapbang['inherited_alut'] : (!empty($operasi['detail_alut']) ? $operasi['detail_alut'] : []);
        }

        $pageTitle = ($editMode ? "Edit SITREP {$hariKe}" : "Penerbitan SITREP {$hariKe}") . " - {$operasi['nama_operasi']}";
        $navbarTitle = $editMode ? "Edit Laporan Perkembangan ({$hariKe})" : "Penerbitan Laporan Perkembangan ({$hariKe})";

        $viewData = [
            'idOperasi' => $idOperasi,
            'operasi' => $operasi,
            'idLaporan' => $idLaporan,
            'editMode' => $editMode,
            'laporan' => $laporan,
            'masterTim' => $masterTim,
            'masterAlut' => $masterAlut,
            'baseDateOps' => $baseDateOps,
            'waktuLaporan' => $waktuLaporan,
            'tglLap' => $tglLap,
            'hariKe' => $hariKe,
            'jenisLaporan' => $jenisLaporan,
            'waktuDebrief' => $waktuDebrief,
            'cuaca' => $cuaca,
            'isiLaporan' => $isiLaporan,
            'initialUnsurData' => $initialUnsurData,
            'initialAlutData' => $initialAlutData,
            'pageTitle' => $pageTitle,
            'navbarTitle' => $navbarTitle
        ];

        $this->render('operasi/sitrep_form', $viewData, [
            'title' => $pageTitle,
            'route' => 'operasi_detail',
            'navbarTitle' => $navbarTitle
        ]);
    }
}
