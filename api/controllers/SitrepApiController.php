<?php
/**
 * QR-SAR - Laporan Perkembangan (SITREP) API Controller
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 */

declare(strict_types=1);

class SitrepApiController extends BaseApiController {
    public static function handle(string $path, string $method, array $input): bool {
        // 1. Create SITREP under an operation
        if (preg_match('#^/operasi/(\d+)/laporan$#', $path, $m) && $method === 'POST') {
            self::requireAuth();
            $idOps = (int)$m[1];
            $ops = OperasiModel::getById($idOps);
            if (!$ops) {
                self::error('Operasi SAR tidak ditemukan.', 404);
            }
            if ($ops['status'] === 'CLOSED') {
                self::error('Operasi SAR telah berstatus DITUTUP (CLOSED). Laporan Perkembangan baru tidak dapat ditambahkan kecuali operasi dibuka kembali (Rollback).', 403);
            }
            $input['id_operasi'] = $idOps;
            try {
                $newId = LaporanModel::create($input);
                self::json(['success' => true, 'id' => $newId, 'message' => 'Laporan Perkembangan berhasil ditambahkan.']);
            } catch (\Throwable $e) {
                self::error($e->getMessage(), 400);
            }
            return true;
        }

        // 2. Update or Delete SITREP
        if (preg_match('#^/laporan/(\d+)$#', $path, $m)) {
            self::requireAuth();
            $id = (int)$m[1];
            $existingLap = LaporanModel::getById($id);
            if (!$existingLap) {
                self::error('Data Laporan Perkembangan (SITREP) tidak ditemukan.', 404);
            }
            $parentOps = OperasiModel::getById((int)$existingLap['id_operasi']);
            $isExistingPenutupan = (strtoupper((string)($existingLap['jenis_laporan'] ?? '')) === 'PENUTUPAN');
            if ($parentOps && $parentOps['status'] === 'CLOSED' && !($method !== 'DELETE' && $isExistingPenutupan)) {
                self::error('Operasi SAR telah berstatus DITUTUP (CLOSED). Laporan Perkembangan terkunci dan tidak dapat diubah/dihapus kecuali status operasi dibuka kembali.', 403);
            }

            if ($method === 'PUT' || $method === 'POST') {
                $ok = LaporanModel::update($id, $input);
                self::json(['success' => $ok, 'message' => 'Laporan Perkembangan berhasil diperbarui.']);
                return true;
            }

            if ($method === 'DELETE') {
                if (!AuthHelper::canDelete()) {
                    self::error('Akses ditolak: Hanya Administrator yang berwenang menghapus data SITREP.', 403);
                }
                $ok = LaporanModel::delete($id);
                self::json(['success' => $ok, 'message' => 'Laporan berhasil dihapus.']);
                return true;
            }
        }

        return false;
    }
}
