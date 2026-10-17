<?php
/**
 * QR-SAR - Master Data Operasional API Controller
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 */

declare(strict_types=1);

class MasterApiController extends BaseApiController {
    public static function handle(string $path, string $method, array $input): bool {
        // 1. Master Alut
        if ($path === '/master/alut') {
            if ($method === 'GET') {
                self::requireAuth();
                self::json(['success' => true, 'data' => MasterModel::getAlut()]);
                return true;
            }
            if ($method === 'POST') {
                self::requireAdmin();
                $id = MasterModel::createAlut($input['nama_alut'] ?? '', $input['satuan'] ?? 'Unit');
                self::json(['success' => true, 'id' => $id]);
                return true;
            }
        }

        if (preg_match('#^/master/alut/(\d+)$#', $path, $m)) {
            $id = (int)$m[1];
            if ($method === 'PUT' || $method === 'POST') {
                self::requireAdmin();
                $ok = MasterModel::updateAlut($id, $input['nama_alut'] ?? '', $input['satuan'] ?? 'Unit');
                self::json(['success' => $ok]);
                return true;
            }
            if ($method === 'DELETE') {
                self::requireAdmin();
                $ok = MasterModel::deleteAlut($id);
                self::json(['success' => $ok]);
                return true;
            }
        }

        // 2. Master Tim
        if ($path === '/master/tim') {
            if ($method === 'GET') {
                self::requireAuth();
                self::json(['success' => true, 'data' => MasterModel::getTim()]);
                return true;
            }
            if ($method === 'POST') {
                self::requireAdmin();
                $id = MasterModel::createTim($input['nama_tim'] ?? '');
                self::json(['success' => true, 'id' => $id]);
                return true;
            }
        }

        if (preg_match('#^/master/tim/(\d+)$#', $path, $m)) {
            $id = (int)$m[1];
            if ($method === 'PUT' || $method === 'POST') {
                self::requireAdmin();
                $ok = MasterModel::updateTim($id, $input['nama_tim'] ?? '');
                self::json(['success' => $ok]);
                return true;
            }
            if ($method === 'DELETE') {
                self::requireAdmin();
                $ok = MasterModel::deleteTim($id);
                self::json(['success' => $ok]);
                return true;
            }
        }

        // 3. SarUnit (Kansar, Pos SAR, Unit Siaga, KN SAR)
        if ($path === '/sar-units') {
            if ($method === 'GET') {
                self::requireAuth();
                self::json(['success' => true, 'data' => SarUnitModel::getAll()]);
                return true;
            }
            if ($method === 'POST') {
                self::requireAdmin();
                $id = SarUnitModel::create($input);
                self::json(['success' => true, 'id' => $id]);
                return true;
            }
        }

        // 4. Pejabat / SMC
        if ($path === '/settings/pejabat') {
            if ($method === 'GET') {
                self::requireAuth();
                self::json(['success' => true, 'data' => PejabatModel::getAll()]);
                return true;
            }
            if ($method === 'POST') {
                self::requireAdmin();
                $id = PejabatModel::create($input);
                self::json(['success' => true, 'id' => $id]);
                return true;
            }
        }

        if (preg_match('#^/settings/pejabat/(.+)$#', $path, $m)) {
            $id = $m[1];
            if ($method === 'PUT' || $method === 'POST') {
                self::requireAdmin();
                $ok = PejabatModel::update($id, $input);
                self::json(['success' => $ok]);
                return true;
            }
            if ($method === 'DELETE') {
                self::requireAdmin();
                $ok = PejabatModel::delete($id);
                self::json(['success' => $ok]);
                return true;
            }
        }

        return false;
    }
}
