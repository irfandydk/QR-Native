<?php
/**
 * QR-SAR - API dokumen Rencana Operasi SAR (draf, terbit, foto)
 * Semua pengguna login boleh menyunting/menerbitkan (K13); tanpa cek CLOSED (K16).
 */

declare(strict_types=1);

class RenopsDokumenApiController extends BaseApiController {
    private const MAX_LEN = 20000;
    private const STALE = 'Draf telah diubah pengguna lain. Muat ulang halaman.';
    private const TEXT_KEYS = ['awareness', 'kronologi', 'jenis_operasi', 'upaya_sebelumnya', 'mission', 'waktu',
        'area_pencarian', 'rescue_plan', 'briefing', 'operation_stage'];
    private const LIST_KEYS = ['koordinasi', 'koordinasi_instansi', 'persiapan_personel', 'sarana', 'peralatan',
        'administrasi', 'logistik'];
    private const ROW_KEYS = [
        'data_kejadian' => ['label', 'value'],
        'aset' => ['grup', 'aset', 'jumlah'],
        'sru' => ['unsur', 'tugas', 'area', 'pola', 'track_spacing'],
    ];
    private const STRUKTUR_FIELDS = ['sc', 'smc', 'osc', 'staf_operasi', 'staf_admin', 'staf_logistik',
        'staf_intelijen', 'staf_humas', 'staf_komunikasi'];

    public static function handle(string $path, string $method, array $input): bool {
        if (!preg_match('#^/renops/(\d+)/(dokumen|foto)(?:/(terbit|\d+))?\z#', $path, $m)) {
            return false;
        }
        self::requireAuth();
        $id = (int)$m[1];
        $renops = RenopsModel::getById($id);
        if (!$renops) {
            self::error('Data Rencana Operasi (RENOPS) tidak ditemukan.', 404);
        }
        $sub = $m[3] ?? null;

        if ($m[2] === 'dokumen') {
            if ($sub === null && $method === 'GET') {
                self::get($id);
            } elseif ($sub === null && $method === 'PUT') {
                self::put($id, (int)$renops['id_operasi'], $input);
            } elseif ($sub === 'terbit' && $method === 'POST') {
                self::terbit($id, $input);
            }
            return false;
        }

        if ($sub === null && $method === 'POST') {
            self::upload($id);
        } elseif ($sub !== null && ctype_digit($sub) && $method === 'PUT') {
            $cur = null;
            foreach (RenopsFotoModel::listByRenops($id) as $row) {
                if ((int)$row['id_foto'] === (int)$sub) {
                    $cur = $row;
                }
            }
            if (!$cur) {
                self::error('Foto tidak ditemukan.', 404);
            }
            $ket = $input['keterangan'] ?? (string)$cur['keterangan'];
            $urutan = $input['urutan'] ?? (int)$cur['urutan'];
            if (!is_string($ket) || !is_int($urutan) || $urutan < 0 || $urutan > 2147483647) {
                self::error('Keterangan harus teks dan urutan bilangan bulat.', 422);
            }
            RenopsFotoModel::update((int)$sub, $id, mb_substr($ket, 0, 255), $urutan);
            self::json(['success' => true]);
        } elseif ($sub !== null && ctype_digit($sub) && $method === 'DELETE') {
            RenopsFotoModel::delete((int)$sub, $id) ? self::json(['success' => true]) : self::error('Foto tidak ditemukan.', 404);
        }
        return false;
    }

    private static function userId(): ?string {
        $id = AuthHelper::getCurrentUser()['id'] ?? null;
        return $id === null ? null : (string)$id;
    }

    private static function get(int $id): void {
        $doc = RenopsDokumenService::resolve($id);
        $draft = RenopsDokumenModel::getDraft($id);
        $terbit = RenopsDokumenModel::getLatestTerbit($id);
        self::json([
            'doc' => $doc,
            'rev' => $draft['rev'] ?? 0,
            'terbit_versi' => $terbit ? (int)$terbit['versi'] : null,
            'has_unpublished' => RenopsDokumenPublisher::hasUnpublishedChanges($id),
            'closed' => (bool)($doc['meta']['closed'] ?? false),
            'foto' => RenopsFotoModel::listByRenops($id),
        ]);
    }

    private static function put(int $id, int $idOperasi, array $input): void {
        $rev = self::requireRev($input);
        try {
            if (!is_array($input['sections'] ?? null)) {
                throw new InvalidArgumentException("Isi 'sections' tidak valid.");
            }
            $sections = self::sanitizeSections($input['sections']);
        } catch (InvalidArgumentException $e) {
            self::error($e->getMessage(), 422);
        }
        $rev = RenopsDokumenModel::saveDraft($id, $idOperasi, $sections, $rev);
        $rev === null ? self::error(self::STALE, 409) : self::json(['rev' => $rev]);
    }

    private static function requireRev(array $input): int {
        $r = $input['rev'] ?? null;
        if (is_string($r) && ctype_digit($r)) {
            $r = (int)$r;
        }
        if (!is_int($r) || $r < 0) {
            self::error("Parameter 'rev' (bilangan bulat) wajib diisi.", 422);
        }
        return $r;
    }

    private static function terbit(int $id, array $input): void {
        $rev = self::requireRev($input);
        try {
            $res = RenopsDokumenPublisher::publish($id, self::userId(), $rev);
        } catch (RuntimeException $e) {
            error_log('Terbit RENOPS #' . $id . ' gagal: ' . $e->getMessage());
            self::error('Gagal menerbitkan dokumen: berkas peta/foto tidak ditemukan. Unggah ulang foto atau capture ulang peta.', 422);
        }
        if ($res === null) {
            self::error(self::STALE, 409);
        }
        if ($res['baru']) {
            AuditHelper::log('RENOPS_DOKUMEN_TERBIT', "Terbit dokumen RENOPS #{$id} versi {$res['versi']}", 'renops', (string)$id);
        }
        self::json([
            'versi' => $res['versi'], 'baru' => $res['baru'], 'token' => $res['token'],
            'verify_url' => $res['verify_url'], 'public_url' => $res['public_url'],
            'print_url' => BASE_URL . '/operasi.php?action=print_renops&id_renops=' . $id,
        ]);
    }

    private static function upload(int $id): void {
        $f = $_FILES['foto'] ?? null;
        if (!$f || !is_array($f['name'] ?? null)) {
            // Melebihi post_max_size: PHP mengosongkan $_FILES & $_POST tanpa galat.
            $tooBig = (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > (int)ini_parse_quantity((string)ini_get('post_max_size'));
            self::error($tooBig
                ? 'Total ukuran foto terlalu besar untuk satu unggahan. Unggah lebih sedikit foto sekaligus (maksimal 5 MB per foto).'
                : 'Tidak ada berkas foto yang diunggah.', 422);
        }
        // multipart: php://input kosong, jadi $input kosong; keterangan[] ada di $_POST
        $fail = self::storeUploads($id, $f, (array)($_POST['keterangan'] ?? []), self::userId());
        $foto = RenopsFotoModel::listByRenops($id);
        $fail === null ? self::json(['foto' => $foto]) : self::json(['message' => $fail, 'foto' => $foto], 422);
    }

    /**
     * Simpan berkas ($_FILES['foto'] bentuk multi) satu per satu; kembalikan pesan gagal pertama atau null.
     * $requireUploaded=false hanya untuk test (tanpa HTTP).
     */
    public static function storeUploads(int $id, array $f, array $ket, ?string $userId, bool $requireUploaded = true): ?string {
        $names = (array)$f['name'];
        if (count($names) > RenopsFotoModel::MAX_PER_RENOPS) {
            return 'Maksimal ' . RenopsFotoModel::MAX_PER_RENOPS . ' foto per unggahan.';
        }
        foreach ($names as $i => $_) {
            $tmp = (string)($f['tmp_name'][$i] ?? '');
            $err = $f['error'][$i] ?? UPLOAD_ERR_NO_FILE;
            if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
                return 'Ukuran foto maksimal 5 MB.';
            }
            if ($err !== UPLOAD_ERR_OK || $tmp === '' || ($requireUploaded && !is_uploaded_file($tmp))) {
                return 'Unggahan foto tidak valid atau gagal.';
            }
            try {
                RenopsFotoModel::store($id, $tmp, (int)$f['size'][$i], $userId, mb_substr((string)($ket[$i] ?? ''), 0, 255));
            } catch (InvalidArgumentException $e) {
                return $e->getMessage();
            } catch (RuntimeException $e) {
                error_log('Unggah foto RENOPS #' . $id . ' gagal: ' . $e->getMessage());
                return 'Gagal menyimpan foto.';
            }
        }
        return null;
    }

    private static function str(mixed $v, string $key): string {
        if (!is_string($v)) {
            throw new InvalidArgumentException("Isi bagian '{$key}' tidak valid.");
        }
        return mb_substr($v, 0, self::MAX_LEN);
    }

    /** Saring suntingan draf: hanya kunci kontrak, bentuk nilai dipaksa; null = reset kunci. */
    public static function sanitizeSections(array $in): array {
        $out = [];
        foreach ($in as $k => $v) {
            $k = (string)$k;
            if ($v === null) {
                $isKey = in_array($k, self::TEXT_KEYS, true) || in_array($k, self::LIST_KEYS, true)
                    || isset(self::ROW_KEYS[$k]) || $k === 'struktur';
                if (!$isKey) {
                    throw new InvalidArgumentException("Bagian dokumen '{$k}' tidak dikenal.");
                }
                $out[$k] = null;
            } elseif (in_array($k, self::TEXT_KEYS, true)) {
                $out[$k] = self::str($v, $k);
            } elseif (in_array($k, self::LIST_KEYS, true)) {
                if (!is_array($v)) {
                    throw new InvalidArgumentException("Isi bagian '{$k}' tidak valid.");
                }
                $out[$k] = array_map(fn($x) => self::str($x, $k), array_values($v));
            } elseif (isset(self::ROW_KEYS[$k])) {
                $out[$k] = self::rows($v, self::ROW_KEYS[$k], $k);
            } elseif ($k === 'struktur') {
                if (!is_array($v)) {
                    throw new InvalidArgumentException("Isi bagian 'struktur' tidak valid.");
                }
                $s = [];
                foreach (self::STRUKTUR_FIELDS as $f) {
                    if (array_key_exists($f, $v)) {
                        $s[$f] = self::str($v[$f], $k);
                    }
                }
                if (array_key_exists('sru', $v)) {
                    $s['sru'] = self::rows($v['sru'], ['nama', 'keterangan'], 'struktur');
                }
                $out[$k] = $s;
            } else {
                throw new InvalidArgumentException("Bagian dokumen '{$k}' tidak dikenal.");
            }
        }
        return $out;
    }

    private static function rows(mixed $v, array $fields, string $key): array {
        if (!is_array($v)) {
            throw new InvalidArgumentException("Isi bagian '{$key}' tidak valid.");
        }
        $rows = [];
        foreach ($v as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException("Isi bagian '{$key}' tidak valid.");
            }
            $r = [];
            foreach ($fields as $f) {
                $r[$f] = self::str($row[$f] ?? '', $key);
            }
            $rows[] = $r;
        }
        return $rows;
    }
}
