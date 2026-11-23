<?php
/**
 * QR-SAR - Rencana Operasi (RENOPS) API Controller
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 */

declare(strict_types=1);

class RenopsApiController extends BaseApiController {
    public static function handle(string $path, string $method, array $input): bool {
        // Delete RENOPS
        if (preg_match('#^/renops/(\d+)$#', $path, $m) && $method === 'DELETE') {
            self::requireAuth();
            $id = (int)$m[1];
            $existingRenops = RenopsModel::getById($id);
            if (!$existingRenops) {
                self::error('Data Rencana Operasi (RENOPS) tidak ditemukan.', 404);
            }
            $parentStatus = OperasiModel::getStatusById((int)$existingRenops['id_operasi']);
            if ($parentStatus === 'CLOSED') {
                self::error('Operasi SAR telah berstatus DITUTUP (CLOSED). Data RENOPS terkunci dan tidak dapat diubah/dihapus kecuali status operasi dibuka kembali.', 403);
            }
            if (!AuthHelper::canDelete()) {
                self::error('Akses ditolak: Hanya Administrator yang berwenang menghapus data RENOPS.', 403);
            }
            $ok = RenopsModel::delete($id);
            self::json(['success' => $ok, 'message' => 'Rencana Operasi berhasil dihapus.']);
            return true;
        }

        return false;
    }
}
