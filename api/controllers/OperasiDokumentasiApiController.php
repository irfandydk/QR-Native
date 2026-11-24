<?php
/**
 * QR-SAR - REST API Controller Arsip Dokumentasi Kolase Umum & Operasi SAR
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Endpoints Terpadu (Pusat Dokumentasi Umum & Operasi SAR):
 *   GET    /api/dokumentasi
 *   GET    /api/dokumentasi/{docId}
 *   POST   /api/dokumentasi/source             (multipart/form-data: source_file per-slot, opsional id_operasi)
 *   POST   /api/dokumentasi                    (multipart/form-data: simpan kolase umum id_operasi=0/kosong atau operasi aktif)
 *   POST   /api/dokumentasi/{docId}            (multipart/form-data: perbarui arsip tersimpan in-place)
 *   DELETE /api/dokumentasi/{docId}            (khusus ADMIN — Opsi 1 RBAC)
 *
 * Endpoints Spesifik Operasi SAR:
 *   GET    /api/operasi/{id}/dokumentasi
 *   GET    /api/operasi/{id}/dokumentasi/{docId}
 *   POST   /api/operasi/{id}/dokumentasi/source
 *   POST   /api/operasi/{id}/dokumentasi
 *   POST   /api/operasi/{id}/dokumentasi/{docId}
 *   DELETE /api/operasi/{id}/dokumentasi/{docId} (khusus ADMIN — Opsi 1 RBAC)
 */

declare(strict_types=1);

class OperasiDokumentasiApiController extends BaseApiController {
    public static function handle(string $path, string $method, array $input): bool {
        // 1A. Unggah aset sumber per-slot dari Pusat Dokumentasi Umum (/dokumentasi/source)
        if ($path === '/dokumentasi/source') {
            self::requireAuth();
            if ($method !== 'POST') {
                self::error('Metode HTTP tidak didukung untuk unggah aset sumber dokumentasi.', 405);
            }
            $rawOps = isset($_POST['id_operasi']) ? (int)$_POST['id_operasi'] : 0;
            $targetOps = $rawOps > 0 ? $rawOps : null;
            if ($targetOps !== null && OperasiModel::getStatusById($targetOps) === null) {
                self::error('Operasi SAR tidak ditemukan.', 404);
            }
            return self::handleSourceUpload($targetOps);
        }

        // 1B. Unggah aset sumber per-slot dari Studio Operasi SAR (/operasi/{id}/dokumentasi/source)
        if (preg_match('#^/operasi/(\d+)/dokumentasi/source\z#', $path, $ms)) {
            self::requireAuth();
            if ($method !== 'POST') {
                self::error('Metode HTTP tidak didukung untuk unggah aset sumber dokumentasi.', 405);
            }
            $idOperasi = (int)$ms[1];
            if (OperasiModel::getStatusById($idOperasi) === null) {
                self::error('Operasi SAR tidak ditemukan.', 404);
            }
            return self::handleSourceUpload($idOperasi);
        }

        // 2A. Endpoint Pusat Dokumentasi Terpadu (/dokumentasi dan /dokumentasi/{docId})
        if (preg_match('#^/dokumentasi(?:/(\d+))?\z#', $path, $mg)) {
            self::requireAuth();
            $docId = isset($mg[1]) ? (int)$mg[1] : null;

            if ($docId === null && $method === 'GET') {
                $items = OperasiDokumentasiModel::getAllWithOperasi($_GET);
                self::json([
                    'success' => true,
                    'stats' => OperasiDokumentasiModel::getArchiveStats(),
                    'items' => array_map([self::class, 'serializeItem'], $items),
                ]);
                return true;
            }

            if ($docId !== null && $method === 'GET') {
                $row = OperasiDokumentasiModel::getById($docId);
                if (!$row) {
                    self::error('Arsip dokumentasi tidak ditemukan.', 404);
                }
                self::json([
                    'success' => true,
                    'item' => self::serializeItem($row, true),
                ]);
                return true;
            }

            if ($method === 'POST') {
                $rawOps = isset($_POST['id_operasi']) ? (int)$_POST['id_operasi'] : 0;
                $targetOps = $rawOps > 0 ? $rawOps : null;
                if ($targetOps !== null && OperasiModel::getStatusById($targetOps) === null) {
                    self::error('Operasi SAR yang dipilih tidak ditemukan.', 404);
                }
                return self::handleStoreOrUpdate($docId, $targetOps, null);
            }

            if ($docId !== null && $method === 'DELETE') {
                if (!AuthHelper::canDelete('dokumentasi')) {
                    self::error('Akses ditolak: Hanya Administrator yang dapat menghapus arsip dokumentasi.', 403, 'FORBIDDEN');
                }
                $deleted = OperasiDokumentasiModel::delete($docId);
                if (!$deleted) {
                    self::error('Arsip dokumentasi tidak ditemukan.', 404);
                }
                self::json(['success' => true, 'message' => 'Arsip dokumentasi berhasil dihapus.']);
                return true;
            }

            self::error('Metode HTTP tidak didukung untuk endpoint dokumentasi.', 405);
            return true;
        }

        // 2B. Endpoint Spesifik Operasi SAR (/operasi/{id}/dokumentasi dan /operasi/{id}/dokumentasi/{docId})
        if (!preg_match('#^/operasi/(\d+)/dokumentasi(?:/(\d+))?\z#', $path, $m)) {
            return false;
        }

        self::requireAuth();
        $idOperasi = (int)$m[1];
        $docId = isset($m[2]) ? (int)$m[2] : null;

        $ops = OperasiModel::getStatusById($idOperasi);
        if ($ops === null) {
            self::error('Operasi SAR tidak ditemukan.', 404);
        }

        if ($docId === null && $method === 'GET') {
            $items = OperasiDokumentasiModel::getByOperasiId($idOperasi);
            self::json([
                'success' => true,
                'items' => array_map([self::class, 'serializeItem'], $items),
            ]);
            return true;
        }

        if ($docId !== null && $method === 'GET') {
            $row = OperasiDokumentasiModel::getById($docId);
            if (!$row || (int)($row['id_operasi'] ?? 0) !== $idOperasi) {
                self::error('Arsip dokumentasi tidak ditemukan.', 404);
            }
            self::json([
                'success' => true,
                'item' => self::serializeItem($row, true),
            ]);
            return true;
        }

        if ($method === 'POST') {
            return self::handleStoreOrUpdate($docId, $idOperasi, $idOperasi);
        }

        if ($docId !== null && $method === 'DELETE') {
            if (!AuthHelper::canDelete('dokumentasi')) {
                self::error('Akses ditolak: Hanya Administrator yang dapat menghapus arsip dokumentasi.', 403, 'FORBIDDEN');
            }
            $deleted = OperasiDokumentasiModel::delete($docId, $idOperasi);
            if (!$deleted) {
                self::error('Arsip dokumentasi tidak ditemukan.', 404);
            }
            self::json(['success' => true, 'message' => 'Arsip dokumentasi berhasil dihapus.']);
            return true;
        }

        self::error('Metode HTTP tidak didukung untuk endpoint dokumentasi.', 405);
        return true;
    }

    private static function handleSourceUpload(?int $idOperasi): bool {
        $file = $_FILES['source_file'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errCode = is_array($file) ? (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
            $msg = ($errCode === UPLOAD_ERR_INI_SIZE || $errCode === UPLOAD_ERR_FORM_SIZE)
                ? 'Ukuran berkas sumber melebihi batas unggahan server (maksimal 20 MB).'
                : 'Berkas sumber media slot tidak diterima dengan lengkap.';
            self::error($msg, 422);
        }
        try {
            $asset = OperasiDokumentasiModel::storeSourceAsset($idOperasi, $file);
            self::json([
                'success' => true,
                'path' => $asset['path'],
                'url' => BASE_URL . '/' . ltrim($asset['path'], '/'),
                'media_type' => $asset['media_type'],
                'size' => $asset['size'],
            ], 201);
        } catch (InvalidArgumentException $e) {
            self::error($e->getMessage(), 422);
        } catch (Throwable $e) {
            error_log('[OperasiDokumentasiApiController POST /source] ' . $e->getMessage());
            self::error('Gagal menyimpan aset sumber media.', 500);
        }
        return true;
    }

    private static function handleStoreOrUpdate(?int $docId, ?int $targetOperasiId, ?int $expectedOperasiId): bool {
        $file = $_FILES['media_file'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errCode = is_array($file) ? (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
            $msg = ($errCode === UPLOAD_ERR_INI_SIZE || $errCode === UPLOAD_ERR_FORM_SIZE)
                ? 'Ukuran berkas melebihi batas unggahan server (maksimal 20 MB).'
                : 'Berkas kolase tidak diterima dengan lengkap.';
            self::error($msg, 422);
        }

        $thumb = null;
        if (isset($_FILES['thumb_file']) && is_array($_FILES['thumb_file']) && ($_FILES['thumb_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $thumb = $_FILES['thumb_file'];
        }

        $userId = AuthHelper::getCurrentUser()['id'] ?? null;
        $uidStr = $userId === null ? null : (string)$userId;
        try {
            if ($docId !== null) {
                $updated = OperasiDokumentasiModel::update($docId, $targetOperasiId, $_POST, $file, $thumb, $uidStr, $expectedOperasiId);
                self::json([
                    'success' => true,
                    'message' => 'Perubahan dokumentasi kolase berhasil disimpan.',
                    'item' => self::serializeItem($updated, true),
                ], 200);
            } else {
                $saved = OperasiDokumentasiModel::store($targetOperasiId, $_POST, $file, $thumb, $uidStr);
                self::json([
                    'success' => true,
                    'message' => 'Dokumentasi kolase berhasil disimpan ke arsip.',
                    'item' => self::serializeItem($saved, true),
                ], 201);
            }
        } catch (InvalidArgumentException $e) {
            self::error($e->getMessage(), 422);
        } catch (Throwable $e) {
            error_log('[OperasiDokumentasiApiController POST] ' . $e->getMessage());
            self::error('Gagal menyimpan dokumentasi kolase.', 500);
        }
        return true;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function serializeItem(array $row, bool $includeProject = false): array {
        $filePath = ltrim((string)($row['file_path'] ?? ''), '/');
        $thumbPath = ltrim((string)($row['thumb_path'] ?? ''), '/');
        $projectRaw = isset($row['project_json']) ? (string)$row['project_json'] : '';
        $idOps = ($row['id_operasi'] ?? null) !== null && (int)$row['id_operasi'] > 0 ? (int)$row['id_operasi'] : null;
        $item = [
            'id_dokumentasi' => (int)($row['id_dokumentasi'] ?? 0),
            'id_operasi' => $idOps,
            'is_umum' => $idOps === null,
            'nama_operasi' => isset($row['nama_operasi']) ? (string)$row['nama_operasi'] : '',
            'judul' => (string)($row['judul'] ?? ''),
            'hari_ke' => (string)($row['hari_ke'] ?? ($idOps !== null ? 'H1' : 'KEGIATAN')),
            'tanggal_label' => (string)($row['tanggal_label'] ?? ''),
            'media_type' => (string)($row['media_type'] ?? 'image'),
            'mime_type' => (string)($row['mime_type'] ?? 'image/jpeg'),
            'aspect_ratio' => (string)($row['aspect_ratio'] ?? '2:3'),
            'orientation' => (string)($row['orientation'] ?? 'vertical'),
            'slot_count' => (int)($row['slot_count'] ?? 6),
            'layout_preset' => (string)($row['layout_preset'] ?? ''),
            'width_px' => (int)($row['width_px'] ?? 1080),
            'height_px' => (int)($row['height_px'] ?? 1620),
            'file_url' => $filePath !== '' ? BASE_URL . '/' . $filePath : '',
            'thumb_url' => $thumbPath !== '' ? BASE_URL . '/' . $thumbPath : ($filePath !== '' && ($row['media_type'] ?? '') === 'image' ? BASE_URL . '/' . $filePath : ''),
            'file_size' => (int)($row['file_size'] ?? 0),
            'has_project' => trim($projectRaw) !== '',
            'created_at' => (string)($row['created_at'] ?? ''),
        ];
        if ($includeProject) {
            $item['project'] = OperasiDokumentasiModel::decodeProjectForClient($projectRaw, BASE_URL);
        }
        return $item;
    }
}
