<?php
/**
 * QR-SAR - Berita Radio SAR (BRA) API Controller
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 */

declare(strict_types=1);

class BraApiController extends BaseApiController {
    public static function handle(string $path, string $method, array $input): bool {
        // Check Nomor BRA Unik (Peringatan Dini Tabrakan Nomor Kustom)
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

        return false;
    }
}
