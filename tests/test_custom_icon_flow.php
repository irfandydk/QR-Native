<?php
declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Akses Ditolak: Skrip pengujian hanya dapat dijalankan melalui CLI.' . PHP_EOL);
}

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/models/GisModel.php';

echo "=== TEST CUSTOM ICON SAVE & RETRIEVAL FLOW ===\n";

$testId = 'pt-test-icon-' . time();
$sampleIcon = 'http://127.0.0.1:8000/uploads/icons/icon_1792053884_34b3a7b0.png';

$payload = [
    'id' => $testId,
    'id_operasi' => null,
    'label' => 'Titik Uji Logo Custom',
    'lat' => -4.015,
    'lng' => 122.585,
    'category' => 'Kantor SAR',
    'pointType' => 'pin_umum', // intentionally pass pin_umum to test auto-promotion
    'customIconUrl' => $sampleIcon,
    'description' => 'Test deskripsi titik dengan logo kustom'
];

$savedId = GisModel::savePoint($payload);
echo "1. Saved point with ID: {$savedId}\n";

$points = GisModel::getPoints(null, true);
$found = null;
foreach ($points as $p) {
    if ($p['id'] === $testId) {
        $found = $p;
        break;
    }
}

if (!$found) {
    echo "FAILED: Point not found in DB!\n";
    exit(1);
}

echo "2. Found point in DB:\n";
echo "   - ID: {$found['id']}\n";
echo "   - Label: {$found['label']}\n";
echo "   - PointType: {$found['pointType']}\n";
echo "   - CustomIconUrl: {$found['customIconUrl']}\n";

if ($found['pointType'] !== 'custom_upload') {
    echo "FAILED: pointType is not custom_upload! Got: " . $found['pointType'] . "\n";
    exit(1);
}

if ($found['customIconUrl'] !== $sampleIcon) {
    echo "FAILED: customIconUrl mismatch!\n";
    exit(1);
}

// Clean up test point
$db = getDB();
$db->exec("DELETE FROM GisPoint WHERE id = '{$testId}'");
echo "3. Cleaned up test point.\n";
echo "=== ALL CUSTOM ICON FLOW TESTS PASSED SUCCESSFULLY! ===\n";
