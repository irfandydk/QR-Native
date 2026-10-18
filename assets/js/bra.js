/**
 * QR-SAR - BRA SAR Interactive Engine & Document Renderer
 * KPP Kendari - Basarnas
 */

// Generate letter code AAA, BBB, CCC...
const getSectionLetter = (typeof window !== 'undefined' && window.QrSarUtils && window.QrSarUtils.Bra && typeof window.QrSarUtils.Bra.getSectionLetter === 'function')
    ? window.QrSarUtils.Bra.getSectionLetter
    : function (index) {
        const char = String.fromCharCode(65 + (index % 26));
        return char.repeat(3);
    };

// Re-index all section labels on the form
function reindexSections() {
    const rows = document.querySelectorAll('.bra-section-row');
    rows.forEach((row, idx) => {
        const codeInput = row.querySelector('.sec-code-input');
        const codeBadge = row.querySelector('.sec-code-badge');
        const code = getSectionLetter(idx);
        if (codeInput) codeInput.value = code;
        if (codeBadge) codeBadge.textContent = code;
    });
    updateWordCount();
}

// Live Telegram Word Count (Standardized Senkom Formula)
function updateWordCount() {
    let text = '';
    const perihalEl = document.getElementById('perihalInput') || document.getElementById('bra-perihal');
    if (perihalEl && perihalEl.value.trim()) {
        text += perihalEl.value.trim() + ' ';
    }

    const sectionRows = document.querySelectorAll('.section-block, .bra-section-row');
    if (sectionRows.length > 0) {
        sectionRows.forEach(row => {
            const badge = row.querySelector('.sec-code-badge, .sec-code-input');
            const ta = row.querySelector('.sec-content-textarea, .sec-content-input');
            const code = badge ? (badge.textContent || badge.value || '').trim() : '';
            const isi = ta ? ta.value.trim() : '';
            if (isi) {
                if (code) text += code + ' ';
                text += isi + ' ';
            }
        });
    } else {
        document.querySelectorAll('.sec-content-textarea, .sec-content-input').forEach(el => {
            if (el.value.trim()) text += el.value.trim() + ' ';
        });
    }

    const words = text.trim().split(/\s+/).filter(Boolean);
    const count = words.length;
    const str = count + ' KATA';

    const countInput = document.getElementById('hiddenJumlahKata') || document.getElementById('bra-word-count');
    if (countInput) countInput.value = str;

    const displayEl = document.getElementById('liveWordCountBadge') || document.getElementById('word-count-badge');
    if (displayEl) displayEl.textContent = str;

    const pvEl = document.getElementById('pvJumlahKata');
    if (pvEl) pvEl.textContent = str;

    return count;
}

// Render QR Code with Center Basarnas Emblem on Canvas
function renderBraQRCode(canvasId, verifyUrl, logoUrl, retries = 0) {
    const canvas = typeof canvasId === 'string' ? document.getElementById(canvasId) : canvasId;
    if (!canvas) {
        if (retries < 25) setTimeout(() => renderBraQRCode(canvasId, verifyUrl, logoUrl, retries + 1), 80);
        return;
    }

    const qrEngine = window.QRCode || (typeof QRCode !== 'undefined' ? QRCode : null);
    if (!qrEngine) {
        if (retries < 25) {
            setTimeout(() => renderBraQRCode(canvasId, verifyUrl, logoUrl, retries + 1), 80);
        }
        return;
    }

    const size = 260;
    qrEngine.toCanvas(canvas, verifyUrl, {
        width: size,
        margin: 1,
        color: { dark: '#0a0f1d', light: '#ffffff' },
        errorCorrectionLevel: 'H'
    }, function (error) {
        if (error) {
            console.warn('QRCode canvas generation fallback:', error);
            return;
        }

        const ctx = canvas.getContext('2d');
        if (!ctx) return;

        // Draw center logo badge
        const logoImg = new Image();
        function drawCenterEmblem() {
            const logoSize = Math.floor(size * 0.22);
            const center = (size - logoSize) / 2;
            const radius = Math.floor(logoSize / 2) + 4;

            // White circle background
            ctx.fillStyle = '#ffffff';
            ctx.beginPath();
            ctx.arc(size / 2, size / 2, radius, 0, Math.PI * 2);
            ctx.fill();

            // Subtle border
            ctx.strokeStyle = '#cbd5e1';
            ctx.lineWidth = 1.5;
            ctx.stroke();

            if (logoImg.naturalWidth > 0) {
                // Center logo image
                ctx.save();
                ctx.beginPath();
                ctx.arc(size / 2, size / 2, Math.floor(logoSize / 2) - 1, 0, Math.PI * 2);
                ctx.clip();
                ctx.drawImage(logoImg, center, center, logoSize, logoSize);
                ctx.restore();
            } else {
                // Fallback SAR text badge
                ctx.fillStyle = '#ea580c';
                ctx.beginPath();
                ctx.arc(size / 2, size / 2, Math.floor(logoSize / 2), 0, Math.PI * 2);
                ctx.fill();
                ctx.fillStyle = '#ffffff';
                ctx.font = 'bold ' + Math.floor(logoSize * 0.44) + 'px sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText('SAR', size / 2, size / 2 + 1);
            }
        }

        logoImg.onload = drawCenterEmblem;
        logoImg.onerror = function() { drawCenterEmblem(); };
        if (logoUrl) {
            logoImg.src = logoUrl;
            if (logoImg.complete && logoImg.naturalWidth > 0) {
                drawCenterEmblem();
            }
        } else {
            drawCenterEmblem();
        }
    });
}

// Adjust Document Paper Scale & Size
function setPaperScale(delta) {
    const paper = document.getElementById('official-bra-document');
    if (!paper) return;

    let current = parseInt(paper.getAttribute('data-scale') || '100', 10);
    current = Math.min(125, Math.max(75, current + delta));
    paper.setAttribute('data-scale', current);
    paper.style.transform = `scale(${current / 100})`;
    paper.style.transformOrigin = 'top center';

    const label = document.getElementById('scale-label');
    if (label) label.textContent = current + '%';
}

function setPaperSize(size) {
    const paper = document.getElementById('official-bra-document');
    if (!paper) return;

    paper.classList.remove('paper-a4', 'paper-f4', 'paper-letter', 'paper-legal');
    paper.classList.add(`paper-${size.toLowerCase()}`);
    showToast(`Ukuran kertas diatur ke ${size}`, 'info');
}
