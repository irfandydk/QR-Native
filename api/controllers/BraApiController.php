<?php
/**
 * QR-SAR - Berita Radio SAR (BRA) API Controller
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 */

declare(strict_types=1);

class BraApiController extends BaseApiController {
    public static function handle(string $path, string $method, array $input): bool {
        // 1. BRA List
        if ($path === '/bra' && $method === 'GET') {
            self::requireAuth();
            $kat = $_GET['kategori'] ?? null;
            $q = $_GET['q'] ?? null;
            self::json(['success' => true, 'data' => BraModel::getAll($kat, $q)]);
            return true;
        }

        // 2. Next Nomor BRA
        if ($path === '/bra/next-number' && $method === 'GET') {
            self::requireAuth();
            $kat = BraHelper::normalizeCategory($_GET['kategori'] ?? 'MAPEL');
            $satker = $_GET['satker'] ?? DEFAULT_SATKER_KODE;
            self::json(['success' => true, 'data' => BraHelper::getNextNomorBra($kat, $satker)]);
            return true;
        }

        // 2b. Check Nomor BRA Unik (Peringatan Dini Tabrakan Nomor Kustom)
        if ($path === '/bra/check-number' && $method === 'GET') {
            self::requireAuth();
            $noBra = trim((string)($_GET['no_bra'] ?? ''));
            $excludeId = (int)($_GET['exclude_id'] ?? 0);
            if ($noBra === '') {
                self::json(['success' => true, 'exists' => false]);
                return true;
            }
            $db = getDB();
            $stmt = $db->prepare("SELECT id_bra, no_bra, perihal, kategori FROM BraSar WHERE no_bra = :no_bra AND id_bra != :exclude_id LIMIT 1");
            $stmt->execute([':no_bra' => $noBra, ':exclude_id' => $excludeId]);
            $existing = $stmt->fetch();
            self::json([
                'success' => true,
                'exists' => (bool)$existing,
                'data' => $existing ?: null
            ]);
            return true;
        }

        // 3. Create BRA
        if ($path === '/bra' && $method === 'POST') {
            self::requireAuth();
            $newId = BraModel::create($input);
            self::json(['success' => true, 'id' => $newId, 'message' => 'Berita Radio SAR (BRA) berhasil disimpan.']);
            return true;
        }

        // 6. BRA Detail / Update / Delete
        if (preg_match('#^/bra/(\d+)$#', $path, $m)) {
            $id = (int)$m[1];
            if ($method === 'GET') {
                self::requireAuth();
                $bra = BraModel::getById($id);
                if (!$bra) {
                    self::error('Dokumen BRA tidak ditemukan', 404);
                }
                self::json(['success' => true, 'data' => $bra]);
                return true;
            }

            if ($method === 'PUT' || $method === 'POST') {
                self::requireAuth();
                $ok = BraModel::update($id, $input);
                self::json(['success' => $ok, 'message' => 'Dokumen BRA SAR berhasil diperbarui.']);
                return true;
            }

            if ($method === 'DELETE') {
                self::requireAuth();
                if (!AuthHelper::canDelete()) {
                    self::error('Akses ditolak.', 403);
                }
                $ok = BraModel::delete($id);
                self::json(['success' => $ok, 'message' => 'Dokumen BRA SAR berhasil dihapus.']);
                return true;
            }
        }

        return false;
    }
}
