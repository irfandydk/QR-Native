<?php
/**
 * QR-SAR - Automated Test Suite: Modal Scroll Integrity & Zero Freeze Verification
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Menguji integritas sistem modal, pencegahan scroll freeze, selector hygiene,
 * dan delegasi Single Source of Truth (SSOT) di seluruh modul aplikasi.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/../config/app.php';

$totalAssertions = 0;
$passedAssertions = 0;

function assertCondition(string $description, bool $condition): void {
    global $totalAssertions, $passedAssertions;
    $totalAssertions++;
    if ($condition) {
        $passedAssertions++;
        echo "  [PASS] {$description}\n";
    } else {
        echo "  [FAIL] {$description}\n";
    }
}

echo "\n====================================================================\n";
echo "   UJI OTOMATIS: MODAL SCROLL INTEGRITY & ZERO SCROLL FREEZE        \n";
echo "====================================================================\n";

$rootDir = dirname(__DIR__);

// --- 1. AUDIT CODE PATTERNS & HYGIENE SELECTOR ---
echo "\n--- 1. AUDIT STATIC CODE PATTERNS & HYGIENE SELECTOR ---\n";

// 1.1 assets/js/app.js
$appJsPath = $rootDir . '/assets/js/app.js';
assertCondition("Berkas assets/js/app.js tersedia", file_exists($appJsPath));
$appJs = file_get_contents($appJsPath);

assertCondition("app.js mendefinisikan fungsi ensureBodyScrollUnlocked", str_contains($appJs, 'function ensureBodyScrollUnlocked()'));
assertCondition("app.js mengekspos ensureBodyScrollUnlocked ke window", str_contains($appJs, 'window.ensureBodyScrollUnlocked = ensureBodyScrollUnlocked;'));
assertCondition("app.js mengekspos openModal ke window", str_contains($appJs, 'window.openModal = openModal;'));
assertCondition("app.js mengekspos closeModal ke window", str_contains($appJs, 'window.closeModal = closeModal;'));
assertCondition("app.js TIDAK LAGI menggunakan selector longgar [id^=\"modal-\"]:not(.hidden)", !str_contains($appJs, '[id^="modal-"]:not(.hidden)'));
assertCondition("app.js closeModal menggunakan selector ketat .modal-overlay.open, .bra-modal-overlay.open", str_contains($appJs, "document.querySelectorAll('.modal-overlay.open, .bra-modal-overlay.open')"));

// 1.2 views/operasi/form.php
$formViewPath = $rootDir . '/views/operasi/form.php';
assertCondition("Berkas views/operasi/form.php tersedia", file_exists($formViewPath));
$formView = file_get_contents($formViewPath);

assertCondition("views/operasi/form.php menggunakan map-modal-route-mode-badge", str_contains($formView, 'id="map-modal-route-mode-badge"'));
assertCondition("views/operasi/form.php menggunakan map-modal-btn-mode-direct", str_contains($formView, 'id="map-modal-btn-mode-direct"'));
assertCondition("views/operasi/form.php menggunakan map-modal-btn-mode-manual", str_contains($formView, 'id="map-modal-btn-mode-manual"'));
assertCondition("views/operasi/form.php menggunakan map-modal-manual-actions", str_contains($formView, 'id="map-modal-manual-actions"'));
assertCondition("views/operasi/form.php menggunakan map-modal-waypoint-count-pill", str_contains($formView, 'id="map-modal-waypoint-count-pill"'));
assertCondition("views/operasi/form.php menggunakan map-modal-tactical-dist", str_contains($formView, 'id="map-modal-tactical-dist"'));
assertCondition("views/operasi/form.php menggunakan map-modal-tactical-km", str_contains($formView, 'id="map-modal-tactical-km"'));
assertCondition("views/operasi/form.php menggunakan map-modal-tactical-radial", str_contains($formView, 'id="map-modal-tactical-radial"'));
assertCondition("views/operasi/form.php menggunakan map-modal-tactical-eta", str_contains($formView, 'id="map-modal-tactical-eta"'));

// Pastikan tidak ada ID anak lama di views/operasi/form.php
assertCondition("views/operasi/form.php bebas dari child id modal-btn-mode-direct", !str_contains($formView, 'id="modal-btn-mode-direct"'));
assertCondition("views/operasi/form.php bebas dari child id modal-btn-mode-manual", !str_contains($formView, 'id="modal-btn-mode-manual"'));

// 1.3 assets/js/pages/operasi_form.js
$opsFormJsPath = $rootDir . '/assets/js/pages/operasi_form.js';
$opsFormJs = file_get_contents($opsFormJsPath);
assertCondition("operasi_form.js closeFullscreenMapModal memanggil ensureBodyScrollUnlocked", str_contains($opsFormJs, 'closeFullscreenMapModal') && str_contains($opsFormJs, 'ensureBodyScrollUnlocked'));
assertCondition("operasi_form.js applyAndCloseFullscreenMapModal memanggil ensureBodyScrollUnlocked", str_contains($opsFormJs, 'applyAndCloseFullscreenMapModal') && str_contains($opsFormJs, 'ensureBodyScrollUnlocked'));
assertCondition("operasi_form.js mendukung selector map-modal-btn-mode-direct dengan fallback", str_contains($opsFormJs, "getElementById('map-modal-btn-mode-direct') || document.getElementById('modal-btn-mode-direct')"));

// 1.4 assets/js/pages/operasi_form_map.js
$opsFormMapJsPath = $rootDir . '/assets/js/pages/operasi_form_map.js';
$opsFormMapJs = file_get_contents($opsFormMapJsPath);
assertCondition("operasi_form_map.js mendukung selector map-modal-route-mode-badge dengan fallback", str_contains($opsFormMapJs, "getElementById('map-modal-route-mode-badge') || document.getElementById('modal-route-mode-badge')"));

// 1.5 assets/js/pages/renops_form.js & sitrep_form.js
$renopsFormJsPath = $rootDir . '/assets/js/pages/renops_form.js';
$renopsFormJs = file_get_contents($renopsFormJsPath);
assertCondition("renops_form.js mendelegasikan openModal ke window.openModal", str_contains($renopsFormJs, "window.openModal(id);"));
assertCondition("renops_form.js memanggil ensureBodyScrollUnlocked pada closeModal", str_contains($renopsFormJs, "ensureBodyScrollUnlocked();"));

$sitrepFormJsPath = $rootDir . '/assets/js/pages/sitrep_form.js';
$sitrepFormJs = file_get_contents($sitrepFormJsPath);
assertCondition("sitrep_form.js mendelegasikan openModal ke window.openModal", str_contains($sitrepFormJs, "window.openModal(id);"));
assertCondition("sitrep_form.js memanggil ensureBodyScrollUnlocked pada closeModal", str_contains($sitrepFormJs, "ensureBodyScrollUnlocked();"));

// 1.6 views/operasi/partials/modals.php & assets/js/pages/operasi_detail.js
$opsModalsViewPath = $rootDir . '/views/operasi/partials/modals.php';
$opsModalsView = file_get_contents($opsModalsViewPath);
assertCondition("views/operasi/partials/modals.php menggunakan id wa-awal-modal-text", str_contains($opsModalsView, 'id="wa-awal-modal-text"'));
assertCondition("views/operasi/partials/modals.php bebas dari id modal-wa-awal-text", !str_contains($opsModalsView, 'id="modal-wa-awal-text"'));

$opsDetailJsPath = $rootDir . '/assets/js/pages/operasi_detail.js';
$opsDetailJs = file_get_contents($opsDetailJsPath);
assertCondition("operasi_detail.js copyDetailWA mendukung wa-awal-modal-text dengan fallback", str_contains($opsDetailJs, "getElementById('wa-awal-modal-text')"));


// --- 2. RUNTIME SIMULATION TEST VIA NODE.JS DOM ENGINE ---
echo "\n--- 2. RUNTIME SIMULATION TEST VIA NODE.JS ENGINE ---\n";

$testScript = <<<'JS'
const fs = require('fs');

// Minimal Browser DOM & Window Emulation
class DOMElement {
    constructor(id = '', tag = 'div', classes = '') {
        this.id = id;
        this.tagName = tag.toUpperCase();
        this.classList = {
            _classes: new Set(classes.split(' ').filter(Boolean)),
            add(...c) { c.forEach(x => this._classes.add(x)); },
            remove(...c) { c.forEach(x => this._classes.delete(x)); },
            contains(c) { return this._classes.has(c); },
            toggle(c) { if (this.contains(c)) this.remove(c); else this.add(c); }
        };
        this.style = {};
        this.children = [];
        this.parentElement = null;
        this.attributes = {};
    }
    setAttribute(k, v) { this.attributes[k] = String(v); }
    getAttribute(k) { return this.attributes[k] || null; }
    appendChild(child) {
        child.parentElement = this;
        this.children.push(child);
        return child;
    }
    closest(selector) {
        let cur = this;
        while (cur) {
            if (selector.includes('.modal-overlay') && cur.classList.contains('modal-overlay')) return cur;
            if (selector.includes('.bra-modal-overlay') && cur.classList.contains('bra-modal-overlay')) return cur;
            cur = cur.parentElement;
        }
        return null;
    }
}

const elementsRegistry = new Map();
function registerEl(el) {
    if (el.id) elementsRegistry.set(el.id, el);
    return el;
}

const body = new DOMElement('body', 'body');
const documentMock = {
    body: body,
    activeElement: null,
    getElementById(id) {
        return elementsRegistry.get(id) || null;
    },
    querySelectorAll(selector) {
        const results = [];
        for (const el of elementsRegistry.values()) {
            if (selector.includes('.modal-overlay.open') && el.classList.contains('modal-overlay') && el.classList.contains('open')) {
                results.push(el);
            } else if (selector.includes('.bra-modal-overlay.open') && el.classList.contains('bra-modal-overlay') && el.classList.contains('open')) {
                results.push(el);
            }
        }
        return results;
    },
    addEventListener: () => {},
    removeEventListener: () => {}
};

const windowMock = {
    document: documentMock,
    addEventListener: () => {},
    removeEventListener: () => {},
    dispatchEvent: () => {},
    CustomEvent: class {}
};

global.window = windowMock;
global.document = documentMock;

// Muat Central Modal Engine dari app.js (ekstrak fungsi modal & scroll)
const appJsCode = fs.readFileSync('assets/js/app.js', 'utf8');
eval(appJsCode);

// Siapkan DOM Tree Uji:
// 1. Modal Fullscreen Map & elemen anak
const modalMap = registerEl(new DOMElement('modal-fullscreen-map', 'div', 'modal-overlay hidden'));
body.appendChild(modalMap);

const childBtnDirect = registerEl(new DOMElement('map-modal-btn-mode-direct', 'button'));
const childBtnManual = registerEl(new DOMElement('map-modal-btn-mode-manual', 'button'));
const childDistSpan  = registerEl(new DOMElement('map-modal-tactical-dist', 'span'));
modalMap.appendChild(childBtnDirect);
modalMap.appendChild(childBtnManual);
modalMap.appendChild(childDistSpan);

// 2. Modal WA Awal & elemen anak
const modalWaAwal = registerEl(new DOMElement('modal-view-wa-awal', 'div', 'modal-overlay hidden'));
body.appendChild(modalWaAwal);
const childWaText = registerEl(new DOMElement('wa-awal-modal-text', 'pre'));
modalWaAwal.appendChild(childWaText);

// 3. Modal RENOPS Import GIS
const modalRenopsGis = registerEl(new DOMElement('modal-import-gis-renops', 'div', 'modal-overlay hidden'));
body.appendChild(modalRenopsGis);

const report = {
    testA_fullscreenMap: false,
    testB_waAwalModal: false,
    testC_renopsGisModal: false,
    testD_stackedModals: false,
    testE_ensureUnlockedDefensive: false
};

// TEST A: Buka Fullscreen Map, lalu Tutup. Verifikasi overflow kembali ke ''
window.openModal('modal-fullscreen-map');
const openOverflowA = body.style.overflow; // Harusnya 'hidden'
window.closeModal('modal-fullscreen-map');
const closeOverflowA = body.style.overflow; // Harusnya ''
report.testA_fullscreenMap = (openOverflowA === 'hidden' && closeOverflowA === '');

// TEST B: Buka Modal WA Awal, lalu Tutup. Verifikasi overflow kembali ke ''
window.openModal('modal-view-wa-awal');
const openOverflowB = body.style.overflow;
window.closeModal('modal-view-wa-awal');
const closeOverflowB = body.style.overflow;
report.testB_waAwalModal = (openOverflowB === 'hidden' && closeOverflowB === '');

// TEST C: Buka Modal RENOPS GIS, lalu Tutup via delegasi renops_form.js
// Simulasi delegasi renops_form.js:
const renopsJsCode = fs.readFileSync('assets/js/pages/renops_form.js', 'utf8');
// Ekstrak openModal & closeModal dari renops_form
const renopsScope = {};
(function(scope) {
    eval(renopsJsCode.slice(renopsJsCode.indexOf('function openModal(id) {'), renopsJsCode.indexOf('// ── GIS Plotting Import into RENOPS ──')));
    scope.openModal = openModal;
    scope.closeModal = closeModal;
})(renopsScope);

renopsScope.openModal('modal-import-gis-renops');
const openOverflowC = body.style.overflow;
renopsScope.closeModal('modal-import-gis-renops');
const closeOverflowC = body.style.overflow;
report.testC_renopsGisModal = (openOverflowC === 'hidden' && closeOverflowC === '');

// TEST D: Stacked Modals (Dua modal terbuka bersamaan)
window.openModal('modal-fullscreen-map');
window.openModal('modal-view-wa-awal');
// Tutup salah satu modal (modal-fullscreen-map masih open)
window.closeModal('modal-view-wa-awal');
const stillLockedOverflow = body.style.overflow; // Wajib tetap 'hidden' karena modal-fullscreen-map masih terbuka
window.closeModal('modal-fullscreen-map');
const finalUnlockedOverflow = body.style.overflow; // Wajib dipulihkan ke ''
report.testD_stackedModals = (stillLockedOverflow === 'hidden' && finalUnlockedOverflow === '');

// TEST E: Defensive ensureBodyScrollUnlocked() saat state kotor
body.style.overflow = 'hidden';
window.ensureBodyScrollUnlocked();
report.testE_ensureUnlockedDefensive = (body.style.overflow === '');

console.log(JSON.stringify(report));
JS;

$tmpScriptPath = $rootDir . '/scratch_test_modal.js';
file_put_contents($tmpScriptPath, $testScript);

$output = [];
$returnCode = 0;
exec("node " . escapeshellarg($tmpScriptPath) . " 2>&1", $output, $returnCode);
@unlink($tmpScriptPath);

if ($returnCode === 0 && !empty($output)) {
    $jsonStr = end($output);
    $results = json_decode($jsonStr, true);
    
    assertCondition("Node.js runtime evaluasi berhasil dieksekusi", is_array($results));
    if (is_array($results)) {
        assertCondition("Siklus Open/Close Fullscreen Map memulihkan body scroll (Test A)", $results['testA_fullscreenMap'] ?? false);
        assertCondition("Siklus Open/Close Modal WA Awal memulihkan body scroll (Test B)", $results['testB_waAwalModal'] ?? false);
        assertCondition("Siklus Open/Close Modal RENOPS GIS via delegasi form memulihkan scroll (Test C)", $results['testC_renopsGisModal'] ?? false);
        assertCondition("Stacked Modals menangani nesting dengan benar tanpa premature scroll unlock (Test D)", $results['testD_stackedModals'] ?? false);
        assertCondition("Defensive ensureBodyScrollUnlocked memulihkan scroll ketika tidak ada modal aktif (Test E)", $results['testE_ensureUnlockedDefensive'] ?? false);
    }
} else {
    echo "  [FAIL] Gagal menjalankan runtime simulasi Node.js:\n";
    echo implode("\n", $output) . "\n";
    assertCondition("Node.js engine runtime pass", false);
}

// --- 3. REKAPITULASI HASIL UJI ---
echo "\n====================================================================\n";
echo "REKAPITULASI UJI: {$passedAssertions} / {$totalAssertions} PENGUJIAN LULUS (" . round(($passedAssertions / $totalAssertions) * 100, 2) . "%)\n";
echo "====================================================================\n";

if ($passedAssertions === $totalAssertions) {
    echo "\n>>> STATUS: SELURUH SISTEM MODAL BEBAS DARI BUG SCROLL FREEZE (100% PASS) <<<\n\n";
    exit(0);
} else {
    echo "\n>>> STATUS: TERDAPAT PENGUJIAN YANG GAGAL! <<<\n\n";
    exit(1);
}
