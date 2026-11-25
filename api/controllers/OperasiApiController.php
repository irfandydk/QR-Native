<?php
/**
 * QR-SAR - Operasi SAR API Controller
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 */

declare(strict_types=1);

class OperasiApiController extends BaseApiController {
    public static function handle(string $path, string $method, array $input): bool {
        // 1. Pratinjau naskah WA dari isi form (belum disimpan), generator sama dengan saat simpan
        if ($path === '/operasi/wa-preview' && $method === 'POST') {
            self::requireAuth();
            $data = $_POST ?: $input;
            foreach (['detail_alut', 'detail_unsur'] as $key) {
                if (!empty($data[$key . '_json']) && is_string($data[$key . '_json'])) {
                    $decoded = json_decode($data[$key . '_json'], true);
                    if (is_array($decoded)) $data[$key] = $decoded;
                }
            }
            $doc = (string)($data['doc'] ?? '');
            $idOps = (int)($data['id_operasi'] ?? ($doc === 'laporan_awal' ? ($data['id'] ?? 0) : 0));
            $ops = $idOps > 0 ? OperasiModel::getById($idOps) : null;
            if ($doc !== 'laporan_awal' && !$ops) {
                self::error('Operasi SAR tidak ditemukan.', 404);
            }

            if ($doc === 'laporan_awal') {
                $prep = OperasiModel::prepareLaporanAwal($data, $ops);
                $text = TemplateHelper::generateLaporanAwalWA($prep['ops'], $prep['korban'], $prep['unsur'], $prep['alut']);
            } elseif ($doc === 'renops') {
                $existing = !empty($data['id_renops']) ? RenopsModel::getById((int)$data['id_renops']) : null;
                $data['srus'] = RenopsModel::normalizeInputSrus(is_array($data['srus'] ?? null) ? $data['srus'] : []);
                $data['hari_ke'] = TemplateHelper::hitungHariKe($ops, $data['tanggal_laporan'] ?? date('Y-m-d'));
                if (RenopsModel::isHariPertama($data['hari_ke'])) {
                    $data['waktu_briefing'] = null;
                    if (empty($data['waktu_mulai_ops']) && !empty($ops['waktu_berangkat'])) {
                        $data['waktu_mulai_ops'] = $ops['waktu_berangkat'];
                    }
                }
                $text = RenopsModel::buildPreviewWa($ops, $data, $existing);
            } elseif ($doc === 'sitrep') {
                $existing = !empty($data['id_laporan']) ? LaporanModel::getById((int)$data['id_laporan']) : null;
                if ($existing && (int)($existing['id_operasi'] ?? 0) !== (int)$idOps) {
                    $existing = null;
                }
                $data['hari_ke'] = LaporanModel::resolveSitrepHariKe($ops, $existing, (string)($data['waktu_laporan'] ?? 'now'), $data['hari_ke'] ?? null, true);
                $text = LaporanModel::buildPreviewWa($ops, $data, $existing);
            } else {
                self::error('Jenis naskah WA tidak dikenal.', 400);
            }
            self::json(['success' => true, 'text' => $text]);
            return true;
        }

        // 2. Operasi Rollback Penutupan (Re-open)
        if (preg_match('#^/operasi/(\d+)/rollback-penutupan$#', $path, $m) && $method === 'POST') {
            self::requireAuth();
            $id = (int)$m[1];
            $waktuReopen = $input['waktu_reopen'] ?? $input['waktu'] ?? date('Y-m-d H:i:s');
            try {
                $ok = OperasiModel::rollbackPenutupan($id, $waktuReopen);
                $updatedOps = OperasiModel::getById($id);
                self::json([
                    'success' => $ok,
                    'message' => 'Status Operasi SAR berhasil dibuka kembali (OPEN) dan perhitungan hari operasional dimulai lagi.',
                    'waktu_reopen' => $updatedOps['waktu_reopen'] ?? $waktuReopen
                ]);
            } catch (\Throwable $e) {
                self::error($e->getMessage(), 400);
            }
            return true;
        }

        // 3. Operasi Detail / Delete
        if (preg_match('#^/operasi/(\d+)$#', $path, $m)) {
            $id = (int)$m[1];
            if ($method === 'GET') {
                self::requireAuth();
                $ops = OperasiModel::getById($id);
                if (!$ops) {
                    self::error('Operasi tidak ditemukan', 404);
                }
                self::json(['success' => true, 'data' => $ops]);
                return true;
            }

            if ($method === 'DELETE') {
                self::requireAuth();
                if (!AuthHelper::canDelete()) {
                    self::error('Akses ditolak: Hanya administrator yang dapat menghapus data operasi.', 403);
                }
                try {
                    $ok = OperasiModel::delete($id);
                    self::json(['success' => $ok, 'message' => 'Operasi SAR berhasil dihapus.']);
                } catch (\Throwable $e) {
                    error_log("QR-SAR API Error deleting Operasi {$id}: " . $e->getMessage());
                    self::error('Gagal menghapus operasi SAR: ' . $e->getMessage(), 500);
                }
                return true;
            }
        }

        return false;
    }
}
