<?php
/**
 * QR-SAR - Operasi SAR API Controller
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 */

declare(strict_types=1);

class OperasiApiController extends BaseApiController {
    public static function handle(string $path, string $method, array $input): bool {
        // 1. Stats
        if ($path === '/laporan/stats' || $path === '/monitoring/stats') {
            self::requireAuth();
            self::json(['success' => true, 'data' => OperasiModel::getStats()]);
            return true;
        }

        // 2. Operasi List
        if ($path === '/operasi' && $method === 'GET') {
            self::requireAuth();
            $status = $_GET['status'] ?? null;
            $q = $_GET['q'] ?? null;
            self::json(['success' => true, 'data' => OperasiModel::getAll($status, $q)]);
            return true;
        }

        // 3. Operasi Create
        if ($path === '/operasi' && $method === 'POST') {
            self::requireAuth();
            $newId = OperasiModel::create($input);
            self::json(['success' => true, 'id' => $newId, 'message' => 'Laporan Awal Operasi SAR berhasil disimpan.']);
            return true;
        }

        // 4. Operasi Close
        if (preg_match('#^/operasi/(\d+)/close$#', $path, $m) && $method === 'POST') {
            self::requireAuth();
            $id = (int)$m[1];
            $waktu = $input['waktu_selesai'] ?? date('Y-m-d H:i:s');
            try {
                $ok = OperasiModel::closeOperation($id, $waktu);
                self::json(['success' => $ok, 'message' => 'Operasi SAR resmi ditutup.']);
            } catch (\Throwable $e) {
                self::error($e->getMessage(), 400);
            }
            return true;
        }

        // 5. Operasi Rollback Penutupan (Re-open)
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

        // 6. Operasi Detail / Update / Delete
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

            if ($method === 'PUT' || $method === 'POST') {
                self::requireAuth();
                try {
                    $ok = OperasiModel::update($id, $input);
                    self::json(['success' => $ok, 'message' => 'Data Operasi SAR berhasil diperbarui.']);
                } catch (\Throwable $e) {
                    self::error($e->getMessage(), 400);
                }
                return true;
            }

            if ($method === 'DELETE') {
                self::requireAuth();
                if (!AuthHelper::canDelete()) {
                    self::error('Akses ditolak: Hanya administrator yang dapat menghapus data operasi.', 403);
                }
                $ok = OperasiModel::delete($id);
                self::json(['success' => $ok, 'message' => 'Operasi SAR berhasil dihapus.']);
                return true;
            }
        }

        return false;
    }
}
