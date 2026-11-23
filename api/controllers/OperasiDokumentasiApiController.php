<?php
/**
 * QR-SAR - REST API Controller Arsip Dokumentasi Kolase Operasi SAR
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Endpoints:
 *   GET    /api/operasi/{id}/dokumentasi
 *   GET    /api/operasi/{id}/dokumentasi/{docId}
 *   POST   /api/operasi/{id}/dokumentasi/source   (multipart/form-data: source_file per-slot untuk edit ulang)
 *   POST   /api/operasi/{id}/dokumentasi          (multipart/form-data: media_file, optional thumb_file, metadata, project_json)
 *   POST   /api/operasi/{id}/dokumentasi/{docId}  (multipart/form-data: perbarui arsip kolase tersimpan secara in-place)
 *   DELETE /api/operasi/{id}/dokumentasi/{docId}
 */

declare(strict_types=1);

class OperasiDokumentasiApiController extends BaseApiController {
    public static function handle(string $path, string $method, array $input): bool {
        // Endpoint khusus unggah aset sumber per-slot (untuk penyimpanan proyek yang dapat diedit ulang)
        if (preg_match('#^/operasi/(\d+)/dokumentasi/source\z#', $path, $ms)) {
            self::requireAuth();
            if ($method !== 'POST') {
                self::error('Metode HTTP tidak didukung untuk unggah aset sumber dokumentasi.', 405);
            }
            $idOperasi = (int)$ms[1];
            if (OperasiModel::getStatusById($idOperasi) === null) {
                self::error('Operasi SAR tidak ditemukan.', 404);
            }
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
                    $updated = OperasiDokumentasiModel::update($docId, $idOperasi, $_POST, $file, $thumb, $uidStr);
                    self::json([
                        'success' => true,
                        'message' => 'Perubahan dokumentasi kolase berhasil disimpan.',
                        'item' => self::serializeItem($updated, true),
                    ], 200);
                } else {
                    $saved = OperasiDokumentasiModel::store($idOperasi, $_POST, $file, $thumb, $uidStr);
                    self::json([
                        'success' => true,
                        'message' => 'Dokumentasi kolase berhasil disimpan ke arsip operasi.',
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

        if ($docId !== null && $method === 'DELETE') {
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

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function serializeItem(array $row, bool $includeProject = false): array {
        $filePath = ltrim((string)($row['file_path'] ?? ''), '/');
        $thumbPath = ltrim((string)($row['thumb_path'] ?? ''), '/');
        $projectRaw = isset($row['project_json']) ? (string)$row['project_json'] : '';
        $item = [
            'id_dokumentasi' => (int)($row['id_dokumentasi'] ?? 0),
            'id_operasi' => (int)($row['id_operasi'] ?? 0),
            'judul' => (string)($row['judul'] ?? ''),
            'hari_ke' => (string)($row['hari_ke'] ?? 'H1'),
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
