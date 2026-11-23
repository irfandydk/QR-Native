<?php
/**
 * QR-SAR - Laporan Perkembangan (SITREP) API Controller
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 */

declare(strict_types=1);

class SitrepApiController extends BaseApiController {
    public static function handle(string $path, string $method, array $input): bool {
        // Delete SITREP
        if (preg_match('#^/laporan/(\d+)$#', $path, $m) && $method === 'DELETE') {
            self::requireAuth();
            $id = (int)$m[1];
            $existingLap = LaporanModel::getById($id);
            if (!$existingLap) {
                self::error('Data Laporan Perkembangan (SITREP) tidak ditemukan.', 404);
            }
            $parentStatus = OperasiModel::getStatusById((int)$existingLap['id_operasi']);
            if ($parentStatus === 'CLOSED') {
                self::error('Operasi SAR telah berstatus DITUTUP (CLOSED). Laporan Perkembangan terkunci dan tidak dapat diubah/dihapus kecuali status operasi dibuka kembali.', 403);
            }
            if (!AuthHelper::canDelete()) {
                self::error('Akses ditolak: Hanya Administrator yang berwenang menghapus data SITREP.', 403);
            }
            $ok = LaporanModel::delete($id);
            self::json(['success' => $ok, 'message' => 'Laporan berhasil dihapus.']);
            return true;
        }

        return false;
    }
}
