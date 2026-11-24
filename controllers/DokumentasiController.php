<?php
/**
 * QR-SAR - Controller Pusat Dokumentasi Umum & Operasi SAR
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Menangani:
 * - Halaman arsip kronologis terpadu (/dokumentasi.php) yang mengelompokkan seluruh dokumentasi
 *   (baik Dokumentasi Umum maupun Dokumentasi Operasi SAR) berdasarkan waktu pembuatannya.
 * - Studio pembuatan & pengeditan kolase (/dokumentasi.php?action=create atau ?action=edit&id=...)
 *   dengan pilihan opsional Operasi SAR Aktif.
 */

declare(strict_types=1);

class DokumentasiController extends BaseController {
    private const BULAN_INDO = [
        '01' => 'Januari',
        '02' => 'Februari',
        '03' => 'Maret',
        '04' => 'April',
        '05' => 'Mei',
        '06' => 'Juni',
        '07' => 'Juli',
        '08' => 'Agustus',
        '09' => 'September',
        '10' => 'Oktober',
        '11' => 'November',
        '12' => 'Desember',
    ];

    private const BULAN_PENDEK_INDO = [
        1 => 'Jan',
        2 => 'Feb',
        3 => 'Mar',
        4 => 'Apr',
        5 => 'Mei',
        6 => 'Jun',
        7 => 'Jul',
        8 => 'Agu',
        9 => 'Sep',
        10 => 'Okt',
        11 => 'Nov',
        12 => 'Des',
    ];

    public function handle(): void {
        $this->requireLogin();

        $action = strtolower(trim((string)($_GET['action'] ?? '')));
        if ($action === '' && (isset($_GET['id']) || isset($_GET['doc_id']))) {
            $action = 'edit';
        }

        if ($action === 'create' || $action === 'edit') {
            $this->studio($action);
            return;
        }

        $this->index();
    }

    /**
     * Format kode bulan YYYY-MM ke Bahasa Indonesia (mis. "2026-11" -> "November 2026").
     */
    public static function formatMonthYearIndo(string $ym): string {
        if (!preg_match('/^(\d{4})-(\d{2})$/', trim($ym), $m)) {
            return $ym !== '' ? $ym : 'Lainnya';
        }
        $namaBulan = self::BULAN_INDO[$m[2]] ?? $m[2];
        return $namaBulan . ' ' . $m[1];
    }

    /**
     * Format waktu pembuatan arsip ke penanda waktu WITA ringkas (mis. "24 Nov 2026, 11:30 WITA").
     */
    public static function formatWitaTimestamp(?string $datetime): string {
        $raw = trim((string)($datetime ?? ''));
        if ($raw === '') {
            return '-';
        }
        $ts = strtotime($raw);
        if ($ts === false) {
            return $raw;
        }
        $bln = self::BULAN_PENDEK_INDO[(int)date('n', $ts)] ?? date('M', $ts);
        return date('d', $ts) . ' ' . $bln . ' ' . date('Y', $ts) . ', ' . date('H:i', $ts) . ' WITA';
    }

    /**
     * Halaman utama arsip kronologis seluruh dokumentasi kolase (Umum + Operasi SAR).
     */
    protected function index(): void {
        $scopeRaw = strtolower(trim((string)($_GET['scope'] ?? 'all')));
        $scopeFilter = in_array($scopeRaw, ['all', 'umum', 'operasi'], true) ? $scopeRaw : 'all';

        $mediaRaw = strtolower(trim((string)($_GET['media_type'] ?? '')));
        $mediaFilter = in_array($mediaRaw, ['image', 'video'], true) ? $mediaRaw : '';

        $monthRaw = trim((string)($_GET['month'] ?? ''));
        $monthFilter = preg_match('/^\d{4}-\d{2}$/', $monthRaw) ? $monthRaw : '';

        $searchQuery = trim((string)($_GET['q'] ?? ''));

        $stats = OperasiDokumentasiModel::getArchiveStats();
        $availableMonths = OperasiDokumentasiModel::getAvailableMonths();
        $items = OperasiDokumentasiModel::getAllWithOperasi([
            'scope' => $scopeFilter,
            'media_type' => $mediaFilter,
            'month' => $monthFilter,
            'q' => $searchQuery,
        ]);

        $groupedArchive = [];
        foreach ($items as $item) {
            $createdAt = trim((string)($item['created_at'] ?? ''));
            $ym = (strlen($createdAt) >= 7 && preg_match('/^\d{4}-\d{2}/', $createdAt))
                ? substr($createdAt, 0, 7)
                : date('Y-m');
            if (!isset($groupedArchive[$ym])) {
                $groupedArchive[$ym] = [
                    'ym' => $ym,
                    'label' => self::formatMonthYearIndo($ym),
                    'items' => [],
                ];
            }
            $groupedArchive[$ym]['items'][] = $item;
        }

        $this->render('dokumentasi/index', [
            'stats' => $stats,
            'availableMonths' => $availableMonths,
            'items' => $items,
            'groupedArchive' => array_values($groupedArchive),
            'scopeFilter' => $scopeFilter,
            'mediaFilter' => $mediaFilter,
            'monthFilter' => $monthFilter,
            'searchQuery' => $searchQuery,
            'canDeleteDok' => AuthHelper::canDelete('dokumentasi'),
        ], [
            'title' => 'Dokumentasi Kolase',
            'route' => 'dokumentasi',
            'navbarTitle' => 'Pusat Dokumentasi',
            'navbarSubtitle' => 'Arsip Kronologis Dokumentasi Umum & Operasi SAR',
        ]);
    }

    /**
     * Studio pembuatan & pengeditan kolase dokumentasi umum maupun operasi SAR aktif.
     */
    protected function studio(string $action): void {
        $settings = SettingsModel::getSettings();
        $editDocId = ($action === 'edit') ? (int)($_GET['id'] ?? $_GET['doc_id'] ?? 0) : 0;

        $editDok = null;
        $editProject = null;
        if ($action === 'edit') {
            $editDok = $editDocId > 0 ? OperasiDokumentasiModel::getById($editDocId) : null;
            if (!$editDok) {
                $this->setFlash('error', 'Arsip dokumentasi yang ingin diedit tidak ditemukan.');
                $this->redirect('/dokumentasi.php');
            }
            $editProject = OperasiDokumentasiModel::decodeProjectForClient(
                isset($editDok['project_json']) ? (string)$editDok['project_json'] : null,
                BASE_URL
            );
        }

        // Muat daftar Operasi SAR Aktif (OPEN) sebagai opsi alternatif cepat di Studio
        $db = getDB();
        $openRows = $db->query("SELECT id_operasi, nama_operasi, waktu_kejadian, waktu_lapor, waktu_berangkat, waktu_reopen, hari_sebelum_reopen, status
                                FROM Operasi
                                WHERE status = 'OPEN'
                                ORDER BY id_operasi DESC")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $opsById = [];
        foreach ($openRows as $op) {
            $opsById[(int)$op['id_operasi']] = $op;
        }

        // Jika mengedit arsip milik operasi yang sudah CLOSED, sertakan operasi tersebut di dropdown agar tautannya terjaga
        $linkedOpsId = 0;
        if ($editDok !== null) {
            $linkedOpsId = ($editDok['id_operasi'] ?? null) !== null ? (int)$editDok['id_operasi'] : 0;
        } elseif (isset($_GET['id_operasi']) && (int)$_GET['id_operasi'] > 0) {
            $linkedOpsId = (int)$_GET['id_operasi'];
        }

        if ($linkedOpsId > 0 && !isset($opsById[$linkedOpsId])) {
            $stmtExtra = $db->prepare("SELECT id_operasi, nama_operasi, waktu_kejadian, waktu_lapor, waktu_berangkat, waktu_reopen, hari_sebelum_reopen, status
                                       FROM Operasi
                                       WHERE id_operasi = :id
                                       LIMIT 1");
            $stmtExtra->execute([':id' => $linkedOpsId]);
            $extraOp = $stmtExtra->fetch(PDO::FETCH_ASSOC);
            if (is_array($extraOp)) {
                $opsById[$linkedOpsId] = $extraOp;
            } else {
                $linkedOpsId = 0;
            }
        }

        $activeOperasiOptions = [];
        foreach ($opsById as $opId => $opRow) {
            $hRaw = (string)TemplateHelper::hitungHariKe($opRow);
            $hClean = strtoupper(str_replace('.', '', $hRaw)) ?: 'H1';
            $namaClean = trim((string)($opRow['nama_operasi'] ?? ''));
            $namaRingkas = (string)preg_replace('/^Operasi\s+SAR\s+/i', '', $namaClean);
            $judulOps = trim('Dok Operasi SAR ' . $hClean . ' ' . ($namaRingkas !== '' ? $namaRingkas : $namaClean));

            $activeOperasiOptions[] = [
                'id' => $opId,
                'nama' => $namaClean,
                'status' => (string)($opRow['status'] ?? 'OPEN'),
                'hariLabel' => $hClean,
                'judulDefault' => $judulOps,
            ];
        }

        $selectedOption = null;
        if ($linkedOpsId > 0) {
            foreach ($activeOperasiOptions as $opt) {
                if ((int)$opt['id'] === $linkedOpsId) {
                    $selectedOption = $opt;
                    break;
                }
            }
        }

        $generalDefaults = [
            'hariLabel' => 'KEGIATAN',
            'judulDefault' => 'Dokumentasi Kegiatan KPP Kelas A Kendari',
        ];

        $tanggalDefault = TemplateHelper::formatTanggalIndo(date('Y-m-d'));
        if ($selectedOption !== null) {
            $hariLabel = $selectedOption['hariLabel'];
            $judulDefault = $selectedOption['judulDefault'];
            $operasiPayload = [
                'id_operasi' => $selectedOption['id'],
                'nama_operasi' => $selectedOption['nama'],
            ];
        } else {
            $hariLabel = $generalDefaults['hariLabel'];
            $judulDefault = $generalDefaults['judulDefault'];
            $operasiPayload = null;
        }

        if ($editDok !== null) {
            if (!empty($editDok['hari_ke'])) {
                $hariLabel = (string)$editDok['hari_ke'];
            }
            if (isset($editDok['tanggal_label']) && trim((string)$editDok['tanggal_label']) !== '') {
                $tanggalDefault = (string)$editDok['tanggal_label'];
            }
            if (!empty($editDok['judul'])) {
                $judulDefault = (string)$editDok['judul'];
            }
        }

        $emergencyFormatted = OperasiController::formatEmergencyCallLine(
            (string)($settings['no_telp'] ?? '0401 3196 557'),
            (string)($settings['no_wa'] ?? '')
        );

        $this->render('operasi/dokumentasi_studio', [
            'operasi' => $operasiPayload,
            'allowOperasiSelect' => true,
            'activeOperasiOptions' => $activeOperasiOptions,
            'generalDefaults' => $generalDefaults,
            'backDetailUrl' => BASE_URL . '/dokumentasi.php',
            'backDetailLabel' => 'Kembali ke Arsip Dokumentasi',
            'newStudioUrl' => BASE_URL . '/dokumentasi.php?action=create',
            'apiEndpoint' => BASE_URL . '/api/index.php?path=/dokumentasi',
            'sourceApiEndpoint' => BASE_URL . '/api/index.php?path=/dokumentasi/source',
            'settings' => $settings,
            'hariLabel' => $hariLabel,
            'tanggalDefault' => $tanggalDefault,
            'judulDefault' => $judulDefault,
            'emergencyFormatted' => $emergencyFormatted,
            'logo1DataUri' => getAppLogoDataUri(),
            'logo2DataUri' => getAppLogo2DataUri(),
            'logo3DataUri' => getAppLogo3DataUri(),
            'editDok' => $editDok,
            'editProject' => $editProject,
        ], [
            'title' => $editDok ? 'Edit Dokumentasi Kolase' : 'Buat Dokumentasi Kolase',
            'route' => 'dokumentasi',
            'navbarTitle' => $editDok ? 'EDIT DOKUMENTASI KOLASE' : 'STUDIO DOKUMENTASI KOLASE',
        ]);
    }
}
