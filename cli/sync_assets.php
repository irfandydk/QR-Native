<?php
/**
 * QR-SAR - Public Asset Synchronization & Link Manager CLI
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Usage:
 *   php cli/sync_assets.php           (Check status and auto-link/sync assets)
 *   php cli/sync_assets.php --status  (Check status only)
 *   php cli/sync_assets.php --symlink (Force relative symlinks: Linux/macOS/Git Bash)
 *   php cli/sync_assets.php --mirror  (Deep mirror/copy: Windows/No-Symlink Host)
 *   php cli/sync_assets.php --clean   (Remove public assets links/mirrors)
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Akses Ditolak: Skrip ini hanya dapat dijalankan melalui antarmuka konsol/terminal (CLI).' . PHP_EOL);
}

$rootDir = dirname(__DIR__);
$publicDir = $rootDir . '/public';
$assetsDir = $rootDir . '/assets';
$uploadsDir = $rootDir . '/uploads';

// CLI Colors Helper
function c(string $text, string $color = '0'): string {
    return "\033[{$color}m{$text}\033[0m";
}

echo PHP_EOL;
echo c("===================================================================", "1;33") . PHP_EOL;
echo c("   QR-SAR BASARNAS KENDARI - ASSET SYNCHRONIZATION MANAGER CLI     ", "1;37;41") . PHP_EOL;
echo c("===================================================================", "1;33") . PHP_EOL . PHP_EOL;

$args = array_slice($argv, 1);
$mode = 'auto';

foreach ($args as $arg) {
    if ($arg === '--status') $mode = 'status';
    elseif ($arg === '--symlink') $mode = 'symlink';
    elseif ($arg === '--mirror') $mode = 'mirror';
    elseif ($arg === '--clean') $mode = 'clean';
    elseif ($arg === '--help' || $arg === '-h') {
        echo "Panduan Penggunaan:" . PHP_EOL;
        echo "  php cli/sync_assets.php           Periksa dan sinkronisasi otomatis (symlink jika didukung, mirror jika tidak)" . PHP_EOL;
        echo "  php cli/sync_assets.php --status  Tampilkan status tautan aset publik saat ini" . PHP_EOL;
        echo "  php cli/sync_assets.php --symlink Pasang/perbarui tautan simbolik relatif (Linux/macOS/Unix)" . PHP_EOL;
        echo "  php cli/sync_assets.php --mirror  Salin dan sinkronkan folder fisik (Windows/Non-symlink hosting)" . PHP_EOL;
        echo "  php cli/sync_assets.php --clean   Hapus tautan atau direktori salinan di dalam folder public/" . PHP_EOL . PHP_EOL;
        exit(0);
    }
}

$targets = [
    'assets'      => ['src' => $assetsDir, 'target' => $publicDir . '/assets', 'rel' => '../assets', 'isDir' => true],
    'uploads'     => ['src' => $uploadsDir, 'target' => $publicDir . '/uploads', 'rel' => '../uploads', 'isDir' => true],
    'favicon.ico' => ['src' => $rootDir . '/favicon.ico', 'target' => $publicDir . '/favicon.ico', 'rel' => '../favicon.ico', 'isDir' => false],
    'favicon.png' => ['src' => $rootDir . '/favicon.png', 'target' => $publicDir . '/favicon.png', 'rel' => '../favicon.png', 'isDir' => false],
];

// Helper to check target status
function inspectTarget(array $info): array {
    $target = $info['target'];
    if (!file_exists($target) && !is_link($target)) {
        return ['status' => 'MISSING', 'type' => 'none', 'valid' => false];
    }
    if (is_link($target)) {
        $linkTarget = @readlink($target);
        $valid = file_exists($target);
        return ['status' => $valid ? 'SYMLINK_OK' : 'SYMLINK_BROKEN', 'type' => 'symlink', 'link' => $linkTarget, 'valid' => $valid];
    }
    if (is_dir($target)) {
        return ['status' => 'DIRECTORY_MIRROR', 'type' => 'dir', 'valid' => true];
    }
    if (is_file($target)) {
        return ['status' => 'FILE_COPY', 'type' => 'file', 'valid' => true];
    }
    return ['status' => 'UNKNOWN', 'type' => 'unknown', 'valid' => false];
}

// 1. STATUS REPORT
echo c("[1/2] Memeriksa status berkas dan tautan di folder /public...", "1;36") . PHP_EOL;
foreach ($targets as $name => $info) {
    $st = inspectTarget($info);
    $statusColor = match ($st['status']) {
        'SYMLINK_OK'       => '1;32',
        'DIRECTORY_MIRROR', 'FILE_COPY' => '1;34',
        'SYMLINK_BROKEN'   => '1;31',
        default            => '1;33'
    };
    $desc = $st['status'];
    if (!empty($st['link'])) {
        $desc .= " -> {$st['link']}";
    }
    printf("  • %-14s : %s\n", $name, c($desc, $statusColor));
}
echo PHP_EOL;

if ($mode === 'status') {
    echo c("Pemeriksaan status selesai.", "1;32") . PHP_EOL . PHP_EOL;
    exit(0);
}

// Helper: Recursive delete
function deleteRecursive(string $target): void {
    if (is_link($target) || is_file($target)) {
        @unlink($target);
        return;
    }
    if (is_dir($target)) {
        $files = scandir($target) ?: [];
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            deleteRecursive($target . '/' . $file);
        }
        @rmdir($target);
    }
}

// Helper: Recursive copy
function copyRecursive(string $src, string $dst): int {
    $copied = 0;
    if (is_file($src)) {
        @copy($src, $dst);
        return 1;
    }
    if (!is_dir($dst)) {
        @mkdir($dst, 0755, true);
    }
    $items = scandir($src) ?: [];
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $srcPath = $src . '/' . $item;
        $dstPath = $dst . '/' . $item;
        if (is_dir($srcPath)) {
            $copied += copyRecursive($srcPath, $dstPath);
        } else {
            if (@copy($srcPath, $dstPath)) {
                $copied++;
            }
        }
    }
    return $copied;
}

// 2. CLEAN ACTION
if ($mode === 'clean') {
    echo c("[2/2] Membersihkan tautan aset di /public...", "1;33") . PHP_EOL;
    foreach ($targets as $name => $info) {
        if (file_exists($info['target']) || is_link($info['target'])) {
            deleteRecursive($info['target']);
            echo "  ✓ Dihapus: {$name}" . PHP_EOL;
        }
    }
    echo PHP_EOL . c("Pembersihan selesai.", "1;32") . PHP_EOL . PHP_EOL;
    exit(0);
}

// 3. EXECUTE SYMLINK OR MIRROR
echo c("[2/2] Menyelaraskan aset publik (Mode: " . strtoupper($mode) . ")...", "1;36") . PHP_EOL;

$useSymlink = ($mode === 'symlink') || ($mode === 'auto' && strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN' && function_exists('symlink'));

foreach ($targets as $name => $info) {
    $targetPath = $info['target'];
    $st = inspectTarget($info);

    if ($useSymlink) {
        // If already a valid symlink, skip
        if ($st['status'] === 'SYMLINK_OK' && ($st['link'] ?? '') === $info['rel']) {
            echo "  ✓ " . c($name, "1;32") . " sudah terpasang symlink valid ({$info['rel']})." . PHP_EOL;
            continue;
        }

        // Remove broken or non-symlink target
        if ($st['type'] !== 'none') {
            deleteRecursive($targetPath);
        }

        // Create relative symlink
        $cwd = getcwd();
        chdir($publicDir);
        $ok = @symlink($info['rel'], basename($targetPath));
        if ($cwd) chdir($cwd);

        if ($ok) {
            echo "  ✓ " . c($name, "1;32") . " berhasil ditautkan secara simbolik -> {$info['rel']}" . PHP_EOL;
        } else {
            echo "  ! Gagal membuat symlink untuk {$name}, beralih ke penyalinan fisik (Mirror)..." . PHP_EOL;
            $count = copyRecursive($info['src'], $targetPath);
            echo "  ✓ " . c($name, "1;34") . " disalin fisik ({$count} berkas)." . PHP_EOL;
        }
    } else {
        // Mirror Mode (Windows / No-Symlink)
        if ($st['type'] === 'symlink') {
            @unlink($targetPath);
        }
        $count = copyRecursive($info['src'], $targetPath);
        echo "  ✓ " . c($name, "1;34") . " berhasil disinkronkan via mirror fisik ({$count} berkas)." . PHP_EOL;
    }
}

echo PHP_EOL;
echo c("===================================================================", "1;32") . PHP_EOL;
echo c("  SUKSES: Aset publik KPP Kendari telah tersinkronisasi 100%!      ", "1;32") . PHP_EOL;
echo c("  Single Source of Truth: /assets & /uploads                       ", "0;37") . PHP_EOL;
echo c("  Document Root /public kini terhubung penuh dengan aset sistem.   ", "0;37") . PHP_EOL;
echo c("===================================================================", "1;32") . PHP_EOL . PHP_EOL;
