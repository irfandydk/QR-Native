const assert = require('node:assert/strict');
const path = require('node:path');

const mod = require(path.resolve(__dirname, '../../assets/js/dokumentasi/collage_layouts.js'));

let passCount = 0;
function check(name, fn) {
  fn();
  passCount++;
  console.log('PASS ' + name);
}

check('exports core API and slot bounds 4..12', () => {
  assert.equal(mod.MIN_SLOTS, 4);
  assert.equal(mod.MAX_SLOTS, 12);
  assert.equal(mod.clampSlotCount(1), 4);
  assert.equal(mod.clampSlotCount(3), 4);
  assert.equal(mod.clampSlotCount(6), 6);
  assert.equal(mod.clampSlotCount(15), 12);
});

check('CANVAS_PRESETS covers vertical, square, and horizontal orientations', () => {
  const orientations = new Set(mod.CANVAS_PRESETS.map((p) => p.orientation));
  assert.ok(orientations.has('vertical'), 'must include vertical presets');
  assert.ok(orientations.has('horizontal'), 'must include horizontal presets');
  assert.ok(orientations.has('square'), 'must include square preset');
  assert.ok(mod.CANVAS_PRESETS.some((p) => p.id === 'portrait_2_3' && p.width === 1080 && p.height === 1620));
  assert.ok(mod.CANVAS_PRESETS.some((p) => p.id === 'landscape_16_9' && p.width === 1920 && p.height === 1080));
});

check('every slot count from 4 to 12 has valid non-overlapping layouts for vertical and horizontal', () => {
  const gridBox = { x: 40, y: 320, width: 1000, height: 1120 };
  for (let count = 4; count <= 12; count++) {
    const presets = mod.getLayoutsForCount(count);
    assert.ok(presets.length >= 2, `slot count ${count} should have at least 2 layout options`);

    for (const preset of presets) {
      assert.equal(preset.slots.length, count, `preset ${preset.id} must define ${count} slots`);
      const rects = mod.computeSlotRects(preset, gridBox, 16);
      assert.equal(rects.length, count);

      // Every rect stays strictly inside gridBox with positive dimensions and zero overlap
      for (let i = 0; i < rects.length; i++) {
        const a = rects[i];
        assert.ok(a.w > 20 && a.h > 20, `slot ${i} in ${preset.id} has valid size`);
        assert.ok(a.x >= gridBox.x - 0.5 && a.y >= gridBox.y - 0.5, `slot ${i} inside left/top`);
        assert.ok(a.x + a.w <= gridBox.x + gridBox.width + 0.5, `slot ${i} inside right`);
        assert.ok(a.y + a.h <= gridBox.y + gridBox.height + 0.5, `slot ${i} inside bottom`);

        for (let j = i + 1; j < rects.length; j++) {
          const b = rects[j];
          const overlapX = Math.min(a.x + a.w, b.x + b.w) - Math.max(a.x, b.x);
          const overlapY = Math.min(a.y + a.h, b.y + b.h) - Math.max(a.y, b.y);
          assert.ok(overlapX <= 0.5 || overlapY <= 0.5, `slots ${i} and ${j} in ${preset.id} must not overlap`);
        }
      }
    }
  }
});

check('getDefaultLayoutId adapts cleanly to vertical vs horizontal orientation', () => {
  const v6 = mod.getDefaultLayoutId(6, 'vertical');
  const h6 = mod.getDefaultLayoutId(6, 'horizontal');
  assert.notEqual(v6, h6, '6-slot vertical default (2x3) differs from horizontal default (3x2)');
});

check('computeCoverCrop handles portrait-in-landscape, landscape-in-portrait, zoom & pan clamp', () => {
  const crop1 = mod.computeCoverCrop(1920, 1080, 400, 400, 1, 0, 0);
  assert.equal(Math.round(crop1.sw), 1080);
  assert.equal(Math.round(crop1.sh), 1080);
  assert.equal(Math.round(crop1.sx), 420);
  assert.equal(Math.round(crop1.sy), 0);

  const cropZoom = mod.computeCoverCrop(1000, 1000, 500, 500, 2, 100, -100);
  assert.equal(Math.round(cropZoom.sw), 500);
  assert.equal(Math.round(cropZoom.sh), 500);
  assert.ok(cropZoom.sx >= 0 && cropZoom.sx + cropZoom.sw <= 1000.01);
  assert.ok(cropZoom.sy >= 0 && cropZoom.sy + cropZoom.sh <= 1000.01);
});

check('wrapCanvasLines wraps long Basarnas titles cleanly within maxWidth and maxLines', () => {
  const measure = (txt) => txt.length * 10;
  const title = 'Dok Operasi SAR H1 Kecelakaan Kapal, 1 Orang Nelayan Diduga Terjatuh dari Perahu di Perairan Batauga Kab. Buton Selatan.';
  const lines = mod.wrapCanvasLines(measure, title, 360, 4);
  assert.ok(lines.length >= 2 && lines.length <= 4);
  for (const line of lines) {
    assert.ok(measure(line) <= 360 || !line.includes(' '), `line "${line}" fits width`);
  }
});

check('formatCollageFilename generates clean filesystem-safe filenames', () => {
  const fname = mod.formatCollageFilename('H1', 'Kecelakaan Kapal / Perahu di Batauga!', 'jpg');
  assert.equal(fname, 'DOK_SAR_H1_KECELAKAAN_KAPAL_PERAHU_DI_BATAUGA.jpg');
});

check('COLLAGE_STYLES provides Klasik Resmi KPP and Editorial Modern themes', () => {
  assert.ok(Array.isArray(mod.COLLAGE_STYLES) && mod.COLLAGE_STYLES.length >= 2);
  assert.ok(mod.COLLAGE_STYLES.some((s) => s.id === 'klasik_kpp'));
  assert.ok(mod.COLLAGE_STYLES.some((s) => s.id === 'editorial_modern'));
});

check('QUALITY_PRESETS supports Full HD, 2K QHD, and 4K Ultra HD dimensions', () => {
  assert.ok(Array.isArray(mod.QUALITY_PRESETS) && mod.QUALITY_PRESETS.length >= 3);
  const p23 = mod.getCanvasPreset('portrait_2_3');
  const p169 = mod.getCanvasPreset('landscape_16_9');
  const fhd = mod.resolveExportDimensions(p23, 'fhd');
  const qhd = mod.resolveExportDimensions(p23, 'qhd');
  const uhd = mod.resolveExportDimensions(p169, 'uhd_4k');
  assert.equal(fhd.width, 1080);
  assert.equal(fhd.height, 1620);
  assert.equal(qhd.width, 1620);
  assert.equal(qhd.height, 2430);
  assert.equal(uhd.width, 3840);
  assert.equal(uhd.height, 2160);
  assert.ok(uhd.videoBitsPerSecond >= 12000000);
});

check('formatEmergencyLine outputs clean 115 / telp / WA line without duplicates', () => {
  assert.equal(mod.formatEmergencyLine('0401 3196 557', '08114034115'), 'EMERGENCY CALL : 115 / 0401 3196 557 / WA: 08114034115');
  assert.equal(mod.formatEmergencyLine('115 / 0401 3196 557', '08114034115'), 'EMERGENCY CALL : 115 / 0401 3196 557 / WA: 08114034115');
  assert.equal(mod.formatEmergencyLine('0401 3196 557', ''), 'EMERGENCY CALL : 115 / 0401 3196 557');
  assert.equal(mod.formatEmergencyLine('', '08114034115'), 'EMERGENCY CALL : 115 / WA: 08114034115');
});

console.log(`ALL PASS (${passCount} tests)`);
