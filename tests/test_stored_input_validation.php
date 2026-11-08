<?php
/**
 * Test: data yang disimpan lalu dirender ulang ke HTML/JS divalidasi di model.
 * - ID objek GIS hanya [A-Za-z0-9_.:-]{1,100}; selain itu ditolak (InvalidArgumentException).
 * - Warna GIS hanya hex / rgb(a) / hsl(a) / nama warna; selain itu diganti warna bawaan.
 * - status_korban hanya DP|S|MD|H.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Akses Ditolak: Skrip pengujian hanya dapat dijalankan melalui CLI.' . PHP_EOL);
}

require_once __DIR__ . '/../config/app.php';

echo "=== TEST VALIDASI DATA TERSIMPAN ===\n\n";

$testPassed = 0;
$testTotal = 0;

function assertCondition(bool $cond, string $message): void {
    global $testPassed, $testTotal;
    $testTotal++;
    if ($cond) {
        $testPassed++;
        echo " [PASS] {$message}\n";
    } else {
        echo " [FAIL] {$message}\n";
    }
}

function rejects(callable $fn): bool {
    try { $fn(); return false; } catch (InvalidArgumentException $e) { return true; } catch (Throwable $e) { return false; }
}

$db = getDB();
$_SESSION = ['user_id' => 'USR-ADMIN', 'role' => 'ADMIN', 'user' => ['id' => 'USR-ADMIN', 'username' => 'admin', 'role' => 'ADMIN']];
$bad = '"><img src=x onerror=alert(1)>';
$sfx = bin2hex(random_bytes(3));

// 1. ID GIS
assertCondition(rejects(fn() => GisModel::savePoint(['id' => $bad, 'lat' => -4, 'lng' => 122, 'label' => 'x'])), 'savePoint menolak id berisi markup');
assertCondition(rejects(fn() => GisModel::savePolygon(['id' => $bad, 'name' => 'x'])), 'savePolygon menolak id berisi markup');
assertCondition(rejects(fn() => GisModel::saveRoute(['id' => $bad, 'name' => 'x'])), 'saveRoute menolak id berisi markup');
assertCondition(rejects(fn() => GisModel::saveSearchPattern(['id' => $bad, 'name' => 'x'])), 'saveSearchPattern menolak id berisi markup');
assertCondition((int)$db->query("SELECT COUNT(*) FROM GisPoint WHERE id LIKE '%onerror%'")->fetchColumn() === 0, 'Tidak ada baris GIS ber-id markup yang tersimpan');

// 2. Warna GIS
$polyId = "poly-uji-{$sfx}";
$routeId = "route-uji-{$sfx}";
$patId = "pattern-uji-{$sfx}";
$pointId = "pt-uji-{$sfx}";
GisModel::savePoint(['id' => $pointId, 'lat' => -4, 'lng' => 122, 'label' => 'uji']);
assertCondition((bool)$db->query("SELECT COUNT(*) FROM GisPoint WHERE id = " . $db->quote($pointId))->fetchColumn(), 'ID GIS normal tetap tersimpan');
GisModel::savePolygon(['id' => $polyId, 'name' => 'uji', 'strokeColor' => 'red;background:url(x)', 'fillColor' => 'rgba(1, 2, 3, 0.4)', 'points' => []]);
$poly = $db->query("SELECT strokeColor, fillColor FROM GisPolygon WHERE id = " . $db->quote($polyId))->fetch(PDO::FETCH_ASSOC);
assertCondition(($poly['strokeColor'] ?? '') === '#ea580c', 'Warna garis poligon tidak sah diganti bawaan');
assertCondition(($poly['fillColor'] ?? '') === 'rgba(1, 2, 3, 0.4)', 'Warna rgba sah dipertahankan');
GisModel::saveRoute(['id' => $routeId, 'name' => 'uji', 'strokeColor' => '"><b>x</b>']);
assertCondition($db->query("SELECT strokeColor FROM GisRoute WHERE id = " . $db->quote($routeId))->fetchColumn() === '#059669', 'Warna rute tidak sah diganti bawaan');
GisModel::saveSearchPattern(['id' => $patId, 'name' => 'uji', 'color' => 'hsl(200, 50%, 40%)', 'polygonId' => $bad]);
$pat = $db->query("SELECT color, polygonId FROM GisSearchPattern WHERE id = " . $db->quote($patId))->fetch(PDO::FETCH_ASSOC);
assertCondition(($pat['color'] ?? '') === 'hsl(200, 50%, 40%)', 'Warna hsl sah dipertahankan');
assertCondition(($pat['polygonId'] ?? 'x') === '', 'Referensi polygonId tidak sah dikosongkan');
GisModel::saveRoute(['id' => $routeId, 'name' => 'uji', 'strokeColor' => 'Orange']);
assertCondition($db->query("SELECT strokeColor FROM GisRoute WHERE id = " . $db->quote($routeId))->fetchColumn() === 'Orange', 'Nama warna sah dipertahankan');
foreach (['<style>' => 'A', '<!--' => 'A', 'B' => 'B', '🚨' => '🚨'] as $in => $want) {
    GisModel::savePoint(['id' => $pointId, 'lat' => -4, 'lng' => 122, 'label' => 'uji', 'pointType' => 'alphabet', 'alphabetChar' => $in]);
    $got = $db->query("SELECT alphabetChar FROM GisPoint WHERE id = " . $db->quote($pointId))->fetchColumn();
    assertCondition($got === $want, 'alphabetChar ' . json_encode($in) . ' disimpan sebagai ' . json_encode($want) . ' (dapat ' . json_encode($got) . ')');
}
GisModel::deletePoint($pointId);
GisModel::deletePolygon($polyId);
GisModel::deleteRoute($routeId);
GisModel::deleteSearchPattern($patId);

// 3. status_korban
$opsId = OperasiModel::create([
    'nama_operasi' => "Operasi Uji Validasi {$sfx}",
    'nama_pelapor' => 'Uji', 'waktu_kejadian' => '2026-11-06 07:00:00', 'waktu_lapor' => '2026-11-06 07:30:00',
    'lokasi_kejadian' => 'Teluk Kendari', 'kronologi' => 'Uji', 'jumlah_pob' => 2,
    'korban' => [
        ['nama_korban' => 'Korban Uji A', 'umur' => '30', 'jk' => 'Laki-laki', 'alamat' => 'Kendari', 'status_korban' => '<svg onload=1>'],
        ['nama_korban' => 'Korban Uji B', 'umur' => '31', 'jk' => 'Laki-laki', 'alamat' => 'Kendari', 'status_korban' => 'md'],
    ],
]);
$korban = $db->query("SELECT id_korban, status_korban FROM Korban WHERE id_operasi = {$opsId} ORDER BY id_korban")->fetchAll(PDO::FETCH_ASSOC);
assertCondition(($korban[0]['status_korban'] ?? '') === 'DP', 'Status korban tidak sah saat buat operasi menjadi DP');
assertCondition(($korban[1]['status_korban'] ?? '') === 'MD', 'Status korban sah (huruf kecil) dinormalkan ke MD');

$lapId = 0;
try {
    $lapId = LaporanModel::create(['id_operasi' => $opsId, 'jenis_laporan' => 'PERKEMBANGAN', 'update_korban' => [(int)$korban[1]['id_korban'] => '"><x']]);
} catch (Throwable $e) {
    echo "   (LaporanModel::create: {$e->getMessage()})\n";
}
assertCondition($lapId > 0, 'Laporan perkembangan uji tersimpan');
$st = $db->query("SELECT status_korban FROM Korban WHERE id_korban = " . (int)$korban[1]['id_korban'])->fetchColumn();
assertCondition($st === 'MD', 'Status korban tidak sah dari laporan perkembangan diabaikan');
OperasiModel::delete($opsId);

echo "\nTotal: {$testTotal}, Lolos: {$testPassed}\n";
exit($testPassed === $testTotal ? 0 : 1);
