<?php
/**
 * QR-SAR - Rencana Operasi (RENOPS) API Controller
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 */

declare(strict_types=1);

class RenopsApiController extends BaseApiController {
    public static function handle(string $path, string $method, array $input): bool {
        // 1. Create RENOPS under an operation
        if (preg_match('#^/operasi/(\d+)/renops$#', $path, $m) && $method === 'POST') {
            self::requireAuth();
            $idOps = (int)$m[1];
            $ops = OperasiModel::getById($idOps);
            if (!$ops) {
                self::error('Operasi SAR tidak ditemukan.', 404);
            }
            if ($ops['status'] === 'CLOSED') {
                self::error('Operasi SAR telah berstatus DITUTUP (CLOSED). Rencana Operasi (RENOPS) baru tidak dapat ditambahkan kecuali operasi dibuka kembali (Rollback).', 403);
            }
            $input['id_operasi'] = $idOps;
            try {
                $newId = RenopsModel::create($input);
                self::json(['success' => true, 'id' => $newId, 'message' => 'Rencana Operasi SAR berhasil ditambahkan.']);
            } catch (\Throwable $e) {
                self::error($e->getMessage(), 400);
            }
            return true;
        }

        // 2. Update or Delete RENOPS
        if (preg_match('#^/renops/(\d+)$#', $path, $m)) {
            self::requireAuth();
            $id = (int)$m[1];
            $existingRenops = RenopsModel::getById($id);
            if (!$existingRenops) {
                self::error('Data Rencana Operasi (RENOPS) tidak ditemukan.', 404);
            }
            $parentOps = OperasiModel::getById((int)$existingRenops['id_operasi']);
            if ($parentOps && $parentOps['status'] === 'CLOSED') {
                self::error('Operasi SAR telah berstatus DITUTUP (CLOSED). Data RENOPS terkunci dan tidak dapat diubah/dihapus kecuali status operasi dibuka kembali.', 403);
            }

            if ($method === 'PUT' || $method === 'POST') {
                $ok = RenopsModel::update($id, $input);
                self::json(['success' => $ok, 'message' => 'Rencana Operasi berhasil diperbarui.']);
                return true;
            }

            if ($method === 'DELETE') {
                if (!AuthHelper::canDelete()) {
                    self::error('Akses ditolak: Hanya Administrator yang berwenang menghapus data RENOPS.', 403);
                }
                $ok = RenopsModel::delete($id);
                self::json(['success' => $ok, 'message' => 'Rencana Operasi berhasil dihapus.']);
                return true;
            }
        }

        return false;
    }
}
